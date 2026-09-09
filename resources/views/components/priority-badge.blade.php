@props(['priority'])
@php
    $slug = strtolower($priority);
    $icons = ['LOW' => 'minus', 'MEDIUM' => 'alert-circle', 'HIGH' => 'alert-triangle'];
    $iconName = $icons[$priority] ?? 'circle';
@endphp
<span class="priority-badge priority-badge--{{ $slug }}">
    <x-icon :name="$iconName" class="icon badge-icon" />{{ $priority }}
</span>
