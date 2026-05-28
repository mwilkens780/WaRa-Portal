<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Season;
use Illuminate\Http\Request;

class CalendarEventController extends Controller
{
    public function create(Request $request)
    {
        $seasons     = Season::orderByDesc('start_date')->get();
        $defaultDate = $request->get('date');
        return view('calendar.events.create', compact('seasons', 'defaultDate'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['nullable', 'date', 'gte:start_date'],
            'start_time'  => ['nullable', 'date_format:H:i'],
            'end_time'    => ['nullable', 'date_format:H:i'],
            'type'        => ['required', 'in:' . implode(',', array_keys(CalendarEvent::TYPES))],
            'season_id'   => ['nullable', 'exists:seasons,id'],
        ]);

        $data['created_by'] = auth()->id();
        CalendarEvent::create($data);

        $back = $request->input('return_to', route('calendar.index'));
        return redirect($back)->with('success', "Termin \"{$data['title']}\" angelegt.");
    }

    public function edit(CalendarEvent $calendarEvent)
    {
        $seasons = Season::orderByDesc('start_date')->get();
        return view('calendar.events.edit', compact('calendarEvent', 'seasons'));
    }

    public function update(Request $request, CalendarEvent $calendarEvent)
    {
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['nullable', 'date', 'gte:start_date'],
            'start_time'  => ['nullable', 'date_format:H:i'],
            'end_time'    => ['nullable', 'date_format:H:i'],
            'type'        => ['required', 'in:' . implode(',', array_keys(CalendarEvent::TYPES))],
            'season_id'   => ['nullable', 'exists:seasons,id'],
        ]);

        $calendarEvent->update($data);

        return redirect()->route('calendar.index')->with('success', "Termin \"{$calendarEvent->title}\" gespeichert.");
    }

    public function destroy(CalendarEvent $calendarEvent)
    {
        $title = $calendarEvent->title;
        $calendarEvent->delete();
        return back()->with('success', "Termin \"{$title}\" gelöscht.");
    }
}
