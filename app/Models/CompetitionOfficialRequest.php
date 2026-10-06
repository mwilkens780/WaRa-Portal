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

    /**
     * Kampfrichtergruppen der KARIMELDUNG (DSV-Standard). Die Gruppe wird je Person
     * aus der ersten Position vorgeschlagen und ist vom Obmann änderbar.
     */
    const KARI_GROUPS = [
        'WKR' => 'Wettkampfrichter*in',
        'SCH' => 'Schiedsrichter*in',
        'AUS' => 'Auswerter*in',
        'SPR' => 'Sprecher*in',
    ];

    protected $fillable = ['competition_id', 'message', 'deadline', 'created_by_id', 'closed_at', 'finalized_at', 'finalized_by_id'];

    protected function casts(): array
    {
        return ['deadline' => 'date', 'closed_at' => 'datetime', 'finalized_at' => 'datetime'];
    }

    public function finalizer()
    {
        return $this->belongsTo(User::class, 'finalized_by_id');
    }

    /** Alle Angefragten haben geantwortet */
    public function allResponded(): bool
    {
        return $this->invitees->isNotEmpty() && $this->invitees->every(fn($i) => $i->responded_at !== null);
    }

    /** Melden erst, wenn alle geantwortet haben oder die Abfrage geschlossen ist */
    public function readyToAssign(): bool
    {
        return $this->allResponded() || $this->closed_at !== null;
    }

    /**
     * Abschnitte je Veranstaltungstag: aus der Ausschreibung bzw. der
     * Wettkampffolge, sonst ein Abschnitt je Tag.
     *
     * @return array<string, list<int>>  Y-m-d => Abschnittsnummern
     */
    public function sessionsByDay(): array
    {
        $days = $this->days();
        $out  = array_fill_keys($days, []);
        foreach (\App\Services\Competition\DsvWriter::abschnitte($this->competition->loadMissing('events')) as $a) {
            $d = $a['date'] ? Carbon::createFromFormat('d.m.Y', $a['date'])?->format('Y-m-d') : null;
            $out[isset($out[$d]) ? $d : $days[0]][] = $a['nr'];
        }
        foreach ($days as $i => $d) {
            if (empty($out[$d]) && !collect($out)->flatten()->contains($i + 1)) $out[$d] = [$i + 1];
        }
        return $out;
    }

    /** Tag eines Abschnitts */
    public function dayOfSession(int $nr): ?string
    {
        foreach ($this->sessionsByDay() as $day => $nrs) {
            if (in_array($nr, $nrs, true)) return $day;
        }
        return null;
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
