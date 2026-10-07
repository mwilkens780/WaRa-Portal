<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Qualifikation eines Kampfrichters (z. B. Schiedsrichter, Starter) mit
 * Erwerb, Lizenznummer und Ablaufdatum.
 *
 * Die Hauptlizenz (is_primary) ist synchron mit users.kampfrichter_license_*:
 * Änderungen hier landen im Benutzer-Stamm, Änderungen dort (Admin-Formular,
 * WebClub-Abgleich) landen hier – siehe syncToUser() und User::booted().
 */
class OfficialQualification extends Model
{
    /** Vorschläge für die Bezeichnung (frei änderbar) */
    const SUGGESTIONS = [
        'Kampfrichter-Lizenz', 'Wettkampfrichter*in', 'Schiedsrichter*in', 'Starter*in', 'Zielrichter*in',
        'Zeitnehmer*in', 'Schwimmrichter*in', 'Wenderichter*in', 'Auswerter*in', 'Sprecher*in', 'Protokollführer*in',
    ];

    protected $fillable = ['user_id', 'title', 'acquired_on', 'license_nr', 'valid_until', 'is_primary'];

    protected function casts(): array
    {
        return ['acquired_on' => 'date', 'valid_until' => 'date', 'is_primary' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(function (self $q) {
            if ($q->is_primary) {
                // Nur eine Hauptlizenz je Person
                static::where('user_id', $q->user_id)->where('id', '!=', $q->id)->where('is_primary', true)->update(['is_primary' => false]);
                $q->syncToUser();
            }
        });
        static::deleted(function (self $q) {
            if ($q->is_primary) {
                User::whereKey($q->user_id)->first()?->forceFill([
                    'kampfrichter_license_nr' => null, 'kampfrichter_license_issued' => null, 'kampfrichter_license_valid_until' => null,
                ])->saveQuietly();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Hauptlizenz in den Benutzer-Stamm schreiben (ohne User-Ereignisse, sonst Schleife) */
    public function syncToUser(): void
    {
        $this->user?->forceFill([
            'kampfrichter_license_nr'          => $this->license_nr,
            'kampfrichter_license_issued'      => $this->acquired_on,
            'kampfrichter_license_valid_until' => $this->valid_until,
        ])->saveQuietly();
    }

    /** Läuft in den nächsten Monaten ab (oder ist abgelaufen) */
    public function expiresWithin(int $months): bool
    {
        return $this->valid_until && $this->valid_until->lte(today()->addMonths($months));
    }
}
