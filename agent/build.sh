#!/usr/bin/env bash
# Cross-Compile: ein statisches Binary je Plattform. Kein Runtime auf dem Zielrechner nötig.
set -euo pipefail
cd "$(dirname "$0")"
mkdir -p dist

builds=(
  "windows amd64 dist/sovra-agent-windows-amd64.exe"
  "windows arm64 dist/sovra-agent-windows-arm64.exe"
  "darwin  amd64 dist/sovra-agent-macos-intel"
  "darwin  arm64 dist/sovra-agent-macos-apple"
  "linux   amd64 dist/sovra-agent-linux-amd64"
)

for b in "${builds[@]}"; do
  read -r goos goarch out <<< "$b"
  echo "→ $out"
  CGO_ENABLED=0 GOOS="$goos" GOARCH="$goarch" go build -trimpath -ldflags "-s -w" -o "$out" .
done

echo "Fertig:"
ls -lh dist/
