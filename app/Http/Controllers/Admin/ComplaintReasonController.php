<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintReason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ComplaintReasonController extends Controller
{
    public function index(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('search', '')), 0, 100);
        $priority = in_array($request->query('priority'), Complaint::PRIORITIES, true) ? $request->query('priority') : '';
        $active = in_array($request->query('active'), ['active', 'inactive'], true) ? $request->query('active') : '';
        $sort = in_array($request->query('sort'), ['name', 'priority', 'active', 'created_at'], true)
            ? $request->query('sort') : 'name';
        $order = $request->query('order') === 'desc' ? 'desc' : 'asc';

        $query = ComplaintReason::query();
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }
        if ($priority !== '') {
            $query->where('priority', $priority);
        }
        if ($active !== '') {
            $query->where('active', $active === 'active');
        }

        $reasons = $query->orderBy($sort, $order)->paginate(20)->withQueryString();

        return view('admin.reasons.index', [
            'reasons' => $reasons,
            'filters' => compact('search', 'priority', 'active', 'sort', 'order'),
        ]);
    }

    public function create(): View
    {
        return view('admin.reasons.form', ['reason' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:complaint_reasons,name'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', Rule::in(Complaint::PRIORITIES)],
            'active' => ['nullable', 'boolean'],
        ]);
        $validated['active'] = $request->boolean('active', true);

        ComplaintReason::create($validated);

        return redirect()->route('admin.reasons.index')->with('status', 'Complaint reason created successfully.');
    }

    public function edit(ComplaintReason $reason): View
    {
        return view('admin.reasons.form', ['reason' => $reason]);
    }

    public function update(Request $request, ComplaintReason $reason): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('complaint_reasons', 'name')->ignore($reason->id)],
            'description' => ['nullable', 'string'],
            'priority' => ['required', Rule::in(Complaint::PRIORITIES)],
            'active' => ['nullable', 'boolean'],
        ]);
        $validated['active'] = $request->boolean('active');

        $reason->update($validated);

        return redirect()->route('admin.reasons.index')->with('status', 'Complaint reason updated successfully.');
    }

    public function destroy(ComplaintReason $reason): RedirectResponse
    {
        try {
            $reason->delete();
        } catch (\Illuminate\Database\QueryException $exception) {
            return back()->withErrors(['reason' => 'This reason is used by complaints and cannot be deleted.']);
        }

        return back()->with('status', 'Complaint reason deleted successfully.');
    }

    public function bulkUpdateActive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'active' => ['required', 'in:0,1'],
            'reason_ids' => ['required', 'array'],
            'reason_ids.*' => ['integer'],
        ]);

        $updated = ComplaintReason::whereIn('id', $validated['reason_ids'])
            ->update(['active' => (bool) $validated['active']]);

        return response()->json([
            'success' => true,
            'message' => $updated . ' reason(s) updated.',
            'data' => [],
        ]);
    }
}
