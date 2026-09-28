@extends('layouts.app')
@section('title', 'Hallenbelegung')
@section('page-title', 'Hallenbelegung')

@section('content')
@php
    // Grid constants — schedule runs 05:30–22:00
    $scheduleStartMin = 330;          // 05:30 in minutes from midnight
    $scheduleEndMin   = 22 * 60;      // 22:00 = 1320 min
    $slotMin          = 15;
    $totalSlots       = ($scheduleEndMin - $scheduleStartMin) / $slotMin; // 66

    // Day view: large scale
    $daySlotPx  = 24;                          // px per 15-min slot
    $dayHourPx  = $daySlotPx * 4;             // 96 px per hour
    $dayTotalPx = $totalSlots * $daySlotPx;   // 1584 px

    // Wochenansicht: Die Stundenskala war mit 32 px so eng, dass von kurzen
    // Belegungen nicht einmal die Beschriftung zu sehen war. Platz nach unten
    // ist genug da - die Seite scrollt ohnehin.
    $weekSlotPx  = 15;                         // px je 15-Minuten-Schritt
    $weekHourPx  = $weekSlotPx * 4;           // 60 px je Stunde (vorher 32)
    $weekTotalPx = $totalSlots * $weekSlotPx; // 990 px
    // 46 px reichten nicht einmal fuer "Grupp…" - der Plan scrollt ohnehin
    // waagerecht, und quer ist mehr Platz als frueher genutzt wurde.
    $weekColPx   = 76;                         // px je Ressourcenspalte

    // Compact mode: hide 08:00–13:00 (slots 10–29 = 20 slots)
    $cHideStart  = 10;                                        // 08:00
    $cHideEnd    = 30;                                        // 13:00
    $cHideN      = 20;
    $weekCmptPx  = ($totalSlots - $cHideN) * $weekSlotPx;   // 368 px
    $dayCmptPx   = ($totalSlots - $cHideN) * $daySlotPx;    // 1104 px
    // PHP compact-slot helper: maps a raw slot to its display position
    $cSlotFn     = fn(float $s): float => $s >= $cHideEnd ? $s - $cHideN : min($s, (float)$cHideStart);

    $scheduleStart = intdiv($scheduleStartMin, 60); // 5  (05:xx)
    $scheduleEnd   = intdiv($scheduleEndMin, 60);   // 22

    $days = \App\Models\HallBooking::DAY_NAMES;

    // Abbreviate resource names for week view headers
    $abbrevFn = function(string $name): string {
        if (preg_match('/Bahn\s*(\d)/i', $name, $m)) return 'B' . $m[1];
        if (str_contains($name, 'Nicht')) return 'NS';
        if (str_contains($name, 'Mehr')) return 'MZ';
        return mb_strtoupper(mb_substr($name, 0, 2));
    };

    // Serialize for Alpine.js
    $groupsForJs    = $groups->map(fn($g) => [
        'id'           => $g->id,
        'name'         => $g->name,
        'trainer_id'   => $g->trainers->first()?->id,
        'trainer_name' => $g->trainers->first()?->name,
    ])->values()->toJson();

    $trainersJson   = $trainers->map(fn($t) => [
        'id'   => $t->id,
        'name' => $t->lastname . ', ' . $t->firstname,
    ])->values()->toJson();

    $resourcesJson  = $resources->map(fn($r) => [
        'id'     => $r->id,
        'name'   => $r->name,
        'abbrev' => $abbrevFn($r->name),
        'color'  => $r->color,
    ])->values()->toJson();
@endphp

{{-- Alpine.js component defined in a script block to avoid JSON-in-attribute encoding issues --}}
<script>
const _hallResources     = {!! $resourcesJson !!};
const _hallGroups        = {!! $groupsForJs !!};
const _hallTrainers      = {!! $trainersJson !!};
const _hallBookings      = {!! $bookingsJson->toJson() !!};
const _expiredSeriesIds  = {!! $expiredGroupIds->toJson() !!};
const _daySlotPx         = {{ $daySlotPx }};
const _weekSlotPx        = {{ $weekSlotPx }};
const _scheduleStartMin  = {{ $scheduleStartMin }}; // 05:30

function hallApp() {
    return {
        view:            'week',
        currentDay:      1,
        filterGroup:     null,
        filterTrainer:   null,
        filterFree:      false,
        filterConflicts: false,
        drag:            null,
        _recentDrag:     false,
        resources:   _hallResources,
        groups:      _hallGroups,
        trainers:    _hallTrainers,
        bookings:        _hallBookings,
        expiredSeriesIds: _expiredSeriesIds,
        daySlotPx:   _daySlotPx,
        weekSlotPx:  _weekSlotPx,

        // ── Modal ──────────────────────────────────────────────────────
        showModal: false,
        editId:    null,
        conflicts: [],
        saving:    false,
        formError: '',
        pageError: '',
        sessionError: '',
        form: {
            hall_resource_ids: [], day_of_week: 1,
            start_time: '08:00', end_time: '10:00',
            label: '', type: 'training',
            training_group_id: null, trainer_id: null,
            training_session_id: null,
            notes: '', color: '',
        },
        // ── Session search ────────────────────────────────────────────
        linkedSession:    null,
        sessionResults:   [],
        sessionSearching: false,

        // ── Conflict detection (client-side) ──────────────────────────
        hasConflict(b) {
            return this.bookings.some(o =>
                o.id !== b.id &&
                o.hall_resource_id === b.hall_resource_id &&
                o.day_of_week      === b.day_of_week &&
                o.start_slot < b.start_slot + b.duration_slots &&
                o.start_slot + o.duration_slots > b.start_slot
            );
        },

        // ── Filter ────────────────────────────────────────────────────
        bookingOpacity(b) {
            if (this.filterFree)     return 'opacity-20 pointer-events-none';
            if (this.filterConflicts && !this.hasConflict(b))                    return 'opacity-20';
            if (this.filterGroup     && b.training_group_id != this.filterGroup)  return 'opacity-20';
            if (this.filterTrainer   && b.trainer_id        != this.filterTrainer) return 'opacity-20';
            return '';
        },
        get activeFilter() {
            return this.filterGroup || this.filterTrainer || this.filterConflicts;
        },
        clearFilters() {
            this.filterGroup = null; this.filterTrainer = null;
            this.filterFree  = false; this.filterConflicts = false;
        },

        // ── Overlap layout: returns {i: columnIndex, n: totalColumns} ─────
        // Bookings that overlap in the same resource+day slot are shown side by side.
        overlapLayout(bid, rid, day) {
            const col  = this.bookings.filter(b => b.hall_resource_id === rid && b.day_of_week === day);
            const bk   = col.find(b => b.id === bid);
            if (!bk) return { i: 0, n: 1 };
            const grp  = col
                .filter(o => o.start_slot < bk.start_slot + bk.duration_slots &&
                             o.start_slot + o.duration_slots > bk.start_slot)
                .sort((a, b) => a.id - b.id);
            return { i: grp.findIndex(o => o.id === bid), n: grp.length };
        },

        // ── Compact mode: map raw slot to display slot (skips 08:00–13:00) ─
        _cSlot(slot) {
            if (!this.compactMode) return slot;
            const HS = {{ $cHideStart }}, HE = {{ $cHideEnd }}, HN = {{ $cHideN }};
            if (slot < HS) return slot;
            if (slot < HE) return HS;   // booking starts in hidden zone → clamp to boundary
            return slot - HN;
        },
        _cDur(startSlot, dur) {
            if (!this.compactMode) return dur;
            const HS = {{ $cHideStart }}, HE = {{ $cHideEnd }};
            let vis = 0;
            for (let s = startSlot; s < startSlot + dur; s++) {
                if (s < HS || s >= HE) vis++;
            }
            return Math.max(vis, 0);
        },

        // ── Expired series detection ──────────────────────────────────
        isExpiredSeries(b) {
            return !!(b.recurrence_group_id && this.expiredSeriesIds.includes(b.recurrence_group_id));
        },
        _hatchStyle: 'background-image:repeating-linear-gradient(45deg,rgba(0,0,0,0.14) 0,rgba(0,0,0,0.14) 2px,transparent 0,transparent 50%);background-size:8px 8px;',

        // ── Booking block styles (handles drag repositioning + overlap) ───
        weekBookingStyle(b) {
            const rawSlot    = (this.drag?.bookingId === b.id) ? this.drag.currentSlot : b.start_slot;
            const slot       = this._cSlot(rawSlot);
            const durSlots   = this._cDur(rawSlot, b.duration_slots);
            const h          = Math.max(durSlots * this.weekSlotPx - 2, 6);
            const zi         = (this.drag?.bookingId === b.id) ? 10 : 1;
            const { i, n }   = this.overlapLayout(b.id, b.hall_resource_id, b.day_of_week);
            const pct        = 100 / n;
            let style = `position:absolute; top:${slot*this.weekSlotPx+1}px; height:${h}px; `
                      + `left:calc(${i*pct}% + 1px); width:calc(${pct}% - 2px); `
                      + `border-radius:5px; background-color:${b.display_color}; color:${b.text_color}; `
                      + `box-shadow:0 1px 2px rgba(0,0,0,.18), inset 0 1px 0 rgba(255,255,255,.22); `
                      + `z-index:${zi}; overflow:hidden;`;
            if (this.isExpiredSeries(b)) style += this._hatchStyle;
            // Im Konfliktfilter sollen die Treffer herausstechen, nicht nur
            // "weniger blass" sein als der Rest.
            if (this.filterConflicts && this.hasConflict(b)) {
                style += 'outline:3px solid #dc2626; outline-offset:-1px; z-index:8;';
            }
            return style;
        },
        dayBookingStyle(b) {
            const rawSlot    = (this.drag?.bookingId === b.id) ? this.drag.currentSlot : b.start_slot;
            const slot       = this._cSlot(rawSlot);
            const durSlots   = this._cDur(rawSlot, b.duration_slots);
            const h          = Math.max(durSlots * this.daySlotPx - 2, 4);
            const zi         = (this.drag?.bookingId === b.id) ? 10 : 2;
            const { i, n }   = this.overlapLayout(b.id, b.hall_resource_id, b.day_of_week);
            const pct        = 100 / n;
            let style = `position:absolute; top:${slot*this.daySlotPx+1}px; height:${h}px; `
                      + `left:calc(${i*pct}% + 2px); width:calc(${pct}% - 4px); `
                      + `border-radius:8px; background-color:${b.display_color}; color:${b.text_color}; `
                      + `box-shadow:0 1px 3px rgba(0,0,0,.2), inset 0 1px 0 rgba(255,255,255,.22); `
                      + `z-index:${zi}; overflow:hidden;`;
            if (this.isExpiredSeries(b)) style += this._hatchStyle;
            if (this.filterConflicts && this.hasConflict(b)) {
                style += 'outline:3px solid #dc2626; outline-offset:-1px; z-index:8;';
            }
            return style;
        },

        // ── Freie Zeitfenster je Ressource und Tag ────────────────────
        //
        // Der Filter "freie Kapazitaeten" hat die Belegungen bisher nur blass
        // gemacht und die ganze Spalte zart gruen hinterlegt - man musste die
        // Luecken selbst suchen. Jetzt werden sie als eigene Bloecke gezeichnet.
        freeSlots(resourceId, day) {
            const belegt = this.dayResourceBookings(resourceId, day)
                .map(b => [b.start_slot, b.start_slot + b.duration_slots])
                .sort((a, b) => a[0] - b[0]);

            // Ueberlappende Belegungen zu durchgehenden Bereichen verschmelzen
            const zusammen = [];
            for (const [von, bis] of belegt) {
                const letzter = zusammen[zusammen.length - 1];
                if (letzter && von <= letzter[1]) letzter[1] = Math.max(letzter[1], bis);
                else zusammen.push([von, bis]);
            }

            const luecken = [];
            let cursor = 0;
            for (const [von, bis] of zusammen) {
                if (von > cursor) luecken.push([cursor, von]);
                cursor = Math.max(cursor, bis);
            }
            if (cursor < this.totalSlots) luecken.push([cursor, this.totalSlots]);

            // Winzige Reste sind keine nutzbare Kapazitaet
            return luecken
                .filter(([von, bis]) => bis - von >= 2)
                .map(([von, bis]) => ({
                    id:    `${resourceId}-${day}-${von}`,
                    start: von,
                    dur:   bis - von,
                    label: this.slotTime(von) + '–' + this.slotTime(bis),
                }));
        },
        slotTime(slot) {
            const min = _scheduleStartMin + slot * 15;
            const pad = n => String(n).padStart(2, '0');
            return `${pad(Math.floor(min / 60))}:${pad(min % 60)}`;
        },
        freeSlotStyle(f, slotPx) {
            const slot = this._cSlot(f.start);
            const dur  = this._cDur(f.start, f.dur);
            if (dur <= 0) return 'display:none';
            return `position:absolute; top:${slot*slotPx+1}px; height:${Math.max(dur*slotPx-2, 6)}px; `
                 + 'left:1px; right:1px; border-radius:5px; z-index:2; '
                 + 'background:#22c55e; border:1px solid #15803d; '
                 + 'box-shadow:0 0 0 1px rgba(34,197,94,.35);';
        },

        // ── Drag & Drop ───────────────────────────────────────────────
        startDrag(b, event) {
            event.stopPropagation();
            this.drag = {
                bookingId:     b.id,
                durationSlots: b.duration_slots,
                initialSlot:   b.start_slot,
                currentSlot:   b.start_slot,
                startY:        event.clientY,
                moved:         false,
            };
            event.currentTarget.setPointerCapture(event.pointerId);
        },
        moveDrag(event) {
            if (!this.drag) return;
            const slotPx   = this.view === 'week' ? this.weekSlotPx : this.daySlotPx;
            const delta    = Math.round((event.clientY - this.drag.startY) / slotPx);
            const raw      = this.drag.initialSlot + delta;
            const maxSlot  = 66 - this.drag.durationSlots; // 66 total 15-min slots (05:30–22:00)
            const newSlot  = Math.max(0, Math.min(raw, maxSlot));
            if (newSlot !== this.drag.currentSlot) {
                this.drag.currentSlot = newSlot;
                this.drag.moved = true;
            }
        },
        async endDrag(event) {
            if (!this.drag) return;
            const ds      = this.drag;
            const booking = this.bookings.find(b => b.id === ds.bookingId);
            this.drag = null;
            if (!booking || !ds.moved) return; // no movement → let @click.stop="openEdit(b)" fire
            if (ds.currentSlot === ds.initialSlot) return;

            // Block openEdit synchronously before any await so the browser's lingering
            // click event (after pointerup) does not re-open the modal with stale data
            this._recentDrag = true;
            setTimeout(() => { this._recentDrag = false; }, 300);

            const startMin = _scheduleStartMin + ds.currentSlot * 15;
            const endMin   = startMin + ds.durationSlots * 15;
            const pad      = n => String(n).padStart(2, '0');
            const st       = `${pad(Math.floor(startMin/60))}:${pad(startMin%60)}`;
            const et       = `${pad(Math.floor(endMin/60))}:${pad(endMin%60)}`;

            // Optimistic update BEFORE await — if the click fires during the request,
            // the modal will already show the correct new times
            const vorher = { start_time: booking.start_time, end_time: booking.end_time, start_slot: booking.start_slot };
            booking.start_time = st; booking.end_time = et; booking.start_slot = ds.currentSlot;
            const zuruecksetzen = () => Object.assign(booking, vorher);

            const res = await this.request(`/trainer/hall/bookings/${ds.bookingId}`, 'PUT', {
                day_of_week: booking.day_of_week, start_time: st, end_time: et,
                label: booking.label, type: booking.type,
                training_group_id: booking.training_group_id, trainer_id: booking.trainer_id,
                training_session_id: booking.training_session_id,
                notes: booking.notes ?? '',
            });
            if (res.ok) return;

            zuruecksetzen();
            if (res.status === 409) {
                // Ueberschneidung: nicht still speichern, sondern im Dialog entscheiden lassen
                this._recentDrag = false;
                this.openEdit(booking);
                this.form.start_time = st;
                this.form.end_time   = et;
                this.conflicts = res.data.conflicts ?? [];
                this.formError = 'Die neue Zeit überschneidet sich mit bestehenden Belegungen.';
                return;
            }
            this.pageError = res.message;
        },

        /**
         * Einheitlicher JSON-Request mit lesbarer Fehlermeldung.
         * Liefert { ok, status, data, message } und wirft nie.
         */
        async request(url, method = 'GET', body = null) {
            try {
                const r = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: body ? JSON.stringify(body) : null,
                });
                const data = await r.json().catch(() => ({}));
                let message = '';
                if (!r.ok) {
                    message = r.status === 422 ? (Object.values(data.errors ?? {}).flat().join(' ') || data.message)
                            : r.status === 419 ? 'Deine Sitzung ist abgelaufen. Bitte Seite neu laden und erneut anmelden.'
                            : r.status === 403 ? 'Dafür fehlt dir die Berechtigung.'
                            : (data.message || `Aktion fehlgeschlagen (Fehler ${r.status}).`);
                }
                return { ok: r.ok, status: r.status, data, message };
            } catch (e) {
                return { ok: false, status: 0, data: {}, message: 'Keine Verbindung zum Server. Bitte erneut versuchen.' };
            }
        },

        // ── Bookings for a given resource + day ───────────────────────
        dayResourceBookings(resourceId, day) {
            return this.bookings.filter(b => b.hall_resource_id === resourceId && b.day_of_week === day);
        },

        /**
         * Liegt die Belegung vollstaendig in der ausgeblendeten Zeit?
         *
         * Im Kompaktmodus wurden solche Belegungen auf die Trennlinie gequetscht
         * und waren als fingerbreite Streifen ohne Beschriftung weder lesbar
         * noch anklickbar. Besser gar nicht zeichnen und stattdessen sagen,
         * dass da etwas ist.
         */
        inHiddenZone(b) {
            const HS = {{ $cHideStart }}, HE = {{ $cHideEnd }};
            return b.start_slot >= HS && (b.start_slot + b.duration_slots) <= HE;
        },
        weekVisibleBookings(resourceId, day) {
            const alle = this.dayResourceBookings(resourceId, day);
            return this.compactMode ? alle.filter(b => !this.inHiddenZone(b)) : alle;
        },
        hiddenCount(resourceId, day) {
            if (!this.compactMode) return 0;
            return this.dayResourceBookings(resourceId, day).filter(b => this.inHiddenZone(b)).length;
        },

        // ── Slot / time helpers ───────────────────────────────────────
        slotToTime(slot) {
            const totalMin = _scheduleStartMin + slot * 15;
            const h = Math.floor(totalMin / 60);
            const m = totalMin % 60;
            return String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0');
        },

        // ── Modal helpers ─────────────────────────────────────────────
        openCreate(resourceId, day, slot) {
            this.editId = null; this.conflicts = []; this.linkedSession = null; this.sessionResults = [];
            this.formError = ''; this.sessionError = '';
            const s = slot ?? 8 * 4;
            this.form = {
                hall_resource_ids: resourceId ? [resourceId] : [],
                day_of_week: day ?? this.currentDay,
                start_time: this.slotToTime(s),
                end_time:   this.slotToTime(Math.min(s + 8, 64)),
                label: '', type: 'training',
                training_group_id: null, trainer_id: null,
                training_session_id: null,
                notes: '', color: '',
            };
            this.showModal = true;
        },
        openEdit(b) {
            if (this._recentDrag) return;  // suppress click fired after a drag
            this.editId = b.id; this.conflicts = []; this.sessionResults = [];
            this.formError = ''; this.sessionError = '';
            this.linkedSession = b.training_session_id ? { id: b.training_session_id, title: b.session_title ?? ('Einheit #' + b.training_session_id) } : null;
            this.form = {
                hall_resource_ids: [b.hall_resource_id],
                day_of_week: b.day_of_week,
                start_time: b.start_time, end_time: b.end_time,
                label: b.label, type: b.type,
                training_group_id: b.training_group_id,
                trainer_id: b.trainer_id ?? null, notes: b.notes ?? '', color: '',
                training_session_id: b.training_session_id ?? null,
            };
            this.showModal = true;
        },
        async searchSessions() {
            if (!this.form.day_of_week || !this.form.start_time || !this.form.end_time) return;
            this.sessionSearching = true;
            this.sessionError = '';
            const p = new URLSearchParams({ day_of_week: this.form.day_of_week, start_time: this.form.start_time, end_time: this.form.end_time });
            const res = await this.request(`/trainer/hall/sessions/search?${p}`);
            this.sessionResults = res.ok ? (res.data.sessions ?? []) : [];
            if (!res.ok) this.sessionError = res.message;
            this.sessionSearching = false;
        },
        linkSession(s) {
            this.form.training_session_id = s.id;
            this.linkedSession = s;
            if (!this.form.label) this.form.label = s.title;
            this.sessionResults = [];
        },
        unlinkSession() { this.form.training_session_id = null; this.linkedSession = null; },
        onGroupChange() {
            const g = this.groups.find(g => g.id == this.form.training_group_id);
            if (g) {
                if (g.trainer_id) this.form.trainer_id = g.trainer_id;
                if (!this.form.label) this.form.label = g.name;
            }
        },
        async checkConflicts() {
            if (!this.form.hall_resource_ids.length || !this.form.start_time || !this.form.end_time) return;
            const p = new URLSearchParams({ day_of_week: this.form.day_of_week, start_time: this.form.start_time, end_time: this.form.end_time });
            this.form.hall_resource_ids.forEach(id => p.append('hall_resource_ids[]', id));
            if (this.editId) p.append('exclude_id', this.editId);
            const res = await this.request(`/trainer/hall/conflicts?${p}`);
            // Fehlgeschlagene Pruefung ist kein "keine Konflikte" - der Server prueft beim Speichern erneut
            if (res.ok) this.conflicts = res.data.conflicts ?? [];
        },
        // force nur ueber "Trotzdem speichern"; sonst lehnt der Server Ueberschneidungen ab (409)
        async save(force = false) {
            this.saving = true;
            this.formError = '';
            const url    = this.editId ? `/trainer/hall/bookings/${this.editId}` : '/trainer/hall/bookings';
            const method = this.editId ? 'PUT' : 'POST';
            const res = await this.request(url, method, { ...this.form, force });
            if (res.ok) { window.location.reload(); return; }
            this.saving = false;
            if (res.status === 409) {
                this.conflicts = res.data.conflicts ?? [];
                this.formError = 'Überschneidung mit bestehenden Belegungen – prüfen oder „Trotzdem speichern“.';
                return;
            }
            this.formError = res.message;
        },
        async deleteBooking(id) {
            if (!confirm('Belegung löschen?')) return;
            const res = await this.request(`/trainer/hall/bookings/${id}`, 'DELETE');
            if (res.ok) { window.location.reload(); return; }
            if (this.showModal) this.formError = res.message;
            else this.pageError = res.message;
        },

        get dayName() { return ['','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'][this.currentDay]; },

        compactMode: false,
        totalSlots:  {{ $totalSlots }},

        /**
         * Haftpunkt der Kopfzeile bestimmen.
         *
         * Das Portal hat oben eine eigene fixierte Leiste. Wuerde die
         * Tages-/Bahnzeile bei top:0 haften, verschwaende sie darunter.
         * Deshalb wird deren Hoehe gemessen statt geraten - sie aendert sich
         * mit der Fensterbreite.
         */
        initSticky() {
            const setzen = () => {
                const kopf  = document.querySelector('header.sticky');
                const hoehe = kopf ? Math.round(kopf.getBoundingClientRect().height) : 0;
                this.$el.style.setProperty('--hall-top', hoehe + 'px');
            };
            setzen();
            window.addEventListener('resize', setzen);
        },

        // Legende: eingeklappt starten, Zustand im Browser merken
        legendOpen: false,
        initLegend() {
            try {
                const saved = localStorage.getItem('card:hall-legend');
                if (saved !== null) this.legendOpen = saved === '1';
            } catch (e) {}
        },
        toggleLegend() {
            this.legendOpen = !this.legendOpen;
            try { localStorage.setItem('card:hall-legend', this.legendOpen ? '1' : '0'); } catch (e) {}
        },
    };
}
</script>

<div class="mt-2" x-data="hallApp()" x-init="initSticky()">

{{-- Fehler ausserhalb des Dialogs (Verschieben, Loeschen) --}}
<div x-show="pageError" x-cloak role="alert"
     class="mb-3 flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3">
    <span class="flex-1" x-text="pageError"></span>
    <button type="button" @click="pageError = ''" class="text-red-400 hover:text-red-600" aria-label="Meldung schließen">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
</div>

{{-- ── Top bar ──────────────────────────────────────────────────────────────── --}}
<div class="flex flex-wrap items-center gap-3 mb-4">

    {{-- View toggle --}}
    <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm font-medium shadow-sm">
        <button @click="view='week'"
                :class="view==='week' ? 'bg-primary text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                class="px-4 py-2 transition-colors">
            <svg class="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Woche
        </button>
        <button @click="view='day'"
                :class="view==='day' ? 'bg-primary text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                class="px-4 py-2 border-l border-gray-200 transition-colors">
            <svg class="w-4 h-4 inline mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Tag
        </button>
    </div>

    {{-- Day selector (day view) --}}
    <div class="flex rounded-lg border border-gray-200 overflow-hidden text-xs shadow-sm" x-show="view==='day'" x-transition>
        @foreach($days as $num => $name)
        <button @click="currentDay={{ $num }}"
                :class="currentDay==={{ $num }} ? 'bg-primary text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
                class="px-3 py-2 {{ $num > 1 ? 'border-l border-gray-200' : '' }} transition-colors font-medium">
            {{ substr($name, 0, 2) }}
        </button>
        @endforeach
    </div>

    {{-- Group filter --}}
    <select x-model="filterGroup" @change="filterTrainer = null; filterFree = false"
            :class="filterGroup ? 'border-primary ring-1 ring-primary/30 bg-primary/5 text-primary font-medium' : 'border-gray-200 bg-white text-gray-700'"
            class="text-sm rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-blue-400 outline-none transition-colors border">
        <option value="">Alle Gruppen</option>
        @foreach($groups as $g)
        <option value="{{ $g->id }}">{{ $g->name }}</option>
        @endforeach
    </select>

    {{-- Trainer filter --}}
    <select x-model="filterTrainer" @change="filterGroup = null; filterFree = false"
            :class="filterTrainer ? 'border-indigo-400 ring-1 ring-indigo-300 bg-indigo-50 text-indigo-700 font-medium' : 'border-gray-200 bg-white text-gray-700'"
            class="text-sm rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-indigo-400 outline-none transition-colors border">
        <option value="">Alle Trainer</option>
        @foreach($trainers as $t)
        <option value="{{ $t->id }}">{{ $t->lastname }}, {{ $t->firstname }}</option>
        @endforeach
    </select>

    {{-- Active filter reset badge --}}
    <button x-show="activeFilter" x-transition
            @click="clearFilters()"
            class="flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-300 bg-white text-xs text-gray-600 hover:bg-gray-50 shadow-sm transition-colors font-medium">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        Filter zurücksetzen
    </button>

    {{-- Conflict filter --}}
    <button @click="filterConflicts = !filterConflicts; if(filterConflicts) { filterGroup = null; filterTrainer = null; filterFree = false; }"
            :class="filterConflicts ? 'bg-red-600 text-white border-red-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
            class="flex items-center gap-2 px-3 py-2 rounded-lg border text-sm font-medium shadow-sm transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        Konflikte
    </button>

    {{-- Free capacity --}}
    <button @click="filterFree = !filterFree; if(filterFree) { filterGroup = null; filterTrainer = null; filterConflicts = false; }"
            :class="filterFree ? 'bg-green-600 text-white border-green-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
            class="flex items-center gap-2 px-3 py-2 rounded-lg border text-sm font-medium shadow-sm transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        Freie Kapazitäten
    </button>

    {{-- Kompaktansicht: blendet 05:30–13:00 und Sonntag aus --}}
    <button @click="compactMode = !compactMode"
            :class="compactMode ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
            class="flex items-center gap-2 px-3 py-2 rounded-lg border text-sm font-medium shadow-sm transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 11h16M4 16h10"/></svg>
        <span x-text="compactMode ? 'Vollansicht' : 'Kompaktansicht'"></span>
    </button>

    <a href="{{ route('trainer.hall.import.index') }}"
       class="ml-auto flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 text-sm font-medium shadow-sm transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
        Plan importieren
    </a>

    <button @click="openCreate(null, view==='day' ? currentDay : 1, null)"
            class="flex items-center gap-2 bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        Neue Belegung
    </button>
</div>

{{-- ── Legende ───────────────────────────────────────────────────────────────
     Die Farben stammen aus den Gruppen- und Belegungsdefinitionen, nicht aus
     einer eigenen Liste. Gezeigt wird nur, was im Plan auch vorkommt.
──────────────────────────────────────────────────────────────────────────── --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-2 overflow-hidden" x-init="initLegend()">
    <button type="button" @click="toggleLegend()" :aria-expanded="legendOpen ? 'true' : 'false'"
            class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-gray-50 transition-colors">
        <span class="text-sm font-semibold text-gray-700">Legende</span>
        <span class="flex items-center gap-1">
            @foreach($legendGroups->take(8) as $g)
                <span class="inline-block w-3 h-3 rounded-sm" style="background-color:{{ $g['hex'] }}"
                      title="{{ $g['name'] }}"></span>
            @endforeach
            @if($legendGroups->count() > 8)
                <span class="text-[10px] text-gray-400">+{{ $legendGroups->count() - 8 }}</span>
            @endif
        </span>
        <span class="text-xs text-gray-400 ml-auto">{{ $legendGroups->count() }} Gruppen · {{ $legendTypes->count() }} weitere Arten</span>
        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="legendOpen ? 'rotate-180' : ''"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div x-show="legendOpen" x-cloak class="px-4 pb-4 pt-1 border-t border-gray-100 space-y-4">

        @if($legendGroups->isNotEmpty())
        <div>
            <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-2">Trainingsgruppen</p>
            <div class="flex flex-wrap gap-2">
                @foreach($legendGroups as $g)
                    <span class="inline-flex items-center gap-1.5 pl-1.5 pr-2.5 py-1 rounded-md text-xs font-medium"
                          style="background-color:{{ $g['hex'] }}; color:{{ $g['text'] }}">
                        {{ $g['name'] }}
                        <span class="text-[10px] opacity-70">{{ $g['count'] }}</span>
                    </span>
                @endforeach
            </div>
        </div>
        @endif

        @if($legendTypes->isNotEmpty())
        <div>
            <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-2">Belegungen ohne Trainingsgruppe</p>
            <div class="flex flex-wrap gap-2">
                @foreach($legendTypes as $t)
                    <span class="inline-flex items-center gap-1.5 pl-1.5 pr-2.5 py-1 rounded-md text-xs font-medium"
                          style="background-color:{{ $t['hex'] }}; color:{{ \App\Models\HallBooking::readableTextColor($t['hex']) }}">
                        {{ $t['name'] }}
                        <span class="text-[10px] opacity-70">{{ $t['count'] }}</span>
                    </span>
                @endforeach
            </div>
        </div>
        @endif

        <div>
            <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-2">Kennzeichnungen</p>
            <div class="flex flex-wrap gap-4 text-xs text-gray-600">
                <span class="inline-flex items-center gap-2">
                    <span class="inline-block w-8 h-4 rounded"
                          style="background-color:#F97316; background-image:repeating-linear-gradient(45deg,rgba(0,0,0,0.14) 0,rgba(0,0,0,0.14) 2px,transparent 0,transparent 50%); background-size:8px 8px;"></span>
                    Ausgelaufene Trainingsserie (keine künftigen Termine)
                </span>
                <span class="inline-flex items-center gap-2">
                    <span class="inline-block w-8 h-4 rounded bg-blue-500" style="outline:3px solid #dc2626; outline-offset:-1px"></span>
                    Zeitliche Überschneidung
                </span>
                <span class="inline-flex items-center gap-2">
                    <span class="inline-block w-8 h-4 rounded" style="background:#22c55e; border:1px solid #15803d"></span>
                    Freies Zeitfenster (Filter „Freie Kapazitäten")
                </span>
            </div>
        </div>
    </div>
</div>

{{-- Hinweis auf ausgelaufene Serien --}}
<div x-show="expiredSeriesIds.length > 0" x-transition
     class="flex items-center gap-2.5 px-4 py-2 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-800 mb-1">
    <span class="inline-block w-8 h-4 rounded flex-shrink-0"
          style="background-color:#F97316; background-image:repeating-linear-gradient(45deg,rgba(0,0,0,0.14) 0,rgba(0,0,0,0.14) 2px,transparent 0,transparent 50%); background-size:8px 8px;"></span>
    <span>Schraffierte Belegungen gehören zu ausgelaufenen Trainingsserien (keine zukünftigen Termine). Bitte Serie bearbeiten &amp; Saison neu generieren.</span>
</div>

{{-- ════════════════════════════════════════════════════════════════════════════
     WOCHENANSICHT
     Ressourcen als parallele Spalten, alle 7 Tage nebeneinander,
     Blöcke proportional zur Dauer.
════════════════════════════════════════════════════════════════════════════ --}}
<div x-show="view==='week'" x-transition>
{{-- Kein overflow-hidden auf der Karte: Das machte sie zum Scroll-Container,
     an dem die Kopfzeile geklebt haette statt an der Seite. --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100">

{{-- ── Kopfzeile ─────────────────────────────────────────────────────────────
     Bewusst ausserhalb des waagerechten Scroll-Containers: Ein Element haftet
     immer am naechsten scrollbaren Vorfahren. Läge die Kopfzeile darin, wäre
     das der Container selbst - und der scrollt senkrecht gar nicht, die Zeile
     würde beim Herunterscrollen einfach mitwandern. Deshalb steht sie hier
     eigenstaendig und haftet an der Seite; ihr seitlicher Versatz wird unten
     an den Koerper gekoppelt.
──────────────────────────────────────────────────────────────────────────── --}}
<div class="sticky" style="top:var(--hall-top, 0px); z-index:20">
    <div x-ref="hallHead" class="overflow-hidden bg-gray-50 rounded-t-xl border-b border-gray-200">
        <div class="inline-flex" style="min-width: max-content">
            {{-- Platzhalter ueber der Zeitachse --}}
            <div class="flex-shrink-0 bg-gray-50 border-r border-gray-200 sticky left-0"
                 style="width:44px; height:52px; z-index:2"></div>

            @foreach($days as $dayNum => $dayName)
            <div class="flex-shrink-0 border-l border-gray-200"{{ $dayNum == 7 ? ' x-show="!compactMode"' : '' }}
                 style="height:52px">
                <button @click="view='day'; currentDay={{ $dayNum }}"
                        class="w-full text-center text-xs font-bold text-gray-700 hover:text-primary py-1.5 transition-colors"
                        style="width:{{ count($resources) * $weekColPx }}px">
                    {{ $dayName }}
                </button>
                <div class="flex">
                    @foreach($resources as $resource)
                    <div class="text-center border-l border-gray-100 first:border-0" style="width:{{ $weekColPx }}px">
                        <span class="text-[9px] font-bold uppercase tracking-wide" style="color:{{ $resource->color }}">
                            {{ $abbrevFn($resource->name) }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- ── Koerper ───────────────────────────────────────────────────────────────
     Scrollt waagerecht; overflow-y:clip, damit daraus kein senkrechter
     Scroll-Container wird. Beim Scrollen wird die Kopfzeile mitgezogen.
──────────────────────────────────────────────────────────────────────────── --}}
<div x-ref="hallBody" style="overflow-x:auto; overflow-y:clip;"
     @scroll="$refs.hallHead.scrollLeft = $refs.hallBody.scrollLeft">
<div class="inline-flex" style="min-width: max-content">

    {{-- Zeit-Achse: bleibt beim seitlichen Scrollen links stehen --}}
    <div class="flex-shrink-0 bg-gray-50 border-r border-gray-200 sticky left-0" style="width:44px; z-index:12">
        {{-- Stundenbeschriftungen --}}
        <div class="relative" :style="compactMode ? 'height:{{ $weekCmptPx }}px' : 'height:{{ $weekTotalPx }}px'">
            @for($h = $scheduleStart + 1; $h <= $scheduleEnd; $h++)
            @php
                $rawSlot = (($h * 60) - $scheduleStartMin) / $slotMin;
                $pyFull  = intval($rawSlot * $weekSlotPx - 6);
                $inHide  = ($h >= 8 && $h < 13);
                $cSlot   = $cSlotFn($rawSlot);
                $pyCmpt  = intval($cSlot * $weekSlotPx - 6);
            @endphp
            {{-- Vollansicht --}}
            <div x-show="!compactMode" class="absolute right-1.5 text-[10px] text-gray-400 select-none leading-none"
                 style="top:{{ $pyFull }}px">{{ sprintf('%02d:00', $h) }}</div>
            {{-- Kompaktansicht: Stunden 08–12 ausblenden --}}
            @if(!$inHide)
            <div x-show="compactMode" class="absolute right-1.5 text-[10px] text-gray-400 select-none leading-none"
                 style="top:{{ $pyCmpt }}px">{{ sprintf('%02d:00', $h) }}</div>
            @endif
            @endfor
            {{-- Trennlinie 08:00–13:00 im Kompaktmodus --}}
            <div x-show="compactMode" class="absolute right-0 left-0 pointer-events-none"
                 style="top:{{ $cHideStart * $weekSlotPx }}px; border-top:2px dashed #d1d5db;"></div>
        </div>
    </div>

    {{-- 7 Tagesspalten (Koepfe stehen oben in der haftenden Zeile) --}}
    @foreach($days as $dayNum => $dayName)
    <div class="flex-shrink-0 border-l border-gray-200"{{ $dayNum == 7 ? ' x-show="!compactMode"' : '' }}>

        {{-- Ressourcenspalten --}}
        <div class="flex" :style="compactMode ? 'height:{{ $weekCmptPx }}px' : 'height:{{ $weekTotalPx }}px'">
            @foreach($resources as $resource)
            <div class="relative border-l border-gray-100 first:border-0 overflow-hidden"
                 :style="compactMode ? 'width:{{ $weekColPx }}px; cursor:crosshair; height:{{ $weekCmptPx }}px' : 'width:{{ $weekColPx }}px; cursor:crosshair;'"
                 @click.self="openCreate({{ $resource->id }}, {{ $dayNum }}, Math.floor($event.offsetY / {{ $weekSlotPx }}))">
                {{-- Rasterlinien: Vollansicht --}}
                @for($h = $scheduleStart + 1; $h <= $scheduleEnd; $h++)
                @php $lpy = intval((($h * 60) - $scheduleStartMin) / $slotMin * $weekSlotPx); @endphp
                <div x-show="!compactMode" class="absolute pointer-events-none" style="left:0;right:0;top:{{ $lpy }}px;border-top:1px solid #e5e7eb;"></div>
                @endfor
                {{-- Rasterlinien: Kompaktansicht (08–12 Uhr weggelassen, 13+ verschoben) --}}
                @for($h = $scheduleStart + 1; $h <= $scheduleEnd; $h++)
                @php
                    $rs   = (($h * 60) - $scheduleStartMin) / $slotMin;
                    $inH  = ($h >= 8 && $h < 13);
                    $clpy = intval($cSlotFn($rs) * $weekSlotPx);
                @endphp
                @if(!$inH)
                <div x-show="compactMode" class="absolute pointer-events-none" style="left:0;right:0;top:{{ $clpy }}px;border-top:1px solid #e5e7eb;"></div>
                @endif
                @endfor
                {{-- Trennlinie im Kompaktmodus --}}
                <div x-show="compactMode" class="absolute pointer-events-none" style="left:0;right:0;top:{{ $cHideStart * $weekSlotPx }}px;border-top:2px dashed #d1d5db;z-index:3;"></div>

                {{-- Freie Zeitfenster: im Filter als eigene gruene Bloecke --}}
                <template x-if="filterFree">
                    <template x-for="f in freeSlots({{ $resource->id }}, {{ $dayNum }})" :key="f.id">
                        <div :style="freeSlotStyle(f, {{ $weekSlotPx }})"
                             class="pointer-events-none flex items-start justify-center">
                            <span x-show="f.dur >= 4"
                                  style="font-size:9px; font-weight:700; color:#052e16; padding:1px 2px; white-space:nowrap"
                                  x-text="f.label"></span>
                        </div>
                    </template>
                </template>

                {{-- Ausgeblendete Zeit: Sammelmarke statt gequetschter Streifen --}}
                <template x-if="hiddenCount({{ $resource->id }}, {{ $dayNum }}) > 0">
                    <button type="button" @click.stop="compactMode = false"
                            class="absolute flex items-center justify-center gap-0.5 rounded bg-gray-200 hover:bg-gray-300 text-gray-600 border border-gray-300"
                            style="left:2px; right:2px; top:{{ $cHideStart * $weekSlotPx - 9 }}px; height:16px; z-index:5; font-size:9px; font-weight:700"
                            :title="hiddenCount({{ $resource->id }}, {{ $dayNum }}) + ' Belegung(en) zwischen 08:00 und 13:00 – klicken für die Vollansicht'">
                        <span x-text="hiddenCount({{ $resource->id }}, {{ $dayNum }})"></span>
                        <span>×&nbsp;08–13</span>
                    </button>
                </template>

                {{-- Belegungsblöcke (Alpine.js) --}}
                <template x-for="b in weekVisibleBookings({{ $resource->id }}, {{ $dayNum }})" :key="b.id">
                    <div :style="weekBookingStyle(b)"
                         :title="b.display_title + ' · ' + b.start_time + '–' + b.end_time + (b.label && b.label !== b.display_title ? ' · ' + b.label : '')"
                         :class="[bookingOpacity(b), (hasConflict(b) && !filterConflicts) ? 'ring-1 ring-inset ring-red-500' : '']"
                         class="transition-opacity select-none"
                         style="touch-action:none; cursor:grab"
                         @click.stop="openEdit(b)"
                         @pointerdown.stop="startDrag(b, $event)"
                         @pointermove.stop="moveDrag($event)"
                         @pointerup.stop="endDrag($event)"
                         @pointercancel="drag = null">
                        {{-- Beschriftung schon ab 30 Minuten: bei 60 px je Stunde
                             ist dafuer Platz, vorher brauchte es eine volle Stunde --}}
                        <div x-show="b.duration_slots >= 2"
                             style="font-size:10px; font-weight:700; padding:1px 3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; line-height:1.25"
                             x-text="b.display_title"></div>
                        <div x-show="b.duration_slots >= 4"
                             style="font-size:9px; opacity:.85; padding:0 3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; line-height:1.2"
                             x-text="b.start_time + '–' + b.end_time"></div>
                        <span x-show="b.has_missing_trainer" title="Kein Trainer"
                              style="position:absolute; top:3px; right:3px; width:5px; height:5px; border-radius:50%; background:rgba(0,0,0,0.35)"></span>
                        <span x-show="hasConflict(b)"
                              style="position:absolute; top:2px; left:2px; width:5px; height:5px; border-radius:50%; background:#ef4444; opacity:0.9"></span>
                    </div>
                </template>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach

</div>
</div>
</div>
<p class="text-xs text-gray-400 mt-2 text-center">
    Klick auf einen Tagnamen → Tagesdetailansicht &nbsp;·&nbsp; Klick in eine Spalte → neue Belegung &nbsp;·&nbsp; Blöcke verschieben per Drag &amp; Drop
</p>
</div>

{{-- ════════════════════════════════════════════════════════════════════════════
     TAGESANSICHT
     Ressourcen als parallele Spalten, 15-Minuten-Raster, Blöcke proportional.
════════════════════════════════════════════════════════════════════════════ --}}
<div x-show="view==='day'" x-transition>
{{-- Wie in der Wochenansicht: kein overflow-hidden, damit die Kopfzeile
     an der Seite haften kann statt an der Karte. --}}
<div class="bg-white rounded-xl shadow-sm border border-gray-100">

    {{-- Ressourcen-Kopfzeile: bleibt beim Scrollen oben stehen --}}
    <div class="flex sticky bg-white border-b border-gray-200 shadow-sm rounded-t-xl"
         style="padding-left:52px; top:var(--hall-top, 0px); z-index:20">
        @foreach($resources as $resource)
        <div class="flex-1 px-2 py-3 border-l border-gray-100 text-center" style="min-width:110px">
            <div class="flex items-center justify-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:{{ $resource->color }}"></span>
                <span class="text-xs font-semibold text-gray-700 truncate">{{ $resource->name }}</span>
            </div>
            <div class="text-[10px] text-gray-400 mt-0.5">{{ $resource->type_label }}</div>
        </div>
        @endforeach
    </div>

    {{-- Grid: scrollt mit der Seite, nicht in einem eigenen Kasten --}}
    <div x-ref="dayGrid">
        <div class="flex relative"
             :style="compactMode ? 'height:{{ $dayCmptPx }}px' : 'height:{{ $dayTotalPx }}px'">

            {{-- Zeit-Achse: bleibt beim seitlichen Scrollen links stehen --}}
            <div class="flex-shrink-0 bg-gray-50 border-r border-gray-100 relative sticky left-0" style="width:52px; z-index:15">
                @for($h = $scheduleStart + 1; $h <= $scheduleEnd; $h++)
                @php
                    $drs   = (($h * 60) - $scheduleStartMin) / $slotMin;
                    $dFull = intval($drs * $daySlotPx - 6);
                    $dInH  = ($h >= 8 && $h < 13);
                    $dCmpt = intval($cSlotFn($drs) * $daySlotPx - 6);
                @endphp
                <div x-show="!compactMode" class="absolute right-2 text-[10px] text-gray-400 select-none leading-none"
                     style="top:{{ $dFull }}px">{{ sprintf('%02d:00', $h) }}</div>
                @if(!$dInH)
                <div x-show="compactMode" class="absolute right-2 text-[10px] text-gray-400 select-none leading-none"
                     style="top:{{ $dCmpt }}px">{{ sprintf('%02d:00', $h) }}</div>
                @endif
                @endfor
                {{-- Trennlinie im Kompaktmodus --}}
                <div x-show="compactMode" class="absolute pointer-events-none right-0 left-0"
                     style="top:{{ $cHideStart * $daySlotPx }}px; border-top:2px dashed #d1d5db;"></div>
            </div>

            {{-- Horizontale Rasterlinien: Vollansicht --}}
            @for($s = 0; $s < $totalSlots; $s++)
            @php $isFullHour = ($scheduleStartMin + $s * $slotMin) % 60 === 0; @endphp
            <div x-show="!compactMode" class="absolute pointer-events-none"
                 style="left:52px; right:0; top:{{ $s * $daySlotPx }}px;
                        border-top:1px solid {{ $isFullHour ? '#e5e7eb' : '#f9fafb' }}"></div>
            @endfor

            {{-- Horizontale Rasterlinien: Kompaktansicht (08–12 Uhr weggelassen, 13+ verschoben) --}}
            @for($s = 0; $s < $totalSlots; $s++)
            @php
                $sMin    = $scheduleStartMin + $s * $slotMin;
                $sHour   = intdiv($sMin, 60);
                $dInH2   = ($sHour >= 8 && $sHour < 13);
                $dIsHour = $sMin % 60 === 0;
                $dCSlot  = $cSlotFn((float)$s);
                $dCTop   = intval($dCSlot * $daySlotPx);
            @endphp
            @if(!$dInH2)
            <div x-show="compactMode" class="absolute pointer-events-none"
                 style="left:52px; right:0; top:{{ $dCTop }}px;
                        border-top:1px solid {{ $dIsHour ? '#e5e7eb' : '#f9fafb' }}"></div>
            @endif
            @endfor
            {{-- Trennlinie (Tagesansicht) --}}
            <div x-show="compactMode" class="absolute pointer-events-none"
                 style="left:52px; right:0; top:{{ $cHideStart * $daySlotPx }}px; border-top:2px dashed #d1d5db; z-index:5;"></div>

            {{-- Ressourcenspalten --}}
            @foreach($resources as $resource)
            <div class="flex-1 border-l border-gray-100 relative"
                 style="min-width:110px; cursor:crosshair"
                 @click.self="openCreate({{ $resource->id }}, currentDay, Math.floor($event.offsetY / {{ $daySlotPx }}))">

                {{-- Freie Zeitfenster als eigene gruene Bloecke --}}
                <template x-if="filterFree">
                    <template x-for="f in freeSlots({{ $resource->id }}, currentDay)" :key="f.id">
                        <div :style="freeSlotStyle(f, {{ $daySlotPx }})"
                             class="pointer-events-none flex flex-col items-center justify-center text-center">
                            <span style="font-size:11px; font-weight:800; color:#052e16" x-text="f.label"></span>
                            <span x-show="f.dur >= 4" style="font-size:10px; color:#166534" x-text="(f.dur * 15) + ' Min frei'"></span>
                        </div>
                    </template>
                </template>

                {{-- Ausgeblendete Zeit: Sammelmarke statt gequetschter Streifen --}}
                <template x-if="hiddenCount({{ $resource->id }}, currentDay) > 0">
                    <button type="button" @click.stop="compactMode = false"
                            class="absolute flex items-center justify-center gap-1 rounded bg-gray-200 hover:bg-gray-300 text-gray-600 border border-gray-300"
                            style="left:3px; right:3px; top:{{ $cHideStart * $daySlotPx - 11 }}px; height:20px; z-index:5; font-size:10px; font-weight:700"
                            :title="hiddenCount({{ $resource->id }}, currentDay) + ' Belegung(en) zwischen 08:00 und 13:00 – klicken für die Vollansicht'">
                        <span x-text="hiddenCount({{ $resource->id }}, currentDay)"></span>
                        <span>Belegungen 08–13 Uhr</span>
                    </button>
                </template>

                {{-- Belegungsblöcke (Alpine.js, wechseln mit currentDay) --}}
                <template x-for="b in weekVisibleBookings({{ $resource->id }}, currentDay)" :key="b.id">
                    <div :style="dayBookingStyle(b)"
                         :class="[bookingOpacity(b), (hasConflict(b) && !filterConflicts) ? 'ring-2 ring-inset ring-red-500' : '']"
                         class="transition-opacity select-none"
                         style="touch-action:none; cursor:grab"
                         @click.stop="openEdit(b)"
                         @pointerdown.stop="startDrag(b, $event)"
                         @pointermove.stop="moveDrag($event)"
                         @pointerup.stop="endDrag($event)"
                         @pointercancel="drag = null">
                        {{-- Label row --}}
                        <div style="display:flex; align-items:center; gap:3px; padding:3px 7px 0; overflow:hidden">
                            <div style="font-size:11px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1" x-text="b.display_title"></div>
                            <span x-show="hasConflict(b)" title="Überschneidung" style="flex-shrink:0; width:14px; height:14px; border-radius:50%; background:rgba(239,68,68,0.8); display:inline-flex; align-items:center; justify-content:center; font-size:9px; font-weight:900; color:white">!</span>
                            {{-- Missing trainer badge --}}
                            {{-- Dunkler Kreis statt weissem: auf hellen Gruppenfarben
                                 (Weiß/Pink, Gelb) war das Zeichen sonst unsichtbar --}}
                            <span x-show="b.has_missing_trainer && !hasConflict(b)" title="Kein Trainer" style="flex-shrink:0; width:14px; height:14px; border-radius:50%; background:rgba(0,0,0,0.28); display:inline-flex; align-items:center; justify-content:center; font-size:9px; font-weight:900; color:#fff">!</span>
                        </div>
                        {{-- Uhrzeit ab 30 min --}}
                        <div x-show="b.duration_slots >= 2"
                             style="font-size:10px; padding:1px 7px; opacity:0.85"
                             x-text="b.start_time + ' – ' + b.end_time"></div>
                        {{-- Bezeichnung der Belegung, sofern sie etwas anderes sagt
                             als der Titel (dort steht bei Gruppen deren Name) --}}
                        <div x-show="b.duration_slots >= 3 && b.label && b.label !== b.display_title"
                             style="font-size:10px; padding:0 7px; opacity:0.7; white-space:nowrap; overflow:hidden; text-overflow:ellipsis"
                             x-text="b.label"></div>
                        {{-- Linked session icon --}}
                        <div x-show="b.duration_slots >= 4 && b.session_title"
                             style="font-size:9px; padding:0 7px; opacity:0.7; white-space:nowrap; overflow:hidden; text-overflow:ellipsis"
                             x-text="'▶ ' + b.session_title"></div>
                    </div>
                </template>
            </div>
            @endforeach

        </div>
    </div>
</div>
<p class="text-xs text-gray-400 mt-2 text-center">
    Klick in eine Spalte → neue Belegung für diesen Zeitpunkt
</p>
</div>

{{-- ════════════════════════════════════════════════════════════════════════════
     MODAL – Belegung anlegen / bearbeiten
════════════════════════════════════════════════════════════════════════════ --}}
<div x-show="showModal" x-transition.opacity
     class="fixed inset-0 bg-black/40 z-40 flex items-center justify-center p-4"
     @keydown.escape.window="showModal=false">

    <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h2 class="font-bold text-gray-800" x-text="editId ? 'Belegung bearbeiten' : 'Neue Belegung'"></h2>
            <button @click="showModal=false" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="px-6 py-5 space-y-4">

            {{-- Ressourcen --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Ressource(n) <span class="text-red-500">*</span>
                </label>
                <p class="text-xs text-gray-400 mb-2">
                    <span x-show="!editId">Mehrere Auswahlen legen je eine eigene Buchung an.</span>
                    <span x-show="editId">Andere Bahn auswählen, um die Buchung umzubuchen.</span>
                </p>
                <div class="grid grid-cols-2 gap-2">
                    @foreach($resources as $resource)
                    <label class="flex items-center gap-2 text-sm cursor-pointer p-2 rounded-lg border transition-colors"
                           :class="form.hall_resource_ids.includes({{ $resource->id }})
                               ? 'border-primary bg-primary/5'
                               : 'border-gray-200 hover:bg-gray-50'">
                        <input type="checkbox"
                               :value="{{ $resource->id }}"
                               :checked="form.hall_resource_ids.includes({{ $resource->id }})"
                               @change="(editId
                                   ? form.hall_resource_ids = $event.target.checked ? [{{ $resource->id }}] : []
                                   : ($event.target.checked
                                       ? form.hall_resource_ids.push({{ $resource->id }})
                                       : form.hall_resource_ids = form.hall_resource_ids.filter(id => id != {{ $resource->id }})));
                                   form.hall_resource_ids.length ? checkConflicts() : conflicts = []"
                               class="w-4 h-4 rounded text-primary border-gray-300">
                        <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:{{ $resource->color }}"></span>
                        <span class="text-gray-700 truncate">{{ $resource->name }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Wochentag + Uhrzeit --}}
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Wochentag</label>
                    <select x-model.number="form.day_of_week" @change="checkConflicts()"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                        @foreach($days as $num => $name)
                        <option value="{{ $num }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Von <span class="text-red-500">*</span></label>
                    <input type="time" x-model="form.start_time" step="900" @change="checkConflicts()"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bis <span class="text-red-500">*</span></label>
                    <input type="time" x-model="form.end_time" step="900" @change="checkConflicts()"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                </div>
            </div>

            {{-- Konflikte --}}
            <div x-show="conflicts.length > 0" x-transition
                 class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm">
                <p class="font-semibold text-red-700 mb-2 flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    Überschneidung mit bestehenden Belegungen:
                </p>
                <ul class="space-y-2">
                    <template x-for="c in conflicts" :key="c.id">
                        <li class="flex items-center gap-2 bg-red-100/60 rounded-lg px-2 py-1.5">
                            <span class="font-mono text-[11px] bg-white border border-red-200 px-1.5 py-0.5 rounded text-red-700 flex-shrink-0" x-text="c.time"></span>
                            <span class="flex-1 text-red-800 text-xs truncate" x-text="c.resource + ': ' + c.label"></span>
                            <button type="button"
                                    @click="showModal=false; $nextTick(() => { const bk = bookings.find(b => b.id === c.id); if(bk) openEdit(bk); })"
                                    class="flex-shrink-0 text-xs px-2 py-1 bg-white border border-red-300 text-red-600 hover:bg-red-600 hover:text-white rounded font-medium transition-colors">
                                Bearbeiten
                            </button>
                            <button type="button"
                                    @click="deleteBooking(c.id)"
                                    class="flex-shrink-0 text-xs px-2 py-1 bg-red-600 text-white hover:bg-red-700 rounded font-medium transition-colors">
                                Löschen
                            </button>
                        </li>
                    </template>
                </ul>
            </div>

            {{-- Bezeichnung + Typ --}}
            <div class="grid grid-cols-2 gap-3">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bezeichnung <span class="text-red-500">*</span></label>
                    <input type="text" x-model="form.label" placeholder="z.B. SG Wasserratten – Gruppe A"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Typ</label>
                    <select x-model="form.type"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                        <option value="training">Training</option>
                        <option value="course">Kurs</option>
                        <option value="school">Schule</option>
                        <option value="external">Ext. Verein</option>
                        <option value="maintenance">Wartung</option>
                        <option value="other">Sonstiges</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Farbe (optional)</label>
                    <div class="flex gap-2 items-center">
                        <input type="color" x-model="form.color"
                               class="w-10 h-10 rounded-lg border border-gray-300 cursor-pointer p-0.5">
                        <button @click="form.color=''" class="text-xs text-gray-400 hover:text-gray-600">zurücksetzen</button>
                    </div>
                </div>
            </div>

            {{-- Gruppe + Trainer --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Trainingsgruppe</label>
                    <select x-model.number="form.training_group_id" @change="onGroupChange()"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                        <option :value="null">– keine –</option>
                        <template x-for="g in groups" :key="g.id">
                            <option :value="g.id" x-text="g.name"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Trainer</label>
                    <select x-model.number="form.trainer_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 outline-none">
                        <option :value="null">– auto / keiner –</option>
                        <template x-for="t in trainers" :key="t.id">
                            <option :value="t.id" x-text="t.name"></option>
                        </template>
                    </select>
                </div>
            </div>

            {{-- Trainingseinheit verknüpfen --}}
            <div class="border-t border-gray-100 pt-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Trainingseinheit</label>

                {{-- Linked session display --}}
                <div x-show="linkedSession" class="flex items-center gap-2 bg-primary/5 border border-primary/20 rounded-lg px-3 py-2 mb-2 text-sm">
                    <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                    <span class="flex-1 text-primary font-medium truncate" x-text="linkedSession?.title"></span>
                    <button @click="unlinkSession()" type="button" class="text-gray-400 hover:text-red-500 text-xs">Entfernen</button>
                </div>

                <div x-show="!linkedSession" class="space-y-2">
                    <button @click="searchSessions()" type="button"
                            :disabled="sessionSearching"
                            class="text-xs text-primary hover:underline font-medium disabled:opacity-50">
                        <span x-text="sessionSearching ? 'Suche…' : 'Passende Trainingseinheiten suchen'"></span>
                    </button>
                    <div x-show="sessionResults.length > 0" class="border border-gray-200 rounded-lg divide-y max-h-40 overflow-y-auto">
                        <template x-for="s in sessionResults" :key="s.id">
                            <div class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 cursor-pointer" @click="linkSession(s)">
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-gray-800 truncate" x-text="s.title"></p>
                                    <p class="text-[10px] text-gray-400" x-text="s.time + ' · ' + (s.groups || s.trainer || '')"></p>
                                </div>
                                <span x-show="s.recurring" class="text-[10px] text-primary bg-primary/10 px-1.5 rounded">Wiederkehrend</span>
                                <span class="text-xs text-primary font-medium">+ Verknüpfen</span>
                            </div>
                        </template>
                    </div>
                    <p x-show="sessionError" x-cloak class="text-xs text-red-600" role="alert" x-text="sessionError"></p>
                    <p x-show="sessionResults.length === 0 && !sessionSearching && !sessionError" class="text-xs text-gray-400">
                        Keine passenden Einheiten gefunden – oder manuell verknüpfen nach dem Anlegen.
                    </p>
                </div>
            </div>

            {{-- Notizen --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Notizen</label>
                <textarea x-model="form.notes" rows="2"
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-400 outline-none resize-none"
                          placeholder="Anmerkungen, Kontaktperson, etc."></textarea>
            </div>
        </div>

        {{-- Fehler beim Speichern/Loeschen --}}
        <div x-show="formError" x-cloak role="alert"
             class="mx-6 mb-2 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-3 py-2"
             x-text="formError"></div>

        {{-- Footer --}}
        <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between gap-3">
            <button x-show="editId" @click="deleteBooking(editId)"
                    class="text-sm text-red-500 hover:text-red-700 font-medium transition-colors">
                Löschen
            </button>
            <div class="flex gap-3 ml-auto">
                <button @click="showModal=false"
                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                    Abbrechen
                </button>
                <button x-show="conflicts.length > 0" @click="save(true)" :disabled="saving"
                        class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-lg text-sm transition-colors disabled:opacity-60">
                    Trotzdem speichern
                </button>
                <button x-show="conflicts.length === 0" @click="save()" :disabled="saving"
                        class="px-4 py-2 bg-primary hover:bg-primary-dark text-white font-semibold rounded-lg text-sm transition-colors disabled:opacity-60">
                    <span x-text="saving ? 'Speichern…' : 'Speichern'"></span>
                </button>
            </div>
        </div>

    </div>
</div>

</div>{{-- end x-data --}}
@endsection
