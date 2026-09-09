<x-layouts.admin title="Complaint #{{ $complaint->id }}">
    <section class="page-heading">
        <p class="eyebrow">Complaint management</p>
        <h1>Complaint #{{ $complaint->id }}</h1>
        <p><a href="{{ route('admin.complaints.index') }}"><x-icon name="arrow-left" /> Back to complaints</a></p>
    </section>
    <div class="details-layout">
        <section class="content-card">
            <h2>Complaint information</h2>
            <dl class="account-details">
                <div><dt>Complainant</dt><dd>{{ $complaint->user->name }}</dd></div>
                <div><dt>Email</dt><dd>{{ $complaint->user->email }}</dd></div>
                <div><dt>Reason</dt><dd>{{ $complaint->reason->name }}</dd></div>
                <div><dt>Priority</dt><dd><x-priority-badge :priority="$complaint->priority" /></dd></div>
                <div><dt>Status</dt><dd><x-status-badge :status="$complaint->status" /></dd></div>
                <div><dt>Assignee</dt><dd>{{ $complaint->assignee->name ?? 'Unassigned' }}</dd></div>
                <div><dt>Created</dt><dd>{{ $complaint->created_at }}</dd></div>
                <div><dt>Updated</dt><dd>{{ $complaint->updated_at }}</dd></div>
            </dl>
            <h2>Message</h2>
            <p class="complaint-message">{!! nl2br(e($complaint->message)) !!}</p>
        </section>
        <section class="content-card">
            <h2>Manage complaint</h2>
            <form method="post" action="{{ route('admin.complaints.assign', $complaint) }}" class="admin-form" data-confirm="Update the assignment for this complaint?">
                @csrf
                <label for="assignee_id">Assign to</label>
                <select id="assignee_id" name="assignee_id" required>
                    <option value="">Select approved user</option>
                    @foreach ($assignees as $assignee)
                        <option value="{{ $assignee->id }}" @selected($complaint->assigned_to === $assignee->id)>{{ $assignee->name }} ({{ $assignee->email }})</option>
                    @endforeach
                </select>
                <button type="submit"><x-icon name="user-check" /> Save assignment</button>
            </form>
            <hr>
            <form method="post" action="{{ route('admin.complaints.status', $complaint) }}" class="admin-form" data-confirm="Change the status of this complaint?">
                @csrf
                @method('PATCH')
                <label for="new_status">Change status</label>
                <select id="new_status" name="status" required>
                    @foreach (\App\Models\Complaint::STATUSES as $option)
                        <option value="{{ $option }}" @selected($complaint->status === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                <button type="submit"><x-icon name="save" /> Save status</button>
            </form>
        </section>
    </div>
    <section class="content-card history-card">
        <h2>Attachments</h2>
        @if ($complaint->attachments->isEmpty())
            <p>No attachments were submitted.</p>
        @else
            <ul class="attachment-list">
                @foreach ($complaint->attachments as $attachment)
                    <li>
                        <a href="{{ route('admin.complaints.attachments.download', [$complaint, $attachment]) }}"><x-icon name="download" />{{ $attachment->original_name }}</a>
                        <span>{{ number_format($attachment->file_size) }} bytes</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
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
                        @if ($entry->assigned_from !== null || $entry->assigned_to !== null)
                            <p>Assignment: {{ $entry->assignedFromUser->name ?? 'Unassigned' }} to {{ $entry->assignedToUser->name ?? 'Unassigned' }}</p>
                        @endif
                        <p>Performed by {{ $entry->performer->name }}</p>
                    </li>
                @endforeach
            </ol>
        @endif
    </section>
</x-layouts.admin>
