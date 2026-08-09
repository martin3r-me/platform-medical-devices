<?php

namespace Platform\MedicalDevices\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * Ein medizinisches Messgerät der Praxis (Audiometer, Sehtest, Blutdruck …).
 * Authentifiziert sich über einen (gehashten) Token am GDT-Eingang.
 */
class MedicalDevice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'team_id', 'name', 'kind', 'manufacturer', 'model',
        'definition_code', 'token_hash', 'token_prefix', 'status',
        'last_seen_at', 'created_by_user_id',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (MedicalDevice $d) {
            $d->uuid ??= Uuid::uuid7()->toString();
            $d->status ??= 'active';
        });
    }

    public function readings()
    {
        return $this->hasMany(DeviceReading::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Erzeugt einen frischen Geräte-Token, speichert nur Hash + Prefix und gibt den
     * Klartext EINMALIG zurück (zum Hinterlegen im Windows-Agent je Ordner).
     */
    public function issueToken(): string
    {
        $plain  = 'mdev_' . bin2hex(random_bytes(24));
        $this->token_hash   = hash('sha256', $plain);
        $this->token_prefix = Str::substr($plain, 0, 12);
        $this->save();

        return $plain;
    }

    public static function resolveByToken(?string $plain): ?self
    {
        if ($plain === null || $plain === '') {
            return null;
        }
        return static::where('token_hash', hash('sha256', $plain))
            ->where('status', 'active')
            ->first();
    }
}
