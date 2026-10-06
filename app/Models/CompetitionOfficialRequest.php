<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Kampfrichter-Abfrage zu einem Wettkampf. Gestartet vom Vorstand, an alle
 * Kampfrichter oder gezielt einzelne Personen.
 */
class CompetitionOfficialRequest extends Model
{
    /**
     * Einsatzwünsche laut DSV-Standard (Element KARIABSCHNITT). Die Kürzel
     * passen damit später direkt in eine Vereinsmeldeliste.
     */
    const POSITIONS = [
        'SCH'  => 'Schiedsrichter*in',
        'ASCH' => 'Assistenz-Schiedsrichter*in',
        'STA'  => 'Starter*in',
        'ZRO'  => 'Zielrichterobmann',
        'ZR'   => 'Zielrichter*in',
        'ZNO'  => 'Zeitnehmerobmann',
        'ZN'   => 'Zeitnehmer*in',
        'RZN'  => 'Reservezeitnehmer*in',
        'SR'   => 'Schwimmrichter*in',
        'WRO'  => 'Wenderichterobmann',
        'WR'   => 'Wenderichter*in',
        'AUS'  => 'Auswerter*in',
        'SP'   => 'Sprecher*in',
        'PKF'  => 'Protokollführer*in',
        'STO'  => 'Startordner*in',
        'SIB'  => 'Sicherheitsbeauftragte*r',
        'SAUF' => 'Streckenaufsicht',
        'VER'  => 'Ordner Versorgungsstelle',
    ];

    protected $fillable = ['competition_id', 'message', 'deadline', 'created_by_id', 'closed_at'];

    protected function casts(): array
    {
        return ['deadline' => 'date', 'closed_at' => 'datetime'];
    }

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public function invitees()
    {
        return $this->hasMany(CompetitionOfficialInvitee::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** Veranstaltungstage (date … date_end) */
    public function days(): array
    {
        $c     = $this->competition;
        $start = Carbon::parse($c->date);
        $end   = Carbon::parse($c->date_end ?? $c->date);
        $days  = [];
        for ($d = $start->copy(); $d->lte($end) && count($days) < 14; $d->addDay()) {
            $days[] = $d->format('Y-m-d');
        }
        return $days;
    }

    public function isOpen(): bool
    {
        return !$this->closed_at && (!$this->deadline || today()->lte($this->deadline))
            && today()->lte(Carbon::parse($this->competition->date_end ?? $this->competition->date));
    }

    /** Darf Abfragen starten und auswerten: Vorstand (Portal- oder Vereinsrolle) und Admin */
    public static function canManage(?User $user): bool
    {
        return $user && ($user->hasRole('admin') || $user->hasAnyRole('vorstand'));
    }
}
