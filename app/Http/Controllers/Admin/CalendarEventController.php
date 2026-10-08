<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\CalendarEventFile;
use App\Models\CalendarEventInvitee;
use App\Models\Season;
use App\Models\TrainingGroup;
use App\Models\User;
use App\Services\EventInvitations;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Kalendertermine: einfache Termine und Termine mit Einladung (Vorstandssitzung,
 * Elternabend, Team-Event) samt Agenda, Anhängen, Protokollen und Anmeldung.
 *
 * Wer was darf, steht am Modell (CalendarEvent::creatableTypesFor, canManage,
 * canSeeDetails) – die Routen verlangen nur einen angemeldeten Benutzer.
 */
class CalendarEventController extends Controller
{
    /** Dateien: Protokolle, Präsentationen, Tabellen, Bilder */
    private const FILE_RULES = ['file', 'max:20480', 'mimes:pdf,doc,docx,odt,xls,xlsx,ods,ppt,pptx,odp,txt,jpg,jpeg,png'];

    public function __construct(private EventInvitations $invitations) {}

    // ── Anlegen und Bearbeiten ───────────────────────────────────────────────

    public function create(Request $request)
    {
        $types = CalendarEvent::creatableTypesFor($request->user());
        abort_if(empty($types), 403);

        return view('calendar.events.create', $this->formData($request->user()) + [
            'types'       => $types,
            'defaultDate' => $request->get('date'),
            'defaultType' => array_key_exists($request->get('type'), $types) ? $request->get('type') : array_key_first($types),
            'targetGroups' => $this->targetGroups(),
        ]);
    }

    public function store(Request $request)
    {
        $types = CalendarEvent::creatableTypesFor($request->user());
        abort_if(empty($types), 403);

        $data = $this->validated($request, array_keys($types));
        $data['created_by'] = auth()->id();
        $event = CalendarEvent::create($data);
        $this->syncTargetGroups($request, $event);

        if ($event->hasInvitations()) {
            $count = $this->inviteFromRequest($request, $event);
            return redirect()->route('calendar.events.show', $event)
                ->with('success', "Termin \"{$event->title}\" angelegt" . ($count ? ", {$count} Einladung" . ($count === 1 ? '' : 'en') . ' verschickt.' : '.'));
        }

        $back = $request->input('return_to', route('calendar.index'));
        return redirect($back)->with('success', "Termin \"{$data['title']}\" angelegt.");
    }

    public function show(Request $request, CalendarEvent $calendarEvent)
    {
        $user  = $request->user();
        $event = $calendarEvent->load(['creator:id,firstname,lastname', 'files.source']);
        $canSee    = $event->canSeeDetails($user);
        $canManage = $event->canManage($user);

        $invitees = $canSee && $event->hasInvitations()
            ? $event->invitees()->with('user:id,firstname,lastname,birth_date')->get()
                ->sortBy(fn($i) => [array_search($i->status, array_keys(CalendarEventInvitee::STATUSES)), $i->display_name])
            : collect();

        // Protokolle früherer Termine derselben Art zum Verknüpfen
        $previousProtocols = $canManage && $event->hasInvitations()
            ? CalendarEventFile::where('category', 'protokoll')->whereNull('source_file_id')
                ->whereHas('event', fn($q) => $q->where('type', $event->type)->where('id', '!=', $event->id)
                    ->where('start_date', '<=', $event->start_date))
                ->with('event:id,title,start_date')->latest()->limit(30)->get()
                ->filter(fn($f) => $f->event->canSeeDetails($user))
            : collect();

        return view('calendar.events.show', [
            'event'             => $event,
            'canSee'            => $canSee,
            'canManage'         => $canManage,
            'myInvitations'     => $event->hasInvitations() ? $event->invitationsFor($user) : collect(),
            'invitees'          => $invitees,
            'previousProtocols' => $previousProtocols,
        ] + ($canManage && $event->hasInvitations() ? $this->formData($user) : []));
    }

    public function edit(Request $request, CalendarEvent $calendarEvent)
    {
        abort_unless($calendarEvent->canManage($request->user()), 403);
        $types = CalendarEvent::creatableTypesFor($request->user()) + [$calendarEvent->type => CalendarEvent::TYPES[$calendarEvent->type]];

        return view('calendar.events.edit', [
            'calendarEvent' => $calendarEvent,
            'seasons'       => Season::orderByDesc('start_date')->get(),
            'types'         => $types,
            'targetGroups'  => $this->targetGroups(),
        ]);
    }

    public function update(Request $request, CalendarEvent $calendarEvent)
    {
        abort_unless($calendarEvent->canManage($request->user()), 403);
        $types = array_keys(CalendarEvent::creatableTypesFor($request->user()) + [$calendarEvent->type => true]);

        $data = $this->validated($request, $types);
        // Termine mit Einladung behalten ihre Art – sonst passten die Eingeladenen nicht mehr
        if ($calendarEvent->hasInvitations()) unset($data['type']);
        $calendarEvent->update($data);
        $this->syncTargetGroups($request, $calendarEvent);

        return $calendarEvent->hasInvitations()
            ? redirect()->route('calendar.events.show', $calendarEvent)->with('success', 'Termin gespeichert.')
            : redirect()->route('calendar.index')->with('success', "Termin \"{$calendarEvent->title}\" gespeichert.");
    }

    public function destroy(Request $request, CalendarEvent $calendarEvent)
    {
        abort_unless($calendarEvent->canManage($request->user()), 403);

        foreach ($calendarEvent->files()->whereNotNull('path')->get() as $file) {
            // Verknüpfungen in späteren Terminen zeigen sonst ins Leere: Datei bleibt, wenn noch genutzt
            if (!CalendarEventFile::where('source_file_id', $file->id)->exists()) {
                Storage::disk('local')->delete($file->path);
            }
        }
        $title = $calendarEvent->title;
        $calendarEvent->delete();

        return redirect()->route('calendar.index')->with('success', "Termin \"{$title}\" gelöscht.");
    }

    // ── Einladungen und Rückmeldungen ───────────────────────────────────────

    public function invite(Request $request, CalendarEvent $calendarEvent)
    {
        abort_unless($calendarEvent->canManage($request->user()) && $calendarEvent->hasInvitations(), 403);
        $count = $this->inviteFromRequest($request, $calendarEvent);

        return back()->with('success', $count ? "{$count} weitere Einladung" . ($count === 1 ? '' : 'en') . ' verschickt.' : 'Alle Ausgewählten waren schon eingeladen.');
    }

    public function remind(Request $request, CalendarEvent $calendarEvent)
    {
        abort_unless($calendarEvent->canManage($request->user()), 403);
        $n = $this->invitations->remind($calendarEvent);

        return back()->with('success', $n ? "Erinnerung an {$n} Empfänger verschickt." : 'Niemand hatte eine offene Rückmeldung (oder hat Erinnerungen abgewählt).');
    }

    public function respond(Request $request, CalendarEvent $calendarEvent, CalendarEventInvitee $invitee)
    {
        abort_unless($invitee->calendar_event_id === $calendarEvent->id, 404);
        abort_unless($calendarEvent->invitationsFor($request->user())->contains('id', $invitee->id), 403);

        $data = $request->validate([
            'status'  => ['required', 'in:zugesagt,abgesagt,vielleicht'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->invitations->respond($invitee->setRelation('event', $calendarEvent), $data['status'], $data['comment'] ?? null, $request->user());
        } catch (\DomainException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 422)
                : back()->withErrors(['status' => $e->getMessage()]);
        }

        $msg = 'Rückmeldung gespeichert: ' . CalendarEventInvitee::STATUSES[$data['status']] . '.';
        return $request->expectsJson()
            ? response()->json(['message' => $msg, 'status' => $data['status']])
            : back()->with('success', $msg);
    }

    public function removeInvitee(Request $request, CalendarEvent $calendarEvent, CalendarEventInvitee $invitee)
    {
        abort_unless($calendarEvent->canManage($request->user()) && $invitee->calendar_event_id === $calendarEvent->id, 403);
        $name = $invitee->display_name;
        $invitee->delete();

        return back()->with('success', "{$name} ist nicht mehr eingeladen.");
    }

    // ── Agenda-Unterlagen, Anhänge, Protokolle ──────────────────────────────

    public function storeFile(Request $request, CalendarEvent $calendarEvent)
    {
        abort_unless($calendarEvent->canManage($request->user()), 403);

        $data = $request->validate([
            'category'       => ['required', 'in:' . implode(',', array_keys(CalendarEventFile::CATEGORIES))],
            'mode'           => ['required', 'in:file,link,previous'],
            'title'          => ['nullable', 'string', 'max:200'],
            'file'           => ['required_if:mode,file', 'nullable', ...self::FILE_RULES],
            'url'            => ['required_if:mode,link', 'nullable', 'url', 'max:1000'],
            'source_file_id' => ['required_if:mode,previous', 'nullable', 'exists:calendar_event_files,id'],
            'notify'         => ['nullable', 'boolean'],
        ], [
            'file.required_if'           => 'Bitte eine Datei auswählen.',
            'url.required_if'            => 'Bitte einen Link angeben.',
            'source_file_id.required_if' => 'Bitte ein früheres Protokoll auswählen.',
        ]);

        $attrs = ['category' => $data['category'], 'created_by_id' => auth()->id()];

        if ($data['mode'] === 'file') {
            $upload = $request->file('file');
            $attrs += [
                'path'          => $upload->store('calendar-events/' . $calendarEvent->id, 'local'),
                'original_name' => $upload->getClientOriginalName(),
                'size'          => $upload->getSize(),
                'title'         => ($data['title'] ?? null) ?: pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME),
            ];
        } elseif ($data['mode'] === 'link') {
            $attrs += ['url' => $data['url'], 'title' => ($data['title'] ?? null) ?: parse_url($data['url'], PHP_URL_HOST)];
        } else {
            $source = CalendarEventFile::with('event')->findOrFail($data['source_file_id']);
            abort_unless($source->event && $source->event->canSeeDetails($request->user()), 403);
            $attrs += [
                'source_file_id' => $source->id,
                'title'          => ($data['title'] ?? null) ?: $source->title . ' (' . $source->event->start_date->format('d.m.Y') . ')',
            ];
        }

        $file = $calendarEvent->files()->create($attrs);

        $msg = 'Hinzugefügt: ' . $file->title . '.';
        if ($file->category === 'protokoll' && $request->boolean('notify')) {
            $n = $this->invitations->protocolAdded($calendarEvent, $file);
            $msg .= " Hinweis an {$n} Empfänger verschickt.";
        }

        return back()->with('success', $msg);
    }

    public function downloadFile(Request $request, CalendarEvent $calendarEvent, CalendarEventFile $file)
    {
        abort_unless($file->calendar_event_id === $calendarEvent->id && $calendarEvent->canSeeDetails($request->user()), 403);

        return self::deliver($file);
    }

    public function destroyFile(Request $request, CalendarEvent $calendarEvent, CalendarEventFile $file)
    {
        abort_unless($calendarEvent->canManage($request->user()) && $file->calendar_event_id === $calendarEvent->id, 403);

        if ($file->path && !CalendarEventFile::where('source_file_id', $file->id)->exists()) {
            Storage::disk('local')->delete($file->path);
        }
        $file->delete();

        return back()->with('success', 'Entfernt: ' . $file->title . '.');
    }

    /** Datei oder Link ausliefern (auch für Gäste, siehe GuestInvitationController) */
    public static function deliver(CalendarEventFile $file)
    {
        $stored = $file->stored();
        abort_unless($stored, 404);

        if ($stored->url && !$stored->path) return redirect()->away($stored->url);
        abort_unless(Storage::disk('local')->exists($stored->path), 404);

        return Storage::disk('local')->download($stored->path, $stored->original_name);
    }

    // ── Hilfen ──────────────────────────────────────────────────────────────

    /** Zielgruppen einfacher Termine; Termine mit Einladung richten sich nach den Eingeladenen */
    private function syncTargetGroups(Request $request, CalendarEvent $event): void
    {
        $ids = $request->validate([
            'target_group_ids'   => ['nullable', 'array'],
            'target_group_ids.*' => ['integer', 'exists:training_groups,id'],
        ])['target_group_ids'] ?? [];
        $event->trainingGroups()->sync($event->hasInvitations() ? [] : $ids);
    }

    private function targetGroups()
    {
        return TrainingGroup::where('active', true)->orderBy('name')->get(['id', 'name']);
    }

    private function validated(Request $request, array $allowedTypes): array
    {
        $data = $request->validate([
            'title'         => ['required', 'string', 'max:200'],
            'description'   => ['nullable', 'string', 'max:5000'],
            'location'      => ['nullable', 'string', 'max:200'],
            'agenda'        => ['nullable', 'string', 'max:20000'],
            'start_date'    => ['required', 'date'],
            'end_date'      => ['nullable', 'date', 'gte:start_date'],
            'start_time'    => ['nullable', 'date_format:H:i'],
            'end_time'      => ['nullable', 'date_format:H:i'],
            'type'          => ['required', 'in:' . implode(',', $allowedTypes)],
            'season_id'     => ['nullable', 'exists:seasons,id'],
            'rsvp_enabled'  => ['nullable', 'boolean'],
            'rsvp_deadline' => ['nullable', 'date'],
            'capacity'      => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        $data['agenda']       = RichText::isEmpty($data['agenda'] ?? null) ? null : RichText::sanitize($data['agenda']);
        $data['rsvp_enabled'] = $request->boolean('rsvp_enabled');

        return $data;
    }

    private function inviteFromRequest(Request $request, CalendarEvent $event): int
    {
        $sel = $request->validate([
            'all_board'        => ['nullable', 'boolean'],
            'group_ids'        => ['nullable', 'array'],
            'group_ids.*'      => ['integer', 'exists:training_groups,id'],
            'user_ids'         => ['nullable', 'array'],
            'user_ids.*'       => ['integer', 'exists:users,id'],
            'guest_user_ids'   => ['nullable', 'array'],
            'guest_user_ids.*' => ['integer', 'exists:users,id'],
            'guest_emails'     => ['nullable', 'string', 'max:5000'],
        ]);
        $sel['all_board'] = $request->boolean('all_board');

        // Ohne Matrix-Recht "Beliebige Gruppen einladen" nur die eigenen Gruppen
        $user = $request->user();
        if (!$user->canAccess('events_all_groups')) {
            $own = $user->trainingGroups()->pluck('training_groups.id')->all();
            $sel['group_ids'] = array_values(array_intersect($sel['group_ids'] ?? [], $own));
        }

        return $this->invitations->invite(
            $event,
            $this->invitations->audience($event, $sel),
            EventInvitations::parseGuests($sel['guest_emails'] ?? null),
        );
    }

    /** Auswahllisten für Einladungen */
    private function formData(User $user): array
    {
        $isStaff = $user->canAccess('events_all_groups');

        return [
            'seasons'     => Season::orderByDesc('start_date')->get(),
            'groups'      => $isStaff
                ? TrainingGroup::where('active', true)->orderBy('name')->get(['id', 'name'])
                : $user->trainingGroups()->where('active', true)->orderBy('name')->get(['training_groups.id', 'name']),
            'swimmers'    => User::where('role', 'schwimmer')->where('active', true)->orderBy('lastname')->orderBy('firstname')->get(['id', 'firstname', 'lastname']),
            'portalUsers' => User::where('active', true)->orderBy('lastname')->orderBy('firstname')->get(['id', 'firstname', 'lastname', 'role']),
            'boardCount'  => EventInvitations::boardMembers()->count(),
        ];
    }
}
