<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompetitionSignupRequest extends Model
{
    protected $fillable = [
        'competition_id', 'status', 'message', 'attachment_path',
        'deadline', 'qualifying_period_start', 'qualifying_period_end',
        'eligible_group_ids', 'eligible_user_ids',
        'created_by_id', 'activated_at', 'closed_at',
        'close_reason', 'closed_by_id', 'close_note',
        'meeting_point', 'meeting_time', 'bus_available', 'bus_seats',
        'offer_overnight', 'offer_dinner',
    ];

    protected function casts(): array
    {
        return [
            'eligible_group_ids' => 'array',
            'eligible_user_ids'  => 'array',
            'deadline'                => 'date',
            'qualifying_period_start' => 'date',
            'qualifying_period_end'   => 'date',
            'activated_at'            => 'datetime',
            'closed_at'          => 'datetime',
            'bus_available'      => 'boolean',
            'bus_seats'          => 'integer',
            'offer_overnight'    => 'boolean',
            'offer_dinner'       => 'boolean',
        ];
    }

    public function busBookedCount(): int
    {
        return $this->responses()->where('bus_booked', true)->count();
    }

    public function busSeatsRemaining(): int
    {
        return max(0, ($this->bus_seats ?? 8) - $this->busBookedCount());
    }

    public const CLOSE_MANUAL            = 'manual';
    public const CLOSE_COMPETITION_ENDED = 'competition_ended';

    /**
     * Schliesst alle aktiven Abfragen, deren Wettkampf vorbei ist (letzter
     * Wettkampftag vor heute). Laeuft taeglich; am Wettkampftag selbst bleibt
     * die Abfrage offen (Treffpunkt, Bus). Jede Abfrage bekommt einen
     * Vermerk, warum und wann sie geschlossen wurde.
     *
     * @return int Anzahl geschlossener Abfragen
     */
    public static function closeAfterCompetitionEnd(): int
    {
        $expired = static::where('status', 'active')
            ->whereHas('competition', fn($q) => $q
                ->whereRaw('COALESCE(date_end, date) < ?', [today()->toDateString()]))
            ->with('competition')
            ->get();

        foreach ($expired as $request) {
            $end = $request->competition->date_end ?? $request->competition->date;

            $request->update([
                'status'       => 'closed',
                'closed_at'    => now(),
                'close_reason' => self::CLOSE_COMPETITION_ENDED,
                'closed_by_id' => null,
                'close_note'   => sprintf(
                    'Automatisch geschlossen am %s, da der Wettkampf am %s beendet war. Stand der Antworten: %s.',
                    now()->deBerlin('d.m.Y H:i'),
                    $end->format('d.m.Y'),
                    $request->responseSummary()
                ),
            ]);
        }

        return $expired->count();
    }

    /** Von Hand geschlossen - mit Person und Antwortstand */
    public function closeManually(User $by): void
    {
        $this->update([
            'status'       => 'closed',
            'closed_at'    => now(),
            'close_reason' => self::CLOSE_MANUAL,
            'closed_by_id' => $by->id,
            'close_note'   => sprintf(
                'Von %s am %s geschlossen. Stand der Antworten: %s.',
                $by->name, now()->deBerlin('d.m.Y H:i'), $this->responseSummary()
            ),
        ]);
    }

    /** "5 Zusagen, 2 Absagen, 3 ohne Antwort" */
    public function responseSummary(): string
    {
        $counts = $this->responses()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return sprintf('%d Zusagen, %d Absagen, %d ohne Antwort',
            $counts['attending'] ?? 0, $counts['not_attending'] ?? 0, $counts['pending'] ?? 0);
    }

    public function competition() { return $this->belongsTo(Competition::class); }
    public function createdBy()   { return $this->belongsTo(User::class, 'created_by_id'); }
    public function closedBy()    { return $this->belongsTo(User::class, 'closed_by_id'); }
    public function responses()   { return $this->hasMany(CompetitionSignupResponse::class); }

    public function isDraft(): bool  { return $this->status === 'draft'; }
    public function isActive(): bool { return $this->status === 'active'; }
    public function isClosed(): bool { return $this->status === 'closed'; }

    // All users eligible to respond (from groups + individual users)
    public function eligibleUsers(): \Illuminate\Support\Collection
    {
        $userIds = collect();

        if (!empty($this->eligible_group_ids)) {
            $groupUserIds = User::whereHas('trainingGroups', fn($q) =>
                $q->whereIn('training_groups.id', $this->eligible_group_ids)
            )->where('active', true)->pluck('id');
            $userIds = $userIds->merge($groupUserIds);
        }

        if (!empty($this->eligible_user_ids)) {
            $userIds = $userIds->merge($this->eligible_user_ids);
        }

        return User::whereIn('id', $userIds->unique())->orderBy('lastname')->orderBy('firstname')->get();
    }
}
