<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintHistory;
use App\Models\ComplaintReason;
use App\Notifications\ComplaintCreatedNotification;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplaintController extends Controller
{
    public function create(): View
    {
        return view('complaints.create', ['reasons' => ComplaintReason::active()->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reason_id' => ['required', 'integer', 'exists:complaint_reasons,id'],
            'message' => ['required', 'string', 'max:10000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], // 5 MiB
        ]);

        $reason = ComplaintReason::where('id', $validated['reason_id'])->where('active', true)->first();
        if ($reason === null) {
            return back()->withErrors(['reason_id' => 'The selected complaint reason is unavailable.'])->withInput();
        }

        $storedFiles = [];

        try {
            $complaint = DB::transaction(function () use ($request, $reason, &$storedFiles) {
                $complaint = Complaint::create([
                    'user_id' => $request->user()->id,
                    'reason_id' => $reason->id,
                    'message' => $request->input('message'),
                    'priority' => $reason->priority,
                    'status' => 'pending',
                ]);

                ComplaintHistory::create([
                    'complaint_id' => $complaint->id,
                    'action' => 'complaint_created',
                    'old_status' => null,
                    'new_status' => 'pending',
                    'performed_by' => $request->user()->id,
                    'description' => 'Complaint created.',
                ]);

                foreach ($request->file('attachments', []) as $file) {
                    $storedName = Str::random(32) . '.' . strtolower($file->getClientOriginalExtension());
                    $path = $file->storeAs('complaint-attachments', $storedName, 'local');
                    $storedFiles[] = $path;

                    ComplaintAttachment::create([
                        'complaint_id' => $complaint->id,
                        'original_name' => $file->getClientOriginalName(),
                        'stored_name' => $storedName,
                        'file_path' => $path,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }

                return $complaint;
            });
        } catch (\Throwable $exception) {
            foreach ($storedFiles as $path) {
                Storage::disk('local')->delete($path);
            }
            report($exception);

            return back()->withErrors(['message' => 'The complaint could not be submitted right now.'])->withInput();
        }

        // Notification is created only after the transaction has committed
        // successfully; a failure here must never undo the complaint.
        try {
            $admins = User::whereHas('role', fn ($q) => $q->where('name', 'admin'))
                ->where('status', 'approved')
                ->get();
            foreach ($admins as $admin) {
                $admin->notify(new ComplaintCreatedNotification($complaint));
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return redirect()->route('complaints.create')
            ->with('status', 'Complaint submitted successfully.');
    }

    public function index(Request $request): View
    {
        $userId = $request->user()->id;
        $status = in_array($request->query('status'), Complaint::STATUSES, true) ? $request->query('status') : '';
        $priority = in_array($request->query('priority'), Complaint::PRIORITIES, true) ? $request->query('priority') : '';
        $sort = in_array($request->query('sort'), ['created_at', 'updated_at', 'status', 'priority'], true)
            ? $request->query('sort') : 'created_at';
        $order = $request->query('order') === 'asc' ? 'asc' : 'desc';

        $query = Complaint::where('user_id', $userId)->with('reason');
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($priority !== '') {
            $query->where('priority', $priority);
        }

        $complaints = $query->orderBy($sort, $order)->paginate(20)->withQueryString();

        return view('complaints.index', [
            'complaints' => $complaints,
            'status' => $status,
            'priority' => $priority,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    public function show(Request $request, Complaint $complaint): View
    {
        // Ownership check: this complaint must belong to the current user.
        if ($complaint->user_id !== $request->user()->id) {
            abort(404);
        }

        $complaint->load(['reason', 'attachments', 'history.performer', 'history.assignedFromUser', 'history.assignedToUser']);

        return view('complaints.show', ['complaint' => $complaint]);
    }

    public function downloadAttachment(Request $request, Complaint $complaint, ComplaintAttachment $attachment): StreamedResponse
    {
        if ($complaint->user_id !== $request->user()->id) {
            abort(404);
        }
        if ($attachment->complaint_id !== $complaint->id) {
            abort(404);
        }

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }
}
