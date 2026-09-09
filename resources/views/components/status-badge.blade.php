@props(['status'])
@php
    $slug = str_replace('_', '-', strtolower($status));
    $icons = [
        'pending' => 'clock', 'approved' => 'check-circle', 'active' => 'check-circle',
        'resolved' => 'check-circle', 'blocked' => 'ban', 'rejected' => 'x-circle',
        'in_progress' => 'refresh-cw', 'closed' => 'archive', 'inactive' => 'minus-circle',
    ];
    $iconName = $icons[$status] ?? 'circle';
@endphp
<span class="status-badge status-badge--{{ $slug }}">
    <x-icon :name="$iconName" class="icon badge-icon" />{{ $status }}
</span>
