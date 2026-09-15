@props(['complaint', 'sendUrl', 'show', 'canSend', 'unavailableMessage' => null])
<section class="content-card chat-card">
    <h2><x-icon name="message-circle" /> Chat</h2>
    @if (!$show)
        <p class="chat-unavailable">{{ $unavailableMessage }}</p>
    @else
        @php $messages = $complaint->chatConversation->messages ?? collect(); @endphp
        <div class="chat-panel" data-chat
            data-complaint-id="{{ $complaint->id }}"
            data-send-url="{{ $sendUrl }}"
            data-csrf-token="{{ csrf_token() }}"
            data-current-user-id="{{ auth()->id() }}"
            data-reverb-key="{{ config('reverb.apps.apps.0.key') }}"
            data-reverb-host="{{ config('reverb.apps.apps.0.options.host') }}"
            data-reverb-port="{{ config('reverb.apps.apps.0.options.port') }}"
            data-reverb-scheme="{{ config('reverb.apps.apps.0.options.scheme') }}">
            <p class="chat-error" data-chat-error hidden></p>
            <div class="chat-messages" data-chat-messages>
                @forelse ($messages as $message)
                    <x-chat-message :message="$message" />
                @empty
                    <p class="chat-empty-state">No messages yet.</p>
                @endforelse
            </div>
            @if ($canSend)
                <form data-chat-form class="chat-form">
                    <textarea name="message" rows="2" maxlength="2000" required placeholder="Type a message…" aria-label="Message"></textarea>
                    <button type="submit"><x-icon name="send" /> Send</button>
                </form>
            @else
                <p class="chat-unavailable">This complaint is closed — chat is read-only.</p>
            @endif
        </div>
    @endif
</section>
