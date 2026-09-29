<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, Auditable;

    protected array $auditHidden = ['password', 'remember_token', 'initial_password'];

    const ROLES = ['admin', 'trainer', 'schwimmer', 'elternteil', 'kampfrichter', 'vorstand', 'ernaehrungsberater', 'teamarzt'];

    const ROLE_LABELS = [
        'admin'               => 'Administrator',
        'trainer'             => 'Trainer',
        'schwimmer'           => 'Schwimmer',
        'elternteil'          => 'Elternteil',
        'kampfrichter'        => 'Kampfrichter',
        'vorstand'            => 'Vorstand',
        'ernaehrungsberater'  => 'Ernährungsberater',
        'teamarzt'            => 'Teamarzt',
    ];

    protected $fillable = [
        'name', 'firstname', 'lastname', 'email', 'email2', 'password', 'role',
        'birth_date', 'phone', 'mobile', 'active', 'portal_active', 'created_by',
        'gender', 'dsv_id', 'membership_number', 'webclub_person_id', 'member_since', 'resigned_at', 'training_group',
        'street', 'postal_code', 'city', 'country',
        'initial_password', 'mail_preferences',
        'trainer_license_nr', 'trainer_license_valid_until',
        'rescue_certificate_until', 'first_aid_until',
        'police_clearance_date',
        'kampfrichter_license_nr', 'kampfrichter_license_issued', 'kampfrichter_license_valid_until',
        'notes',
        'opt_nutrition', 'opt_sports_medicine',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'           => 'datetime',
            'password'                    => 'hashed',
            'birth_date'                  => 'date',
            'member_since'                => 'date',
            'resigned_at'                 => 'date',
            'active'                      => 'boolean',
            'trainer_license_valid_until' => 'date',
            'rescue_certificate_until'    => 'date',
            'first_aid_until'             => 'date',
            'police_clearance_date'            => 'date',
            'kampfrichter_license_issued'      => 'date',
            'kampfrichter_license_valid_until'  => 'date',
            'opt_nutrition'                     => 'boolean',
            'opt_sports_medicine'               => 'boolean',
            'mail_preferences'                  => 'array',
            'last_login_at'                     => 'datetime',
            'portal_active'                     => 'boolean',
            'portal_activated_at'               => 'datetime',
        ];
    }

    /** Hat sich dieser Zugang jemals angemeldet? */
    public function hasLoggedIn(): bool
    {
        return $this->last_login_at !== null;
    }

    /**
     * Ist der Portal-Zugang fertig eingerichtet? Das ist er erst, wenn die
     * Person auf die Willkommensmail reagiert hat: eigenes Passwort gesetzt
     * und angemeldet. Vorher ist das Konto nur angelegt.
     */
    public function isPortalActivated(): bool
    {
        return $this->portal_activated_at !== null;
    }

    /**
     * Aktivierung nachtragen, sobald beide Bedingungen erfuellt sind. Wird
     * nach der Anmeldung und nach jeder Passwortaenderung aufgerufen - je
     * nachdem, was zuletzt passiert, greift der eine oder der andere Aufruf.
     *
     * saveQuietly: Das ist kein Bearbeitungsvorgang, der ins Protokoll gehoert.
     */
    public function maybeMarkPortalActivated(): bool
    {
        if ($this->isPortalActivated() || $this->hasInitialPassword() || !$this->hasLoggedIn()) {
            return false;
        }

        try {
            $this->forceFill(['portal_activated_at' => now()])->saveQuietly();
        } catch (\Throwable) {
            // Waehrend einer Auslieferung ist der Code einen Moment vor der
            // Migration auf dem Server. Ein fehlender Vermerk darf keine
            // Anmeldung zerlegen - beim naechsten Mal steht die Spalte da.
            return false;
        }

        return true;
    }

    /**
     * Zustand des Portal-Zugangs in einem Wort - fuer Listen und Formulare.
     * Absichtlich unabhaengig von der Vereinsmitgliedschaft.
     */
    public function portalStatus(): array
    {
        if (!($this->portal_active ?? true)) {
            return ['key' => 'off', 'label' => 'Portal gesperrt', 'tone' => 'bg-red-100 text-red-700'];
        }
        if ($this->isPortalActivated()) {
            return ['key' => 'active', 'label' => 'Portal aktiv', 'tone' => 'bg-green-100 text-green-700'];
        }
        if (!$this->email) {
            return ['key' => 'no-mail', 'label' => 'Keine E-Mail', 'tone' => 'bg-gray-100 text-gray-500'];
        }
        return ['key' => 'pending', 'label' => 'Noch nicht aktiviert', 'tone' => 'bg-amber-100 text-amber-700'];
    }

    /** Will dieser Benutzer Mails zu diesem Thema? Siehe App\Support\MailTopic. */
    public function wantsMail(string $topic): bool
    {
        return \App\Support\MailTopic::wants($this, $topic);
    }

    // When firstname/lastname are present, derive display name from them
    public function getNameAttribute($value): string
    {
        $first = $this->attributes['firstname'] ?? '';
        $last  = $this->attributes['lastname'] ?? '';
        if ($first !== '' || $last !== '') {
            return trim("$first $last");
        }
        return $value ?? '';
    }

    // Role checks — primary role
    public function isAdmin(): bool               { return $this->role === 'admin'; }
    public function isTrainer(): bool             { return $this->role === 'trainer'; }
    public function isSchwimmer(): bool           { return $this->role === 'schwimmer'; }
    public function isElternteil(): bool          { return $this->role === 'elternteil'; }
    public function isKampfrichter(): bool        { return $this->role === 'kampfrichter'; }
    public function isVorstand(): bool            { return $this->role === 'vorstand'; }
    public function isErnaehrungsberater(): bool  { return $this->role === 'ernaehrungsberater'; }
    public function isTeamarzt(): bool            { return $this->role === 'teamarzt'; }

    public function hasInitialPassword(): bool
    {
        return !empty($this->attributes['initial_password']);
    }

    /**
     * Check portal access role (used by auth middleware).
     * For checking any club role, use hasAnyRole().
     */
    /**
     * Startseite nach dem Login und fuer "/".
     *
     * Jedes Ziel muss fuer die Rolle freigegeben sein. Frueher landeten
     * Vorstand und Kampfrichter auf fremden Dashboards (403), Ernaehrungs-
     * berater und Teamarzt auf /login - fuer Angemeldete eine Endlosschleife.
     */
    public function homeUrl(): string
    {
        return match ($this->role) {
            'admin'              => route('admin.dashboard'),
            'trainer'            => route('trainer.dashboard'),
            'schwimmer'          => route('swimmer.dashboard'),
            'elternteil'         => route('parent.dashboard'),
            'ernaehrungsberater' => route('nutrition.index'),
            'teamarzt'           => route('teamdoctor.index'),
            default              => MenuPermission::can((string) $this->role, 'calendar')
                                        ? route('calendar.index')
                                        : route('profile.index'),
        };
    }

    public function hasRole(string|array $roles): bool
    {
        return in_array($this->role, (array) $roles);
    }

    /** True if user has this role in user_roles table OR as portal role. */
    public function hasAnyRole(string $role): bool
    {
        if ($this->role === $role) return true;
        return $this->userRoles->contains('role', $role);
    }

    /** All club roles (from user_roles table). */
    public function userRoles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /** Sync club roles: replaces entire set. */
    public function syncRoles(array $roles): void
    {
        $this->userRoles()->whereNotIn('role', $roles)->delete();
        foreach ($roles as $role) {
            $this->userRoles()->firstOrCreate(['role' => $role]);
        }
    }

    // Relations
    public function trainingSessions()
    {
        return $this->hasMany(TrainingSession::class, 'trainer_id');
    }

    public function attendances()
    {
        return $this->hasMany(TrainingAttendance::class);
    }

    public function swimmingTimes()
    {
        return $this->hasMany(SwimmingTime::class);
    }

    public function trainingDiaries()
    {
        return $this->hasMany(TrainingDiary::class);
    }

    public function competitionResults()
    {
        return $this->hasMany(CompetitionResult::class);
    }

    public function trainingGroups()
    {
        return $this->belongsToMany(TrainingGroup::class, 'training_group_swimmer');
    }

    public function trainerGroups()
    {
        return $this->belongsToMany(TrainingGroup::class, 'training_group_trainer');
    }

    public function healthDocuments()
    {
        return $this->hasMany(HealthDocument::class);
    }

    public function uploadedHealthDocuments()
    {
        return $this->hasMany(HealthDocument::class, 'uploaded_by');
    }

    public function individualSessions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(TrainingSession::class, 'training_session_swimmers', 'user_id', 'training_session_id')
            ->whereNotNull('training_session_swimmers.training_session_id');
    }

    public function individualSeries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TrainingSessionSwimmer::class)->whereNotNull('recurrence_group_id');
    }

    public function seriesExclusions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SwimmerSeriesExclusion::class);
    }

    public function sessionRegistrations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TrainingSessionRegistration::class);
    }

    public function children()
    {
        return $this->belongsToMany(User::class, 'parent_swimmer', 'parent_id', 'swimmer_id');
    }

    public function parents()
    {
        return $this->belongsToMany(User::class, 'parent_swimmer', 'swimmer_id', 'parent_id');
    }

    /**
     * Darf dieses Konto fuer das Kind handeln (Gesundheitsdaten)?
     *
     * Eltern sind nur fuer minderjaehrige Kinder gesetzliche Vertreter. Bei
     * Volljaehrigen bleibt die Verknuepfung fuer Training und Wettkampf,
     * Gesundheitsdaten (Art. 9 DSGVO) sieht aber nur das Kind selbst. Ohne
     * Geburtsdatum gilt die vom Admin angelegte Verknuepfung.
     */
    public function isGuardianOf(User $child): bool
    {
        if (!$this->children()->whereKey($child->id)->exists()) return false;
        return $child->age === null || $child->age < 18;
    }

    /**
     * Entscheiden die Eltern ueber die Gesundheits-Einwilligungen?
     *
     * Ja bei Minderjaehrigen (bzw. ohne Geburtsdatum) mit mindestens einem
     * verknuepften, aktiven Elternteil. Ohne Eltern im Portal entscheidet
     * das Kind selbst - sonst koennte niemand einwilligen.
     */
    public function consentManagedByParents(): bool
    {
        return ($this->age === null || $this->age < 18)
            && $this->parents()->where('active', true)->exists();
    }

    /** Kinder, fuer die dieses Konto gesetzlicher Vertreter ist */
    public function wards(): \Illuminate\Support\Collection
    {
        return $this->children()->where('active', true)->orderBy('firstname')->get()
            ->filter(fn(User $child) => $child->age === null || $child->age < 18)
            ->values();
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? $this->birth_date->age : null;
    }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->role;
    }
}
