<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageSent;
use App\Models\ChatConversation;
use App\Models\Complaint;
use App\Models\ComplaintAttachment;
use App\Models\ComplaintHistory;
use App\Models\ComplaintReason;
use App\Notifications\ComplaintCreatedNotification;
use App\Models\User;
use App\Services\PaymentProofOcrService;
use Illuminate\Http\JsonResponse;
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
            // Image only (not pdf, unlike the general attachments below) -
            // Tesseract reads pixels, not PDF pages, and keeping OCR to a
            // single simple input format matches the "keep it simple, this
            // is a learning implementation" brief.
            'payment_proof' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'], // 5 MiB
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], // 5 MiB
        ]);

        $reason = ComplaintReason::where('id', $validated['reason_id'])->where('active', true)->first();
        if ($reason === null) {
            return back()->withErrors(['reason_id' => 'The selected complaint reason is unavailable.'])->withInput();
        }

        $storedFiles = [];

        // Stored and OCR'd *before* the DB transaction: OCR shells out to
        // an external process that can take a second or more, and holding
        // a DB transaction open for that long is bad practice regardless
        // of how reliable the process is. The file is added to
        // $storedFiles up front so the catch block below still cleans it
        // up if anything later in the request fails.
        $paymentProofFile = $request->file('payment_proof');
        $paymentProofStoredName = Str::random(32) . '.' . strtolower($paymentProofFile->getClientOriginalExtension());
        $paymentProofPath = $paymentProofFile->storeAs('payment-proofs', $paymentProofStoredName, 'local');
        $storedFiles[] = $paymentProofPath;

        // Never throws - see PaymentProofOcrService. A missing/broken
        // Tesseract install or an unreadable image both just resolve to
        // ocr_status=failed with transaction_id left null, exactly like a
        // genuinely clean image OCR ran on but found no label match.
        $ocrResult = app(PaymentProofOcrService::class)
            ->process(Storage::disk('local')->path($paymentProofPath));

        try {
            $complaint = DB::transaction(function () use ($request, $reason, $paymentProofPath, $ocrResult, &$storedFiles) {
                $complaint = Complaint::create([
                    'user_id' => $request->user()->id,
                    'reason_id' => $reason->id,
                    'message' => $request->input('message'),
                    'priority' => $reason->priority,
                    'status' => 'pending',
                    'payment_proof_path' => $paymentProofPath,
                    'transaction_id' => $ocrResult['transaction_id'],
                    'ocr_status' => $ocrResult['status'],
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

        $status = 'Complaint submitted successfully. '.($complaint->ocr_status === 'extracted'
            ? 'Payment transaction ID detected successfully.'
            : 'Your payment proof has been uploaded. The payment details will be verified during complaint processing.');

        return redirect()->route('complaints.create')->with('status', $status);
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

        $complaint->load([
            'reason', 'attachments', 'history.performer', 'history.assignedFromUser', 'history.assignedToUser',
            'chatConversation.messages.sender',
        ]);

        return view('complaints.show', [
            'complaint' => $complaint,
            'chatAvailable' => $complaint->status !== 'pending',
            'chatReadOnly' => in_array($complaint->status, Complaint::CHAT_READ_ONLY_STATUSES, true),
        ]);
    }

    /**
     * Send a chat message on this complaint. The conversation is created
     * lazily on the first authorized message rather than via a separate
     * "start chat" step - simpler, and there's never a conversation with
     * zero messages sitting in an awkward half-started state.
     */
    public function storeChatMessage(Request $request, Complaint $complaint): JsonResponse
    {
        if ($complaint->user_id !== $request->user()->id) {
            abort(404);
        }

        if ($complaint->status === 'pending') {
            abort(403, 'Chat is not available while your complaint is pending.');
        }

        if (in_array($complaint->status, Complaint::CHAT_READ_ONLY_STATUSES, true)) {
            abort(403, 'This complaint is closed - chat is read-only.');
        }

        // Normalize before validating so a whitespace-only message is
        // correctly rejected by the "required" rule, not stored as blank.
        $request->merge(['message' => trim((string) $request->input('message', ''))]);
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $conversation = $complaint->chatConversation ?? ChatConversation::create([
            'complaint_id' => $complaint->id,
            'created_by' => $request->user()->id,
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'message' => $validated['message'],
        ]);

        // Broadcast to everyone on the channel, including this sender - the
        // frontend renders every message (its own included) purely from the
        // WebSocket event, so there's exactly one rendering code path rather
        // than an "optimistic render + reconcile with the echo" dance.
        broadcast(new ChatMessageSent($message));

        return response()->json(['success' => true]);
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

    public function downloadPaymentProof(Request $request, Complaint $complaint): StreamedResponse
    {
        if ($complaint->user_id !== $request->user()->id) {
            abort(404);
        }

        return Storage::disk('local')->download($complaint->payment_proof_path);
    }
}
