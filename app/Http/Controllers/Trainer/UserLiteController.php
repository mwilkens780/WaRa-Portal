<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\TrainingGroup;
use App\Models\User;
use App\Services\WebClubImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Benutzerverwaltung fuer Trainer (und Vorstand).
 *
 * Was ein Trainer hier darf, ist bewusst eng gefasst:
 *
 *  - die Mitglieder der eigenen Gruppen sehen, mit Stammdaten, Kontakt,
 *    Adresse und Notizen - das sind die Daten, die im Trainingsalltag
 *    gebraucht werden,
 *  - neue Konten anlegen und diese selbst angelegten Konten auch weiter
 *    bearbeiten - wer einen Tippfehler gemacht hat, soll ihn korrigieren
 *    koennen, ohne dafuer jemanden zu fragen,
 *  - ein bestehendes Konto einer eigenen Gruppe zuordnen.
 *
 * Nicht dazu gehoert das Bearbeiten fremder Konten und das Loeschen, auch
 * nicht der selbst angelegten. Wer Daten eines Mitglieds aendern muss, das er
 * nicht selbst angelegt hat, wendet sich an die Geschaeftsstelle - so bleibt an
 * einer Stelle nachvollziehbar, wo Mitgliederdaten gepflegt werden. Vorstand
 * und Administratoren behalten die Bearbeitung des ganzen Bestands.
 */
class UserLiteController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();

        // Vereinsweite Suche: nur mit Suchbegriff, und nur um jemanden einer
        // Gruppe zuzuordnen. Ohne Begriff bleibt es beim eigenen Bereich - die
        // Liste ist kein Mitgliederverzeichnis zum Durchblaettern.
        $vereinsweit = $request->input('scope') === 'all' && $request->filled('search');

        $query = $vereinsweit ? User::query() : $this->scopedQuery($me);

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $term = '%' . $request->search . '%';
                $q->where('firstname', 'like', $term)
                  ->orWhere('lastname', 'like', $term)
                  ->orWhere('email', 'like', $term);
            });
        }
        if ($request->filled('active')) {
            $query->where('active', $request->active === '1');
        }

        $users = $query->with('trainingGroups:id,name')
            ->orderBy('lastname')->orderBy('firstname')
            ->paginate(25)->withQueryString();

        return view('trainer.users.index', [
            'users'         => $users,
            'meineIds'      => $this->scopedIds($me, $users->getCollection()),
            'gruppen'       => $this->assignableGroups($me),
            'vereinsweit'   => $vereinsweit,
            'darfEditieren' => $this->mayEditAll($me),
            'meineId'       => $me->id,
        ]);
    }

    public function create(Request $request)
    {
        return view('trainer.users.create', [
            'gruppen' => $this->assignableGroups($request->user()),
        ]);
    }

    public function store(Request $request)
    {
        $gruppen = $this->assignableGroups($request->user());

        $data = $request->validate([
            'firstname'  => ['required', 'string', 'max:100'],
            'lastname'   => ['required', 'string', 'max:100'],
            'email'      => ['nullable', 'email', 'unique:users'],
            'role'       => ['required', 'in:' . implode(',', User::ROLES)],
            'birth_date' => ['nullable', 'date'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'group_id'   => ['nullable', 'integer', 'in:' . $gruppen->pluck('id')->join(',')],
        ], [
            'group_id.in' => 'Diese Gruppe gehört nicht zu deinen Gruppen.',
        ]);

        $gruppenId = $data['group_id'] ?? null;
        unset($data['group_id']);

        $plain = WebClubImportService::generateInitialPassword();

        $data['name']             = trim($data['firstname'] . ' ' . $data['lastname']);
        $data['password']         = Hash::make($plain);
        $data['initial_password'] = $plain;
        $data['active']           = true;
        $data['created_by']       = $request->user()->id;

        $user = User::create($data);

        if ($gruppenId) {
            $user->trainingGroups()->syncWithoutDetaching([$gruppenId]);
        }

        return redirect()->route('users-lite.show', $user)->with('success',
            "Benutzer \"{$user->name}\" angelegt. Den Portal-Zugang gibt ein Administrator frei - "
            . 'er verschickt die Willkommensmail mit dem Einrichtungslink.');
    }

    /** Karteikarte: Stammdaten, Kontakt, Adresse, Notizen - nur zum Lesen. */
    public function show(Request $request, User $user)
    {
        $this->authorizeView($request->user(), $user);

        $user->load(['trainingGroups:id,name,color', 'parents', 'children']);

        $offeneGruppen = $this->assignableGroups($request->user())
            ->reject(fn($g) => $user->trainingGroups->contains('id', $g->id))
            ->values();

        return view('trainer.users.show', [
            'user'          => $user,
            'offeneGruppen' => $offeneGruppen,
            'darfEditieren' => $this->mayEdit($request->user(), $user),
        ]);
    }

    /** Ein bestehendes Konto einer eigenen Gruppe zuordnen. */
    public function assignGroup(Request $request, User $user)
    {
        $gruppen = $this->assignableGroups($request->user());

        $data = $request->validate([
            'group_id' => ['required', 'integer', 'in:' . $gruppen->pluck('id')->join(',')],
        ], [
            'group_id.in' => 'Diese Gruppe gehört nicht zu deinen Gruppen.',
        ]);

        $gruppe = $gruppen->firstWhere('id', (int) $data['group_id']);
        $user->trainingGroups()->syncWithoutDetaching([$gruppe->id]);

        return redirect()->route('users-lite.show', $user)
            ->with('success', "\"{$user->name}\" gehört jetzt zur Gruppe \"{$gruppe->name}\".");
    }

    // ── Bearbeiten: selbst angelegte Konten, Vorstand und Admins alle ────────

    public function edit(Request $request, User $user)
    {
        $this->authorizeEdit($request->user(), $user);

        return view('trainer.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeEdit($request->user(), $user);

        $data = $request->validate([
            'firstname'  => ['required', 'string', 'max:100'],
            'lastname'   => ['required', 'string', 'max:100'],
            'email'      => ['nullable', 'email', 'unique:users,email,' . $user->id],
            'role'       => ['required', 'in:' . implode(',', User::ROLES)],
            'birth_date' => ['nullable', 'date'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'mobile'     => ['nullable', 'string', 'max:30'],
            'active'     => ['boolean'],
        ]);

        $data['name']   = trim($data['firstname'] . ' ' . $data['lastname']);
        $data['active'] = $request->has('active') ? $request->boolean('active') : $user->active;

        // Bewusst kein Passwort: Hier wird keines fuer jemand anderen
        // festgelegt. Selbst wenn ein veraltetes Formular noch Felder
        // mitschickt, werden sie hier nicht ausgewertet.

        $user->update($data);

        return redirect()->route('users-lite.show', $user)
            ->with('success', "Benutzer \"{$user->name}\" aktualisiert.");
    }

    // ── Hilfen ───────────────────────────────────────────────────────────────

    private function mayEditAll(User $me): bool
    {
        return in_array($me->role, ['admin', 'vorstand'], true);
    }

    /**
     * Darf dieser Benutzer jenes Konto bearbeiten? Vorstand und
     * Administratoren jedes, ein Trainer die, die er selbst angelegt hat.
     */
    private function mayEdit(User $me, User $user): bool
    {
        return $this->mayEditAll($me) || (int) $user->created_by === $me->id;
    }

    /**
     * Wen darf dieser Trainer sehen? Die Sportler seiner Gruppen, die
     * Trainerkollegen derselben Gruppen, die Eltern dieser Sportler - und die
     * Konten, die er selbst angelegt hat, damit sie ihm nicht in demselben
     * Moment aus der Liste fallen, in dem sie entstehen.
     */
    private function scopedQuery(User $me): Builder
    {
        if ($this->mayEditAll($me)) {
            return User::query();
        }

        $gruppenIds = $me->trainerGroups()->pluck('training_groups.id');

        return User::query()->where(function ($q) use ($gruppenIds, $me) {
            $q->where('id', $me->id)->orWhere('created_by', $me->id);

            if ($gruppenIds->isNotEmpty()) {
                $q->orWhereHas('trainingGroups', fn($g) => $g->whereIn('training_groups.id', $gruppenIds))
                  ->orWhereHas('trainerGroups',  fn($g) => $g->whereIn('training_groups.id', $gruppenIds))
                  ->orWhereHas('children', fn($c) => $c->whereHas('trainingGroups',
                      fn($g) => $g->whereIn('training_groups.id', $gruppenIds)));
            }
        });
    }

    /** Welche der angezeigten Konten liegen im eigenen Bereich? */
    private function scopedIds(User $me, Collection|\Illuminate\Database\Eloquent\Collection $users): Collection
    {
        if ($users->isEmpty()) {
            return collect();
        }

        return $this->scopedQuery($me)->whereIn('id', $users->pluck('id'))->pluck('id');
    }

    private function assignableGroups(User $me): Collection
    {
        $query = TrainingGroup::query()->orderBy('name');

        if (!$this->mayEditAll($me)) {
            $query->whereHas('trainers', fn($q) => $q->where('users.id', $me->id));
        }

        return $query->get(['id', 'name', 'color']);
    }

    private function authorizeView(User $me, User $user): void
    {
        if (!$this->scopedQuery($me)->whereKey($user->id)->exists()) {
            abort(403, 'Dieses Mitglied gehört nicht zu deinen Gruppen.');
        }
    }

    private function authorizeEdit(User $me, User $user): void
    {
        if (!$this->mayEdit($me, $user)) {
            abort(403, 'Dieses Konto pflegt die Geschäftsstelle – ändern lassen sich nur '
                     . 'die Konten, die du selbst angelegt hast.');
        }
    }
}
