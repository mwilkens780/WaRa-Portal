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

    // Week view: compact scale
    $weekSlotPx  = 8;                          // px per 15-min slot
    $weekHourPx  = $weekSlotPx * 4;           // 32 px per hour
    $weekTotalPx = $totalSlots * $weekSlotPx; // 528 px
    $weekColPx   = 46;                         // px per resource column in week view

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
        bookings:    _hallBookings,
        daySlotPx:   _daySlotPx,
        weekSlotPx:  _weekSlotPx,

        // ── Modal ──────────────────────────────────────────────────────
        showModal: false,
        editId:    null,
        conflicts: [],
        saving:    false,
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

        // ── Booking block styles (handles drag repositioning + overlap) ───
        weekBookingStyle(b) {
            const rawSlot    = (this.drag?.bookingId === b.id) ? this.drag.currentSlot : b.start_slot;
            const slot       = this._cSlot(rawSlot);
            const durSlots   = this._cDur(rawSlot, b.duration_slots);
            const h          = Math.max(durSlots * this.weekSlotPx - 1, 4);
            const zi         = (this.drag?.bookingId === b.id) ? 10 : 1;
            const { i, n }   = this.overlapLayout(b.id, b.hall_resource_id, b.day_of_week);
            const pct        = 100 / n;
            return `position:absolute; top:${slot*this.weekSlotPx}px; height:${h}px; `
                 + `left:calc(${i*pct}% + 1px); width:calc(${pct}% - 2px); `
                 + `border-radius:3px; background-color:${b.display_color}; z-index:${zi}; overflow:hidden;`;
        },
        dayBookingStyle(b) {
            const rawSlot    = (this.drag?.bookingId === b.id) ? this.drag.currentSlot : b.start_slot;
            const slot       = this._cSlot(rawSlot);
            const durSlots   = this._cDur(rawSlot, b.duration_slots);
            const h          = Math.max(durSlots * this.daySlotPx - 2, 4);
            const zi         = (this.drag?.bookingId === b.id) ? 10 : 2;
            const { i, n }   = this.overlapLayout(b.id, b.hall_resource_id, b.day_of_week);
            const pct        = 100 / n;
            return `position:absolute; top:${slot*this.daySlotPx+1}px; height:${h}px; `
                 + `left:calc(${i*pct}% + 2px); width:calc(${pct}% - 4px); `
                 + `border-radius:6px; background-color:${b.display_color}; z-index:${zi}; overflow:hidden;`;
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
            booking.start_time = st; booking.end_time = et; booking.start_slot = ds.currentSlot;

            const r = await fetch(`/trainer/hall/bookings/${ds.bookingId}`, {
                method: 'PUT',
                headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept':'application/json' },
                body: JSON.stringify({
                    day_of_week: booking.day_of_week, start_time: st, end_time: et,
                    label: booking.label, type: booking.type,
                    training_group_id: booking.training_group_id, trainer_id: booking.trainer_id,
                    training_session_id: booking.training_session_id,
                    notes: booking.notes ?? '', force: true,
                }),
            });

            if (!r.ok) { window.location.reload(); }
        },

        // ── Bookings for a given resource + day ───────────────────────
        dayResourceBookings(resourceId, day) {
            return this.bookings.filter(b => b.hall_resource_id === resourceId && b.day_of_week === day);
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
            try {
                const p = new URLSearchParams({ day_of_week: this.form.day_of_week, start_time: this.form.start_time, end_time: this.form.end_time });
                const r = await fetch(`/trainer/hall/sessions/search?${p}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                this.sessionResults = (await r.json()).sessions ?? [];
            } finally { this.sessionSearching = false; }
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
            try {
                const r = await fetch(`/trainer/hall/conflicts?${p}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                this.conflicts = (await r.json()).conflicts ?? [];
            } catch {}
        },
        async save() {
            this.saving = true;
            try {
                const url    = this.editId ? `/trainer/hall/bookings/${this.editId}` : '/trainer/hall/bookings';
                const method = this.editId ? 'PUT' : 'POST';
                const r = await fetch(url, {
                    method,
                    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept':'application/json' },
                    body: JSON.stringify({ ...this.form, force: true }),
                });
                if (r.ok) { window.location.reload(); }
            } finally { this.saving = false; }
        },
        async deleteBooking(id) {
            if (!confirm('Belegung löschen?')) return;
            await fetch(`/trainer/hall/bookings/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept':'application/json' },
            });
            window.location.reload();
        },

        get dayName() { return ['','Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'][this.currentDay]; },

        compactMode: false,
    };
}
</script>

<div class="mt-2" x-data="hallApp()">

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

    <button @click="openCreate(null, view==='day' ? currentDay : 1, null)"
            class="ml-auto flex items-center gap-2 bg-primary hover:bg-primary-dark text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        Neue Belegung
    </button>
</div>

{{-- ════════════════════════════════════════════════════════════════════════════
     WOCHENANSICHT
     Ressourcen als parallele Spalten, alle 7 Tage nebeneinander,
     Blöcke proportional zur Dauer.
════════════════════════════════════════════════════════════════════════════ --}}
<div x-show="view==='week'" x-transition>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
<div class="overflow-x-auto">
<div class="inline-flex" style="min-width: max-content">

    {{-- Zeit-Achse --}}
    <div class="flex-shrink-0 bg-gray-50 border-r border-gray-200 z-10" style="width:44px">
        {{-- Doppel-Header (Tagesname + Ressourcen) --}}
        <div class="border-b border-gray-100" style="height:52px"></div>
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

    {{-- 7 Tagesspalten --}}
    @foreach($days as $dayNum => $dayName)
    <div class="flex-shrink-0 border-l border-gray-200"{{ $dayNum == 7 ? ' x-show="!compactMode"' : '' }}>

        {{-- Tageskopf --}}
        <div class="bg-gray-50 border-b border-gray-100" style="height:52px">
            {{-- Tagesname --}}
            <button @click="view='day'; currentDay={{ $dayNum }}"
                    class="w-full text-center text-xs font-bold text-gray-700 hover:text-primary py-1.5 transition-colors"
                    style="width:{{ count($resources) * $weekColPx }}px">
                {{ $dayName }}
            </button>
            {{-- Ressourcen-Kurznamen --}}
            <div class="flex">
                @foreach($resources as $resource)
                <div class="text-center border-l border-gray-100 first:border-0"
                     style="width:{{ $weekColPx }}px">
                    <span class="text-[9px] font-bold uppercase tracking-wide"
                          style="color:{{ $resource->color }}">
                        {{ $abbrevFn($resource->name) }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>

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

                {{-- Freie-Kapazitäten-Overlay --}}
                <div x-show="filterFree" class="absolute inset-0 pointer-events-none"
                     style="background:rgba(134,239,172,0.15); z-index:0"></div>

                {{-- Belegungsblöcke (Alpine.js) --}}
                <template x-for="b in dayResourceBookings({{ $resource->id }}, {{ $dayNum }})" :key="b.id">
                    <div :style="weekBookingStyle(b)"
                         :class="[bookingOpacity(b), hasConflict(b) ? 'ring-1 ring-inset ring-red-500' : '']"
                         class="transition-opacity select-none"
                         style="touch-action:none; cursor:grab"
                         @click.stop="openEdit(b)"
                         @pointerdown.stop="startDrag(b, $event)"
                         @pointermove.stop="moveDrag($event)"
                         @pointerup.stop="endDrag($event)"
                         @pointercancel="drag = null">
                        <div x-show="b.duration_slots >= 4"
                             style="font-size:8px; color:white; font-weight:700; padding:1px 2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; line-height:1.2"
                             x-text="b.label"></div>
                        <span x-show="b.has_missing_trainer"
                              style="position:absolute; top:2px; right:2px; width:5px; height:5px; border-radius:50%; background:white; opacity:0.75"></span>
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
<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">

    {{-- Ressourcen-Kopfzeile --}}
    <div class="flex sticky top-0 z-10 bg-white border-b border-gray-200 shadow-sm" style="padding-left:52px">
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

    {{-- Grid --}}
    <div class="overflow-y-auto" style="max-height:72vh" x-ref="dayGrid">
        <div class="flex relative overflow-hidden"
             :style="compactMode ? 'height:{{ $dayCmptPx }}px' : 'height:{{ $dayTotalPx }}px'">

            {{-- Zeit-Achse --}}
            <div class="flex-shrink-0 bg-gray-50 border-r border-gray-100 relative" style="width:52px">
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

                {{-- Freie-Kapazitäten-Overlay --}}
                <div x-show="filterFree" class="absolute inset-0 pointer-events-none"
                     style="background:rgba(134,239,172,0.12); z-index:0"></div>

                {{-- Belegungsblöcke (Alpine.js, wechseln mit currentDay) --}}
                <template x-for="b in dayResourceBookings({{ $resource->id }}, currentDay)" :key="b.id">
                    <div :style="dayBookingStyle(b)"
                         :class="[bookingOpacity(b), hasConflict(b) ? 'ring-2 ring-inset ring-red-500' : '']"
                         class="shadow-sm transition-opacity text-white select-none"
                         style="touch-action:none; cursor:grab"
                         @click.stop="openEdit(b)"
                         @pointerdown.stop="startDrag(b, $event)"
                         @pointermove.stop="moveDrag($event)"
                         @pointerup.stop="endDrag($event)"
                         @pointercancel="drag = null">
                        {{-- Label row --}}
                        <div style="display:flex; align-items:center; gap:3px; padding:3px 7px 0; overflow:hidden">
                            <div style="font-size:11px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1" x-text="b.label"></div>
                            <span x-show="hasConflict(b)" title="Überschneidung" style="flex-shrink:0; width:14px; height:14px; border-radius:50%; background:rgba(239,68,68,0.8); display:inline-flex; align-items:center; justify-content:center; font-size:9px; font-weight:900; color:white">!</span>
                            {{-- Missing trainer badge --}}
                            <span x-show="b.has_missing_trainer && !hasConflict(b)" title="Kein Trainer" style="flex-shrink:0; width:14px; height:14px; border-radius:50%; background:rgba(255,255,255,0.35); display:inline-flex; align-items:center; justify-content:center; font-size:9px; font-weight:900; color:white">!</span>
                        </div>
                        {{-- Uhrzeit ab 30 min --}}
                        <div x-show="b.duration_slots >= 2"
                             style="font-size:10px; padding:1px 7px; opacity:0.85"
                             x-text="b.start_time + ' – ' + b.end_time"></div>
                        {{-- Gruppe ab 45 min --}}
                        <div x-show="b.duration_slots >= 3 && b.group_name"
                             style="font-size:10px; padding:0 7px; opacity:0.7; white-space:nowrap; overflow:hidden; text-overflow:ellipsis"
                             x-text="b.group_name"></div>
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
                               @change="editId
                                   ? form.hall_resource_ids = $event.target.checked ? [{{ $resource->id }}] : []
                                   : ($event.target.checked
                                       ? form.hall_resource_ids.push({{ $resource->id }})
                                       : form.hall_resource_ids = form.hall_resource_ids.filter(id => id != {{ $resource->id }}))"
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
                    <p x-show="sessionResults.length === 0 && !sessionSearching" class="text-xs text-gray-400">
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
                <button x-show="conflicts.length > 0" @click="save()" :disabled="saving"
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
