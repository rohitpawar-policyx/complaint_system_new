<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ComplaintHistory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ComplaintHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $complaintId = $request->query('complaint_id') ? (int) $request->query('complaint_id') : null;
        $action = mb_substr(trim((string) $request->query('action', '')), 0, 50);
        $performer = mb_substr(trim((string) $request->query('performer', '')), 0, 100);
        $sort = in_array($request->query('sort'), ['created_at', 'complaint_id'], true) ? $request->query('sort') : 'created_at';
        $order = $request->query('order') === 'asc' ? 'asc' : 'desc';

        $query = ComplaintHistory::with(['performer', 'assignedFromUser', 'assignedToUser']);
        if ($complaintId !== null) {
            $query->where('complaint_id', $complaintId);
        }
        if ($action !== '') {
            $query->where('action', 'like', "%{$action}%");
        }
        if ($performer !== '') {
            $query->whereHas('performer', fn ($q) => $q->where('name', 'like', "%{$performer}%"));
        }

        $history = $query->orderBy($sort, $order)->paginate(20)->withQueryString();

        return view('admin.history.index', [
            'history' => $history,
            'filters' => compact('complaintId', 'action', 'performer', 'sort', 'order'),
        ]);
    }
}
