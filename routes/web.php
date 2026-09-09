<?php

use App\Http\Controllers\Admin\ComplaintController as AdminComplaintController;
use App\Http\Controllers\Admin\ComplaintHistoryController;
use App\Http\Controllers\Admin\ComplaintReasonController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Guest-only auth routes.
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Authenticated + approved routes (any role).
Route::middleware(['auth', 'approved'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/complaints/create', [ComplaintController::class, 'create'])->name('complaints.create');
    Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store');
    Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints.index');
    Route::get('/complaints/{complaint}', [ComplaintController::class, 'show'])->name('complaints.show');
    Route::get('/complaints/{complaint}/attachments/{attachment}', [ComplaintController::class, 'downloadAttachment'])
        ->name('complaints.attachments.download');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');
});

// Admin-only routes.
Route::middleware(['auth', 'approved', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::patch('/users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status');
    Route::post('/users/bulk-status', [UserController::class, 'bulkUpdateStatus'])->name('users.bulkStatus');

    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

    Route::get('/reasons', [ComplaintReasonController::class, 'index'])->name('reasons.index');
    Route::get('/reasons/create', [ComplaintReasonController::class, 'create'])->name('reasons.create');
    Route::post('/reasons', [ComplaintReasonController::class, 'store'])->name('reasons.store');
    Route::get('/reasons/{reason}/edit', [ComplaintReasonController::class, 'edit'])->name('reasons.edit');
    Route::patch('/reasons/{reason}', [ComplaintReasonController::class, 'update'])->name('reasons.update');
    Route::delete('/reasons/{reason}', [ComplaintReasonController::class, 'destroy'])->name('reasons.destroy');
    Route::post('/reasons/bulk-active', [ComplaintReasonController::class, 'bulkUpdateActive'])->name('reasons.bulkActive');

    Route::get('/complaints', [AdminComplaintController::class, 'index'])->name('complaints.index');
    Route::get('/complaints/{complaint}', [AdminComplaintController::class, 'show'])->name('complaints.show');
    Route::post('/complaints/{complaint}/assign', [AdminComplaintController::class, 'assign'])->name('complaints.assign');
    Route::patch('/complaints/{complaint}/status', [AdminComplaintController::class, 'updateStatus'])->name('complaints.status');
    Route::get('/complaints/{complaint}/attachments/{attachment}', [AdminComplaintController::class, 'downloadAttachment'])
        ->name('complaints.attachments.download');

    Route::get('/complaint-history', [ComplaintHistoryController::class, 'index'])->name('history.index');
});
