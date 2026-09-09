<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->id;

        $counts = [
            'total' => Complaint::where('user_id', $userId)->count(),
            'pending' => Complaint::where('user_id', $userId)->where('status', 'pending')->count(),
            'in_progress' => Complaint::where('user_id', $userId)->where('status', 'in_progress')->count(),
            'resolved' => Complaint::where('user_id', $userId)->where('status', 'resolved')->count(),
        ];

        return view('dashboard', ['profile' => $request->user(), 'counts' => $counts]);
    }
}
