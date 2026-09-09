<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintHistory;
use App\Models\ComplaintReason;
use App\Models\User;
use App\Notifications\ComplaintAssignedNotification;
use App\Notifications\ComplaintStatusChangedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplaintController extends Controller
{
    private function eligibleAssignees()
    {
        return User::whereHas('role', fn ($q) => $q->where('name', 'user'))
            ->where('status', 'approved')
            ->orderBy('name')
            ->get();
    }

    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), Complaint::STATUSES, true) ? $request->query('status') : '';
        $priority = in_array($request->query('priority'), Complaint::PRIORITIES, true) ? $request->query('priority') : '';
        $search = mb_substr(trim((string) $request->query('search', '')), 0, 100);
        $reasonId = $request->query('reason_id') ? (int) $request->query('reason_id') : null;
        $assigneeId = $request->query('assignee_id') ? (int) $request->query('assignee_id') : null;
        $sort = in_array($request->query('sort'), ['id', 'priority', 'status', 'created_at', 'updated_at'], true)
            ? $request->query('sort') : 'created_at';
        $order = $request->query('order') === 'asc' ? 'asc' : 'desc';

        $query = Complaint::with(['user', 'reason', 'assignee']);
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($priority !== '') {
            $query->where('priority', $priority);
        }
        if ($reasonId !== null) {
            $query->where('reason_id', $reasonId);
        }
        if ($assigneeId !== null) {
            $query->where('assigned_to', $assigneeId);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('reason', fn ($r) => $r->where('name', 'like', "%{$search}%"));
            });
        }

        $complaints = $query->orderBy($sort, $order)->paginate(20)->withQueryString();

        return view('admin.complaints.index', [
            'complaints' => $complaints,
            'reasons' => ComplaintReason::orderBy('name')->get(),
            'assignees' => $this->eligibleAssignees(),
            'filters' => compact('status', 'priority', 'search', 'reasonId', 'assigneeId', 'sort', 'order'),
        ]);
    }

    public function show(Complaint $complaint): View
    {
        $complaint->load(['user', 'reason', 'assignee', 'attachments', 'history.performer', 'history.assignedFromUser', 'history.assignedToUser']);

        return view('admin.complaints.show', ['complaint' => $complaint, 'assignees' => $this->eligibleAssignees()]);
    }

    public function assign(Request $request, Complaint $complaint): RedirectResponse
    {
        $validated = $request->validate([
            'assignee_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $eligible = $this->eligibleAssignees()->firstWhere('id', (int) $validated['assignee_id']);
        if ($eligible === null) {
            return back()->withErrors(['assignee_id' => 'Assignee is not eligible.']);
        }

        if ((int) $complaint->assigned_to === (int) $validated['assignee_id']) {
            return back()->withErrors(['assignee_id' => 'Complaint is already assigned to this user.']);
        }

        $wasReassignment = $complaint->assigned_to !== null;
        $previousAssignee = $complaint->assigned_to;

        DB::transaction(function () use ($complaint, $validated, $previousAssignee, $request) {
            $complaint->update(['assigned_to' => $validated['assignee_id']]);

            ComplaintHistory::create([
                'complaint_id' => $complaint->id,
                'action' => 'complaint_assigned',
                'assigned_from' => $previousAssignee,
                'assigned_to' => $validated['assignee_id'],
                'performed_by' => $request->user()->id,
                'description' => 'Complaint assignment updated.',
            ]);
        });

        try {
            $eligible->notify(new ComplaintAssignedNotification($complaint->fresh(), $wasReassignment));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('status', 'Complaint assignment updated.');
    }

    public function updateStatus(Request $request, Complaint $complaint): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(Complaint::STATUSES)],
        ]);

        if ($complaint->status === $validated['status']) {
            return back()->withErrors(['status' => 'Complaint already has this status.']);
        }

        $oldStatus = $complaint->status;

        DB::transaction(function () use ($complaint, $validated, $oldStatus, $request) {
            $complaint->update(['status' => $validated['status']]);

            ComplaintHistory::create([
                'complaint_id' => $complaint->id,
                'action' => 'complaint_status_changed',
                'old_status' => $oldStatus,
                'new_status' => $validated['status'],
                'performed_by' => $request->user()->id,
                'description' => 'Complaint status updated.',
            ]);
        });

        try {
            $freshComplaint = $complaint->fresh();
            $freshComplaint->user->notify(new ComplaintStatusChangedNotification($freshComplaint));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return back()->with('status', 'Complaint status updated.');
    }

    public function downloadAttachment(Complaint $complaint, ComplaintAttachment $attachment): StreamedResponse
    {
        if ($attachment->complaint_id !== $complaint->id) {
            abort(404);
        }

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }
}
