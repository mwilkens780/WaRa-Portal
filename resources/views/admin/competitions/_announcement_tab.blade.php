{{-- Tab: Ausschreibung — PDF-Import + strukturierte Anzeige --}}
{{-- Variablen: $competition --}}

<div class="p-5 space-y-6"
     x-data="{
         uploading: false,
         parsed: null,
         pdfPath: null,
         saving: false,
         error: '',

         async uploadAndParse(file) {
             this.uploading = true; this.error = '';
             const fd = new FormData();
             fd.append('announcement_pdf', file);
             fd.append('_token', '{{ csrf_token() }}');
             try {
                 const r = await fetch('{{ route('admin.competitions.announcement.parse', $competition) }}', {
                     method: 'POST', body: fd
                 });
                 const d = await r.json();
                 if (!r.ok) { this.error = d.error || 'Fehler beim Parsen'; return; }
                 this.parsed  = d.data;
                 this.pdfPath = d.pdf_path;
             } catch(e) { this.error = e.message; }
             finally { this.uploading = false; }
         },

         async save(importQT) {
             if (!this.parsed) return;
             this.saving = true;
             const fd = new FormData();
             fd.append('_token', '{{ csrf_token() }}');
             fd.append('pdf_path', this.pdfPath ?? '');
             fd.append('announcement', JSON.stringify(this.parsed));
             if (importQT) fd.append('import_qualifying_times', '1');
             const r = await fetch('{{ route('admin.competitions.announcement.save', $competition) }}', {
                 method: 'POST', body: fd
             });
             if (r.ok) { window.location.reload(); }
             else { this.error = 'Speichern fehlgeschlagen.'; this.saving = false; }
         }
     }">

    {{-- Erfolgs-/Fehlermeldung --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
    @endif

    {{-- ── Bereits gespeicherte Daten ─────────────────────────────────── --}}
    @if($competition->announcement_data || $competition->announcement_pdf_path)
        <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 space-y-3">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <h3 class="font-semibold text-blue-800 text-sm">Gespeicherte Ausschreibungsdaten</h3>
                @if($competition->announcement_pdf_path)
                    <a href="{{ Storage::url($competition->announcement_pdf_path) }}"
                       target="_blank"
                       class="text-xs text-primary hover:underline flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        PDF ansehen
                    </a>
                @endif
            </div>

            @php $ad = $competition->announcement_data ?? []; @endphp

            {{-- Fristen --}}
            @if(!empty($ad['deadlines']))
                <div>
                    <p class="text-xs font-semibold text-blue-700 mb-1.5">Fristen</p>
                    <div class="grid sm:grid-cols-2 gap-1.5">
                        @foreach($ad['deadlines'] as $dl)
                            <div class="flex items-center gap-2 text-xs text-gray-700 bg-white rounded-lg px-3 py-2 border border-blue-100">
                                <span class="font-semibold text-primary">{{ \Carbon\Carbon::parse($dl['date'])->format('d.m.Y') }}@if($dl['time']) {{ $dl['time'] }} Uhr @endif</span>
                                <span class="text-gray-500">{{ $dl['description'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Kontakt + Meldegeld --}}
            @if(!empty($ad['entry']))
            @php $e = $ad['entry']; @endphp
            <div class="grid sm:grid-cols-2 gap-3 text-xs">
                @if(!empty($e['contact_email']))
                <div class="bg-white rounded-lg px-3 py-2 border border-blue-100">
                    <p class="text-gray-400 mb-0.5">Meldeanschrift</p>
                    <p class="font-medium text-gray-700">{{ $e['contact_name'] ?? '' }}</p>
                    <a href="mailto:{{ $e['contact_email'] }}" class="text-primary hover:underline">{{ $e['contact_email'] }}</a>
                </div>
                @endif
                @if(!empty($e['fee_individual_cents']))
                <div class="bg-white rounded-lg px-3 py-2 border border-blue-100">
                    <p class="text-gray-400 mb-0.5">Meldegeld</p>
                    <p class="font-medium text-gray-700">{{ number_format($e['fee_individual_cents'] / 100, 2, ',', '') }} € / Einzelmeldung</p>
                    @if(!empty($e['fee_relay_cents']))
                        <p class="text-gray-500">{{ number_format($e['fee_relay_cents'] / 100, 2, ',', '') }} € / Staffel</p>
                    @endif
                    @if(!empty($e['payment_iban']))
                        <p class="text-gray-400 mt-1 font-mono text-[10px]">{{ $e['payment_iban'] }}</p>
                    @endif
                </div>
                @endif
            </div>
            @endif

            {{-- ENM --}}
            @if(!empty($ad['enm']['cases']))
            <div>
                <p class="text-xs font-semibold text-blue-700 mb-1.5">Erhöhtes Meldegeld (ENM)</p>
                <div class="space-y-1">
                    @foreach($ad['enm']['cases'] as $enm)
                        <div class="text-xs bg-white rounded px-3 py-1.5 border border-blue-100 flex items-start gap-2">
                            <span class="font-semibold text-red-600 shrink-0">{{ number_format(($enm['amount_cents'] ?? 0) / 100, 0, ',', '') }} €</span>
                            <span class="text-gray-600">{{ $enm['description'] ?? '' }}</span>
                            @if(!empty($enm['waiver_condition']))
                                <span class="text-gray-400 shrink-0">— Erlass: {{ $enm['waiver_condition'] }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Besondere Bestimmungen --}}
            @if(!empty($ad['special_rules']))
            <details class="text-xs">
                <summary class="cursor-pointer text-blue-700 font-semibold select-none hover:text-blue-900">
                    {{ count($ad['special_rules']) }} besondere Bestimmungen
                </summary>
                <div class="mt-2 space-y-2">
                    @foreach($ad['special_rules'] as $rule)
                        <div class="bg-white rounded px-3 py-2 border border-blue-100">
                            <p class="font-semibold text-gray-700">{{ $rule['title'] ?? ucfirst($rule['category'] ?? '') }}
                                @if(!empty($rule['is_deviation_from_wb']))
                                    <span class="ml-1 text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded font-normal">Abweichung WB</span>
                                @endif
                            </p>
                            <p class="text-gray-500 mt-0.5 leading-relaxed">{{ $rule['text'] ?? '' }}</p>
                        </div>
                    @endforeach
                </div>
            </details>
            @endif

            {{-- Pflichtzeiten kurz --}}
            @if(!empty($ad['qualifying_times']))
            <details class="text-xs">
                <summary class="cursor-pointer text-blue-700 font-semibold select-none hover:text-blue-900">
                    Qualifikationszeiten (aus Ausschreibung)
                </summary>
                <div class="mt-2 overflow-x-auto">
                    <table class="text-[10px] w-full border-collapse">
                        <thead>
                            <tr class="bg-blue-50">
                                <th class="px-2 py-1 text-left">Strecke</th>
                                @foreach(array_keys(($ad['qualifying_times']['M'] ?? $ad['qualifying_times']['W'] ?? [])) as $year)
                                    <th class="px-2 py-1">{{ $year }} M</th>
                                    <th class="px-2 py-1">{{ $year }} W</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(['50F','100F','200F','400F','50B','100B','200B','50R','100R','200R','50S','100S','200S','200L','400L'] as $disc)
                            <tr class="border-t border-blue-50 hover:bg-blue-50/40">
                                <td class="px-2 py-0.5 font-medium">{{ $disc }}</td>
                                @foreach(array_keys(($ad['qualifying_times']['M'] ?? [])) as $year)
                                    <td class="px-2 py-0.5 font-mono text-center">{{ $ad['qualifying_times']['M'][$year][$disc] ?? '–' }}</td>
                                    <td class="px-2 py-0.5 font-mono text-center">{{ $ad['qualifying_times']['W'][$year][$disc] ?? '–' }}</td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
            @endif
        </div>
    @endif

    {{-- ── PDF-Upload + Parse ──────────────────────────────────────────── --}}
    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
        <h3 class="font-semibold text-gray-800 text-sm mb-2">
            {{ $competition->announcement_data ? 'Ausschreibung aktualisieren' : 'Ausschreibungs-PDF importieren' }}
        </h3>
        <p class="text-xs text-gray-500 mb-4">
            PDF hochladen → Claude extrahiert automatisch: Fristen, Meldeanschrift, Meldegeld, ENM-Regeln,
            Kampfgericht, besondere Bestimmungen und Qualifikationszeiten.
        </p>

        {{-- Upload-Bereich --}}
        <div x-show="!parsed">
            <label class="block">
                <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-primary transition-colors cursor-pointer">
                    <svg class="mx-auto w-8 h-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm text-gray-500">Ausschreibungs-PDF auswählen (max. 20 MB)</p>
                    <input type="file" accept=".pdf" class="hidden"
                           @change="uploadAndParse($event.target.files[0])">
                </div>
            </label>
            <div x-show="uploading" class="mt-3 flex items-center gap-2 text-sm text-gray-500">
                <svg class="animate-spin w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                PDF wird gelesen und mit Claude analysiert…
            </div>
        </div>

        {{-- Ergebnis-Vorschau nach Parse --}}
        <div x-show="parsed" x-cloak>
            <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-3 mb-4 text-sm text-green-700">
                Ausschreibung erfolgreich geparst. Bitte prüfe die erkannten Daten und speichere sie.
            </div>

            {{-- Erkannte Kerndaten --}}
            <div class="grid sm:grid-cols-2 gap-3 text-xs mb-4">
                <div class="bg-white rounded-lg p-3 border border-gray-200">
                    <p class="text-gray-400 mb-0.5">Name</p>
                    <p class="font-semibold text-gray-800" x-text="parsed?.competition?.name ?? '–'"></p>
                </div>
                <div class="bg-white rounded-lg p-3 border border-gray-200">
                    <p class="text-gray-400 mb-0.5">Ebene</p>
                    <p class="font-semibold text-gray-800" x-text="parsed?.competition?.level ?? '–'"></p>
                </div>
                <div class="bg-white rounded-lg p-3 border border-gray-200">
                    <p class="text-gray-400 mb-0.5">Meldeschluss</p>
                    <p class="font-semibold text-gray-800"
                       x-text="parsed?.deadlines?.find(d=>d.type==='meldeschluss_einzel')?.date ?? '–'"></p>
                </div>
                <div class="bg-white rounded-lg p-3 border border-gray-200">
                    <p class="text-gray-400 mb-0.5">Meldegeld</p>
                    <p class="font-semibold text-gray-800"
                       x-text="parsed?.entry?.fee_individual_cents
                           ? (parsed.entry.fee_individual_cents/100).toFixed(2).replace('.',',') + ' €'
                           : '–'"></p>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 items-center">
                <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                    <input type="checkbox" id="importQT" class="rounded border-gray-300 text-primary">
                    Qualifikationszeiten in Wettkampffolge übernehmen
                </label>
                <button @click="save(document.getElementById('importQT').checked)"
                        :disabled="saving"
                        class="bg-primary hover:bg-primary-dark text-white font-semibold px-5 py-2 rounded-lg text-sm transition-colors disabled:opacity-50">
                    <span x-text="saving ? 'Speichern…' : 'Daten speichern'"></span>
                </button>
                <button @click="parsed = null; pdfPath = null"
                        class="px-4 py-2 border border-gray-300 text-gray-600 hover:bg-gray-50 rounded-lg text-sm transition-colors">
                    Verwerfen
                </button>
            </div>
        </div>

        <p x-show="error" class="mt-3 text-sm text-red-600 font-medium" x-text="error"></p>
    </div>
</div>
