@props(['message'])
@php
    $mine = $message->sender_id === auth()->id();
@endphp
{{--
    Structure here MUST stay in sync with the appendMessage() template in
    resources/js/chat.js - that's the only other place a message renders
    (this file handles the initial page load, chat.js handles every message
    that arrives afterward over the WebSocket).
--}}
<div class="chat-message {{ $mine ? 'chat-message--mine' : 'chat-message--theirs' }}" data-message-id="{{ $message->id }}">
    <div class="chat-message-meta">
        <strong>{{ $message->sender->name }}</strong>
        <time>{{ $message->created_at->toDayDateTimeString() }}</time>
    </div>
    <p class="chat-message-text">{{ $message->message }}</p>
</div>
