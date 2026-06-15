@extends('layouts.app')
@section('title', 'Berechtigungs-Matrix')
@section('page-title', 'Berechtigungs-Matrix')

@section('content')
@php
    $sections = ['general' => 'Allgemein', 'trainer' => 'Trainer-Bereich', 'swimmer' => 'Schwimmer', 'parent' => 'Eltern'];
    $roleLabels = \App\Models\User::ROLE_LABELS;
    $editableRoles = array_filter(array_keys($roleLabels), fn($r) => $r !== 'admin');
@endphp
<div class="mt-2">
    <p class="text-sm text-gray-500 mb-4">Der <strong>Administrator</strong> hat immer Zugriff auf alle Bereiche. Die Matrix gilt für alle anderen Rollen.</p>

    <form method="POST" action="{{ route('admin.permissions.update') }}">
        @csrf @method('PUT')

        @foreach($sections as $sectionKey => $sectionLabel)
        @php $sectionItems = array_filter($items, fn($i) => $i['section'] === $sectionKey); @endphp
        @if(count($sectionItems))
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-4 overflow-hidden">
            <div class="px-5 py-3 bg-gray-50 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">{{ $sectionLabel }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left px-5 py-2.5 font-medium text-gray-600 w-48">Bereich</th>
                            @foreach($editableRoles as $role)
                                <th class="px-4 py-2.5 font-medium text-gray-600 text-center">{{ $roleLabels[$role] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($sectionItems as $key => $item)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-5 py-3 text-gray-800 font-medium">{{ $item['label'] }}</td>
                            @foreach($editableRoles as $role)
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox"
                                       name="permissions[{{ $role }}][{{ $key }}]"
                                       value="1"
                                       {{ ($matrix[$role][$key] ?? false) ? 'checked' : '' }}
                                       class="w-4 h-4 rounded text-primary border-gray-300 focus:ring-blue-500 cursor-pointer">
                            </td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        @endforeach

        <div class="flex gap-3">
            <button type="submit"
                    class="bg-primary hover:bg-primary-dark text-white font-semibold px-6 py-2.5 rounded-lg transition-colors">
                Berechtigungen speichern
            </button>
        </div>
    </form>
</div>
@endsection
