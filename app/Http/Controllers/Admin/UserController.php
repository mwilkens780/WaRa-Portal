<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AccountWelcomeMail;
use App\Mail\PasswordResetMail;
use App\Models\MailMessage;
use App\Models\Setting;
use App\Models\User;
use App\Services\Mailer;
use App\Services\WebClubImportService;
use App\Support\MailTopic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $term = '%' . $request->search . '%';
                $q->where('firstname', 'like', $term)
                  ->orWhere('lastname', 'like', $term)
                  ->orWhere('name', 'like', $term)
                  ->orWhere('email', 'like', $term);
            });
        }
        if ($request->filled('active')) {
            $query->where('active', $request->active === '1');
        }

        $users = $query->with('userRoles')->orderBy('lastname')->orderBy('firstname')->paginate(20)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $swimmers = User::where('role', 'schwimmer')->orderBy('lastname')->orderBy('firstname')->get();
        return view('admin.users.create', compact('swimmers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'firstname'    => ['required', 'string', 'max:100'],
            'lastname'     => ['required', 'string', 'max:100'],
            'email'        => ['nullable', 'email', 'unique:users'],
            'user_roles'   => ['nullable', 'array'],
            'user_roles.*' => ['in:' . implode(',', User::ROLES)],
            'birth_date'   => ['nullable', 'date'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'active'       => ['boolean'],
            'children'     => ['nullable', 'array'],
            'children.*'   => ['exists:users,id'],
        ]);

        $roles = $data['user_roles'] ?? [];

        // Primary role = highest-priority role in selection (order from User::ROLES)
        $primaryRole = collect(User::ROLES)->first(fn($r) => in_array($r, $roles)) ?? 'schwimmer';

        $plain = WebClubImportService::generateInitialPassword();

        $data['name']             = trim($data['firstname'] . ' ' . $data['lastname']);
        $data['role']             = $primaryRole;
        $data['password']         = Hash::make($plain);
        $data['initial_password'] = $plain;
        $data['active']           = $request->boolean('active', true);
        unset($data['user_roles'], $data['children']);

        $user = User::create($data);
        if ($roles) {
            $user->syncRoles($roles);
        }

        if (in_array('elternteil', $roles) && !empty($request->children)) {
            $user->children()->sync($request->children);
        }

        // Willkommensmail: nur an aktive Konten mit Adresse. Das Initialpasswort
        // bleibt in der Benutzerverwaltung sichtbar - fuer die persoenliche
        // Uebergabe, wenn jemand keine Mail bekommt oder sie nicht findet.
        $mailHint = $this->sendWelcome($user);

        return redirect()->route('admin.users.edit', $user)
            ->with('success', "Benutzer \"{$user->name}\" angelegt – Initialpasswort ist unten sichtbar. {$mailHint}");
    }

    public function edit(User $user)
    {
        $user->load('userRoles');
        $swimmers         = User::where('role', 'schwimmer')->orderBy('lastname')->orderBy('firstname')->get();
        $assignedChildren = $user->children()->pluck('users.id')->toArray();
        $initialPassword  = $user->getRawOriginal('initial_password');
        return view('admin.users.edit', compact('user', 'swimmers', 'assignedChildren', 'initialPassword'));
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'firstname'    => ['required', 'string', 'max:100'],
            'lastname'     => ['required', 'string', 'max:100'],
            'email'        => ['nullable', 'email', 'unique:users,email,' . $user->id],
            'role'         => ['nullable', 'in:' . implode(',', User::ROLES)],
            'user_roles'   => ['nullable', 'array'],
            'user_roles.*' => ['in:' . implode(',', User::ROLES)],
            'birth_date'   => ['nullable', 'date'],
            'gender'       => ['nullable', 'in:M,F'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'mobile'       => ['nullable', 'string', 'max:30'],
            'email2'       => ['nullable', 'email'],
            'dsv_id'       => ['nullable', 'string', 'max:20', 'unique:users,dsv_id,' . $user->id],
            'membership_number' => ['nullable', 'string', 'max:30'],
            'member_since' => ['nullable', 'date'],
            'street'       => ['nullable', 'string', 'max:255'],
            'postal_code'  => ['nullable', 'string', 'max:10'],
            'city'         => ['nullable', 'string', 'max:100'],
            'country'      => ['nullable', 'string', 'max:100'],
            'notes'        => ['nullable', 'string'],
            'active'       => ['boolean'],
            'trainer_license_nr'              => ['nullable', 'string', 'max:50'],
            'trainer_license_valid_until'     => ['nullable', 'date'],
            'rescue_certificate_until'        => ['nullable', 'date'],
            'first_aid_until'                 => ['nullable', 'date'],
            'police_clearance_date'           => ['nullable', 'date'],
            'kampfrichter_license_nr'         => ['nullable', 'string', 'max:50'],
            'kampfrichter_license_issued'     => ['nullable', 'date'],
            'kampfrichter_license_valid_until' => ['nullable', 'date'],
            'children'     => ['nullable', 'array'],
            'children.*'   => ['exists:users,id'],
        ]);

        $data['name']   = trim($data['firstname'] . ' ' . $data['lastname']);
        $roles = $data['user_roles'] ?? [];
        unset($data['user_roles'], $data['children']);

        if ($request->filled('password')) {
            $request->validate([
                'password' => ['confirmed', Password::min(8)->letters()->numbers()],
            ]);
            $data['password']         = Hash::make($request->password);
            $data['initial_password'] = null;
        }

        $data['active'] = $request->has('active') ? $request->boolean('active') : $user->active;
        $user->update($data);
        $user->syncRoles($roles);

        if (($data['role'] ?? '') === 'elternteil' || in_array('elternteil', $roles)) {
            $user->children()->sync($request->children ?? []);
        }

        return redirect()->route('admin.users.index')
            ->with('success', "Benutzer \"{$user->name}\" wurde aktualisiert.");
    }

    /**
     * Passwort zuruecksetzen.
     *
     * Beides zusammen: ein neues Initialpasswort fuer die persoenliche
     * Uebergabe, und eine Mail mit Einmallink, damit sich die Person selbst
     * eines setzen kann, ohne dass ein Passwort durch Postfaecher wandert.
     */
    public function resetPassword(User $user, Mailer $mailer)
    {
        $plain = WebClubImportService::generateInitialPassword();

        $user->update([
            'password'         => Hash::make($plain),
            'initial_password' => $plain,
        ]);

        $mail = new PasswordResetMail($user, byAdmin: true, byName: auth()->user()?->name);
        $log  = $mailer->send($user, MailTopic::ACCOUNT, $mail, $mail->defaultSubject());

        return back()->with('success',
            "Neues Initialpasswort für \"{$user->name}\" gesetzt: {$plain}. " . $this->mailHint($log));
    }

    /** Willkommensmail (erneut) verschicken - einzeln. */
    public function sendWelcomeMail(User $user, Mailer $mailer)
    {
        if (!$user->email) {
            return back()->with('error', "Für \"{$user->name}\" ist keine E-Mail-Adresse hinterlegt.");
        }
        if (!$user->active) {
            return back()->with('error', "\"{$user->name}\" ist nicht aktiv – erst aktivieren, dann einladen.");
        }

        $mail = new AccountWelcomeMail($user, isResend: true);
        $log  = $mailer->send($user, MailTopic::ACCOUNT, $mail, $mail->defaultSubject());

        return back()->with('success', "Willkommensmail an \"{$user->name}\": " . $this->mailHint($log));
    }

    /**
     * Willkommensmail an den Bestand.
     *
     * Bewusst ueber die Warteschlange: mehrere hundert Mails synchron zu
     * verschicken laeuft in den Zeitueberlauf des Webservers. Der Cron
     * arbeitet sie in Haeppchen ab.
     */
    public function bulkWelcomeForm()
    {
        $candidates = User::where('active', true)
            ->whereNotNull('email')
            ->orderBy('lastname')->orderBy('firstname')
            ->get(['id', 'firstname', 'lastname', 'email', 'role', 'initial_password', 'last_login_at']);

        return view('admin.users.bulk-welcome', [
            'candidates'      => $candidates,
            'neverSet'        => $candidates->filter(fn($u) => !$u->hasLoggedIn()),
            'maintenance'     => Setting::getBool('maintenance_mode'),
            'testAddress'     => Mailer::testAddress(),
            'alreadyInvited'  => MailMessage::where('mailable', AccountWelcomeMail::class)
                ->whereIn('status', ['sent', 'pending'])
                ->pluck('user_id')->filter()->unique(),
        ]);
    }

    public function bulkWelcomeSend(Request $request, Mailer $mailer)
    {
        $data = $request->validate([
            'users'   => ['required', 'array', 'min:1'],
            'users.*' => ['integer', 'exists:users,id'],
        ]);

        // Waehrend der Wartung wuerden alle Mails an die Testadresse gehen -
        // mehrere hundert Stueck. Das ist nie gewollt.
        if (Setting::getBool('maintenance_mode')) {
            return back()->with('error',
                'Der Wartungsmodus ist aktiv. Alle Mails gingen an die Testadresse – '
                . 'für den Massenversand bitte erst den Wartungsmodus ausschalten.');
        }

        $users   = User::whereIn('id', $data['users'])->where('active', true)->whereNotNull('email')->get();
        $queued  = 0;
        $skipped = 0;

        foreach ($users as $user) {
            $mail = new AccountWelcomeMail($user, isResend: true);
            $log  = $mailer->queue($user, MailTopic::ACCOUNT, $mail, $mail->defaultSubject());
            $log->status === 'pending' ? $queued++ : $skipped++;
        }

        $msg = "{$queued} Willkommensmails eingereiht. Der Cron verschickt sie in Blöcken zu "
             . Mailer::BATCH_SIZE . " – der Fortschritt steht im Mail-Protokoll.";
        if ($skipped > 0) {
            $msg .= " {$skipped} übersprungen (keine Adresse oder abbestellt).";
        }

        return redirect()->route('admin.mail-log.index')->with('success', $msg);
    }

    // ── Hilfen ───────────────────────────────────────────────────────────────

    private function sendWelcome(User $user): string
    {
        if (!$user->email || !$user->active) {
            return 'Keine Willkommensmail verschickt (keine Adresse oder Konto nicht aktiv).';
        }

        $mail = new AccountWelcomeMail($user);

        return 'Willkommensmail: ' . $this->mailHint(
            app(Mailer::class)->send($user, MailTopic::ACCOUNT, $mail, $mail->defaultSubject())
        );
    }

    private function mailHint(MailMessage $log): string
    {
        return match ($log->status) {
            'sent'    => $log->wasRedirected()
                            ? "verschickt an {$log->sent_to} (Wartungsmodus)."
                            : "verschickt an {$log->recipient_email}.",
            'skipped' => 'nicht verschickt – ' . $log->error,
            'failed'  => 'fehlgeschlagen – ' . $log->error,
            default   => 'wartet auf Versand.',
        };
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'Du kannst dein eigenes Konto nicht löschen.']);
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', "Benutzer \"{$name}\" wurde gelöscht.");
    }

    public function destroyAll(Request $request)
    {
        $request->validate([
            'confirm_text' => ['required', 'in:ALLE LÖSCHEN'],
        ], [
            'confirm_text.in' => 'Bitte gib "ALLE LÖSCHEN" ein, um zu bestätigen.',
        ]);

        $count = User::where('role', '!=', 'admin')->count();
        User::where('role', '!=', 'admin')->delete();

        return redirect()->route('admin.users.index')
            ->with('success', "{$count} Nicht-Admin-Benutzer wurden gelöscht. Alle Administratoren bleiben erhalten.");
    }

    public function cleanupDsvIds()
    {
        $count = User::whereRaw("dsv_id REGEXP '^0+$'")->count();
        User::whereRaw("dsv_id REGEXP '^0+$'")->update(['dsv_id' => null]);

        return redirect()->route('admin.users.index')
            ->with('success', "DSV-ID bereinigt: {$count} Einträge mit Nullwert (000000 etc.) auf leer gesetzt.");
    }

    public function toggleActive(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'Du kannst dein eigenes Konto nicht deaktivieren.']);
        }

        $user->update(['active' => !$user->active]);
        $status = $user->active ? 'aktiviert' : 'deaktiviert';

        return back()->with('success', "Benutzer \"{$user->name}\" wurde {$status}.");
    }

    public function export(): StreamedResponse
    {
        $users = User::with(['trainingGroups', 'children', 'parents'])
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->get();

        $filename = 'portal-personen-' . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $columns = [
            'Portal-ID', 'Nachname', 'Vorname', 'Geburtsdatum', 'Geschlecht',
            'Rolle', 'Aktiv',
            'E-Mail', 'E-Mail 2', 'Telefon', 'Mobil',
            'Straße', 'PLZ', 'Ort', 'Land',
            'Mitgliedsnummer', 'DSV-ID', 'WebClub-ID', 'Mitglied seit', 'Ausgetreten am',
            'Trainingsgruppen',
            'Eltern (Name / E-Mail)',
            'Kinder (Name / E-Mail)',
            'Trainerlizenz-Nr', 'Trainerlizenz gültig bis', 'Rettungsschwimmer bis', 'Erste Hilfe bis',
            'Kampfrichter-Lizenz-Nr',
            'Notizen',
            'Portal erstellt am',
        ];

        return response()->streamDownload(function () use ($users, $columns) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM so Excel erkennt die Kodierung
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, $columns, ';');

            foreach ($users as $user) {
                $groups = $user->trainingGroups->pluck('name')->join(', ');

                $parents = $user->parents->map(fn($p) => trim("{$p->firstname} {$p->lastname}") . ($p->email ? " <{$p->email}>" : ''))->join(' | ');
                $children = $user->children->map(fn($c) => trim("{$c->firstname} {$c->lastname}") . ($c->email ? " <{$c->email}>" : ''))->join(' | ');

                fputcsv($out, [
                    $user->id,
                    $user->lastname,
                    $user->firstname,
                    $user->birth_date?->format('d.m.Y'),
                    match($user->gender) { 'M' => 'männlich', 'F' => 'weiblich', default => '' },
                    User::ROLE_LABELS[$user->role] ?? $user->role,
                    $user->active ? 'ja' : 'nein',
                    $user->email,
                    $user->email2,
                    $user->phone,
                    $user->mobile,
                    $user->street,
                    $user->postal_code,
                    $user->city,
                    $user->country,
                    $user->membership_number,
                    $user->dsv_id,
                    $user->webclub_person_id,
                    $user->member_since?->format('d.m.Y'),
                    $user->resigned_at?->format('d.m.Y'),
                    $groups,
                    $parents,
                    $children,
                    $user->trainer_license_nr,
                    $user->trainer_license_valid_until?->format('d.m.Y'),
                    $user->rescue_certificate_until?->format('d.m.Y'),
                    $user->first_aid_until?->format('d.m.Y'),
                    $user->kampfrichter_license_nr,
                    $user->notes,
                    $user->created_at?->format('d.m.Y'),
                ], ';');
            }

            fclose($out);
        }, $filename, $headers);
    }
}
