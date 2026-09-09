<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $counts = [
            'users' => [
                'total' => User::count(),
                'pending' => User::where('status', 'pending')->count(),
                'approved' => User::where('status', 'approved')->count(),
                'blocked' => User::where('status', 'blocked')->count(),
            ],
            'complaints' => [
                'total' => Complaint::count(),
                'pending' => Complaint::where('status', 'pending')->count(),
                'in_progress' => Complaint::where('status', 'in_progress')->count(),
                'resolved' => Complaint::where('status', 'resolved')->count(),
                'high_priority' => Complaint::where('priority', 'HIGH')->count(),
            ],
        ];

        return view('admin.dashboard', ['counts' => $counts]);
    }
}
