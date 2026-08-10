// sovra medical-devices agent — plattformübergreifender GDT-Ordner-Watcher.
//
// Beobachtet je Geräte-Ordner (Token) neue GDT-Dateien und postet die ROHE Datei an den
// nodera-Ingress. Parsen/Matchen/Strippen macht nodera — der Agent bleibt bewusst dumm.
//
// Nur Standardbibliothek. Bauen: `go build`. Cross: siehe build.sh.
package main

import (
	"bytes"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"time"
)

type Watch struct {
	Folder  string `json:"folder"`
	Token   string `json:"token"`
	Pattern string `json:"pattern"` // optional, z.B. "*.gdt"; leer = alle Dateien
}

type Config struct {
	BaseURL       string  `json:"base_url"`       // z.B. https://nodera.sovra.health
	PollSeconds   int     `json:"poll_seconds"`   // Scan-Intervall (Default 5)
	StableSeconds int     `json:"stable_seconds"` // Datei muss so lange unverändert sein (Default 3)
	Watches       []Watch `json:"watches"`
}

type fileState struct {
	size  int64
	mtime time.Time
	since time.Time // seit wann unverändert
}

var (
	client = &http.Client{Timeout: 30 * time.Second}
	// dedupe: gesendete Datei-Hashes (überlebt keinen Neustart; das Archiv verhindert Re-Scan)
	sent = map[string]bool{}
)

func main() {
	cfgPath := flag.String("config", "config.json", "Pfad zur Config-Datei")
	once := flag.Bool("once", false, "einmal scannen und beenden (Test)")
	flag.Parse()

	cfg, err := loadConfig(*cfgPath)
	if err != nil {
		log.Fatalf("Config: %v", err)
	}
	if cfg.PollSeconds <= 0 {
		cfg.PollSeconds = 5
	}
	if cfg.StableSeconds <= 0 {
		cfg.StableSeconds = 3
	}
	cfg.BaseURL = strings.TrimRight(cfg.BaseURL, "/")

	log.Printf("sovra medical-devices agent — %d Ordner, Ziel %s (poll %ds, stable %ds)",
		len(cfg.Watches), cfg.BaseURL, cfg.PollSeconds, cfg.StableSeconds)

	states := map[string]map[string]fileState{} // folder -> file -> state
	for _, w := range cfg.Watches {
		states[w.Folder] = map[string]fileState{}
	}

	for {
		for _, w := range cfg.Watches {
			scan(cfg, w, states[w.Folder], *once)
		}
		if *once {
			return
		}
		time.Sleep(time.Duration(cfg.PollSeconds) * time.Second)
	}
}

func loadConfig(path string) (Config, error) {
	var c Config
	b, err := os.ReadFile(path)
	if err != nil {
		return c, err
	}
	if err := json.Unmarshal(b, &c); err != nil {
		return c, fmt.Errorf("ungültiges JSON: %w", err)
	}
	if c.BaseURL == "" {
		return c, fmt.Errorf("base_url fehlt")
	}
	for i, w := range c.Watches {
		if w.Folder == "" || w.Token == "" {
			return c, fmt.Errorf("watch #%d: folder und token sind Pflicht", i+1)
		}
	}
	return c, nil
}

func scan(cfg Config, w Watch, state map[string]fileState, immediate bool) {
	entries, err := os.ReadDir(w.Folder)
	if err != nil {
		log.Printf("[%s] Ordner nicht lesbar: %v", w.Folder, err)
		return
	}

	stable := time.Duration(cfg.StableSeconds) * time.Second
	now := time.Now()

	for _, e := range entries {
		if e.IsDir() {
			continue // archiv/ und failed/ überspringen
		}
		name := e.Name()
		if strings.HasPrefix(name, ".") {
			continue
		}
		if w.Pattern != "" {
			if ok, _ := filepath.Match(w.Pattern, name); !ok {
				continue
			}
		}

		info, err := e.Info()
		if err != nil {
			continue
		}

		full := filepath.Join(w.Folder, name)
		if immediate {
			// --once: sofort verarbeiten (kein Stabilitäts-Warten)
			process(cfg, w, full, name)
			continue
		}

		prev, seen := state[name]
		if !seen || prev.size != info.Size() || !prev.mtime.Equal(info.ModTime()) {
			// neu oder verändert → Stabilitäts-Uhr (neu) starten
			state[name] = fileState{size: info.Size(), mtime: info.ModTime(), since: now}
			continue
		}

		// unverändert — lange genug?
		if now.Sub(prev.since) < stable {
			continue
		}

		if process(cfg, w, full, name) {
			delete(state, name) // erledigt (verschoben)
		}
	}
}

// process gibt true zurück, wenn die Datei bearbeitet (verschoben) wurde.
func process(cfg Config, w Watch, full, name string) bool {
	data, err := os.ReadFile(full)
	if err != nil {
		log.Printf("[%s] lesen fehlgeschlagen: %v", name, err)
		return false
	}
	if len(bytes.TrimSpace(data)) == 0 {
		moveTo(w.Folder, "failed", full, name)
		log.Printf("[%s] leer → failed/", name)
		return true
	}

	sum := sha256.Sum256(data)
	hash := hex.EncodeToString(sum[:])
	if sent[hash] {
		moveTo(w.Folder, "archiv", full, name)
		log.Printf("[%s] Duplikat (bereits gesendet) → archiv/", name)
		return true
	}

	status, body, err := post(cfg.BaseURL, w.Token, data)
	if err != nil {
		log.Printf("[%s] Netzwerkfehler, erneuter Versuch später: %v", name, err)
		return false // liegen lassen, nächster Scan probiert erneut
	}

	switch {
	case status >= 200 && status < 300:
		sent[hash] = true
		moveTo(w.Folder, "archiv", full, name)
		log.Printf("[%s] gesendet (%d) → archiv/  %s", name, status, oneline(body))
		return true
	case status == 401 || status == 403:
		log.Printf("[%s] Token abgelehnt (%d) — liegen lassen (Config prüfen)", name, status)
		return false // Token-Problem: nicht in failed/, sondern Config fixen
	case status >= 400 && status < 500:
		moveTo(w.Folder, "failed", full, name)
		log.Printf("[%s] abgelehnt (%d) → failed/  %s", name, status, oneline(body))
		return true
	default:
		log.Printf("[%s] Serverfehler (%d), erneuter Versuch später", name, status)
		return false
	}
}

func post(base, token string, data []byte) (int, string, error) {
	req, err := http.NewRequest(http.MethodPost, base+"/api/medical-devices/ingest", bytes.NewReader(data))
	if err != nil {
		return 0, "", err
	}
	req.Header.Set("Authorization", "Bearer "+token)
	req.Header.Set("Content-Type", "text/plain")
	req.Header.Set("Accept", "application/json")

	resp, err := client.Do(req)
	if err != nil {
		return 0, "", err
	}
	defer resp.Body.Close()
	b, _ := io.ReadAll(io.LimitReader(resp.Body, 4096))
	return resp.StatusCode, string(b), nil
}

func moveTo(folder, sub, full, name string) {
	dir := filepath.Join(folder, sub)
	_ = os.MkdirAll(dir, 0o755)
	dst := filepath.Join(dir, name)
	if _, err := os.Stat(dst); err == nil {
		// Namenskollision → Zeitstempel anhängen
		dst = filepath.Join(dir, time.Now().Format("20060102-150405-")+name)
	}
	if err := os.Rename(full, dst); err != nil {
		log.Printf("[%s] Verschieben nach %s fehlgeschlagen: %v", name, sub, err)
	}
}

func oneline(s string) string {
	s = strings.TrimSpace(s)
	if len(s) > 200 {
		s = s[:200] + "…"
	}
	return strings.ReplaceAll(s, "\n", " ")
}
