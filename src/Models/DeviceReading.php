<?php

namespace Platform\MedicalDevices\Models;

use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

/**
 * Eine eingegangene Messung im Eingang. Hält TRANSIENT PII (raw/patient_name/patient_number),
 * bis der Arzt bestätigt und sie gestrippt an den Core geht.
 */
class DeviceReading extends Model
{
    public const S_RECEIVED  = 'received';
    public const S_MATCHED   = 'matched';
    public const S_UNMATCHED = 'unmatched';
    public const S_FORWARDED = 'forwarded';
    public const S_REJECTED  = 'rejected';

    protected $fillable = [
        'uuid', 'team_id', 'medical_device_id', 'status', 'content_hash',
        'raw', 'patient_name', 'patient_number',
        'matched_patient_id', 'subject_uuid', 'parsed', 'measured_at',
        'observation_uuid', 'forwarded_at', 'note',
    ];

    protected $casts = [
        'raw'          => 'encrypted',
        'patient_name' => 'encrypted',
        'parsed'       => 'array',
        'measured_at'  => 'datetime',
        'forwarded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DeviceReading $r) {
            $r->uuid ??= Uuid::uuid7()->toString();
            $r->status ??= self::S_RECEIVED;
        });
    }

    public function device()
    {
        return $this->belongsTo(MedicalDevice::class, 'medical_device_id');
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::S_RECEIVED, self::S_MATCHED, self::S_UNMATCHED], true);
    }

    public function isForwarded(): bool
    {
        return $this->status === self::S_FORWARDED;
    }

    /** Nach erfolgreichem Forward: PII strippen, nur Referenz bleibt (Single-Storage). */
    public function stripPii(): void
    {
        $this->raw            = null;
        $this->patient_name   = null;
        $this->patient_number = null;
        $this->save();
    }
}
