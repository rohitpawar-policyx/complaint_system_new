<x-layouts.app title="Complaint #{{ $complaint->id }} | Complaint Management System">
    <section class="page-heading">
        <p class="eyebrow">Complaint details</p>
        <h1>Complaint #{{ $complaint->id }}</h1>
        <p><a href="{{ route('complaints.index') }}"><x-icon name="arrow-left" /> Back to my complaints</a></p>
    </section>
    <div class="details-layout">
        <section class="content-card">
            <h2>Complaint information</h2>
            <dl class="account-details">
                <div><dt>Reason</dt><dd>{{ $complaint->reason->name }}</dd></div>
                <div><dt>Payment proof</dt><dd>
                    @if ($complaint->payment_proof_path)
                        <x-image-preview :src="route('complaints.payment-proof.download', $complaint)" alt="Payment proof" />
                    @else
                        <span class="list-meta">Not submitted</span>
                    @endif
                </dd></div>
                <div><dt>Priority</dt><dd><x-priority-badge :priority="$complaint->priority" /></dd></div>
                <div><dt>Status</dt><dd><x-status-badge :status="$complaint->status" /></dd></div>
                <div><dt>Created</dt><dd>{{ $complaint->created_at }}</dd></div>
                <div><dt>Updated</dt><dd>{{ $complaint->updated_at }}</dd></div>
            </dl>
            <h2>Message</h2>
            <p class="complaint-message">{!! nl2br(e($complaint->message)) !!}</p>
        </section>
        <section class="content-card">
            <h2>Attachments</h2>
            @if ($complaint->attachments->isEmpty())
                <p>No attachments were submitted.</p>
            @else
                <ul class="attachment-list">
                    @foreach ($complaint->attachments as $attachment)
                        <li>
                            @if (str_starts_with($attachment->mime_type, 'image/'))
                                <x-image-preview :src="route('complaints.attachments.download', [$complaint, $attachment])" :alt="$attachment->original_name" />
                            @else
                                <a href="{{ route('complaints.attachments.download', [$complaint, $attachment]) }}"><x-icon name="download" />{{ $attachment->original_name }}</a>
                            @endif
                            <span>{{ number_format($attachment->file_size) }} bytes</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
    <x-chat-panel
        :complaint="$complaint"
        :send-url="route('complaints.chat.store', $complaint)"
        :show="$chatAvailable"
        :can-send="$chatAvailable && !$chatReadOnly"
        unavailable-message="Chat becomes available once your complaint is being reviewed." />
    <section class="content-card history-card">
        <h2>History</h2>
        @if ($complaint->history->isEmpty())
            <p>No history is available.</p>
        @else
            <ol class="history-list">
                @foreach ($complaint->history as $entry)
                    <li>
                        <div class="history-entry-heading">
                            <strong>{{ $entry->action }}</strong>
                            <time>{{ $entry->created_at }}</time>
                        </div>
                        <p>{{ $entry->description }}</p>
                        @if ($entry->old_status !== null || $entry->new_status !== null)
                            <p>Status: {{ $entry->old_status ?? 'none' }} to {{ $entry->new_status ?? 'none' }}</p>
                        @endif
                        <p>Performed by {{ $entry->performer->name }}</p>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
    @if ($chatAvailable)
        @push('scripts')
            @vite('resources/js/chat.js')
        @endpush
    @endif
</x-layouts.app>
