<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use Livewire\Volt\Volt;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::prefix('bookings')->group(function () {
    Volt::route('/', 'bookings')->name('bookings');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('/dashboard', 'dashboard.home')->name('dashboard');
    Volt::route('/booking/{id}', 'booking')->name('booking');
    Volt::route('/booking/{id}/edit', 'booking-edit')->name('booking.edit');
});

Route::middleware(['auth', 'verified'])->get('/payment-proof/{orderId}/{filename}', function ($orderId, $filename) {
    $order = Order::where('id', $orderId)
                ->where('users_id', Auth::id())
                ->firstOrFail();
    $filePath = 'payment_proofs/' . $filename;
    if (!Storage::disk('local')->exists($filePath) || 
        basename($order->payment_proof) !== $filename) {
        abort(404);
    }
    return Response::file(Storage::disk('local')->path($filePath));
})->name('payment-proof');

Route::middleware(['auth', 'verified', 'check.staff'])->get('admin/payment-proof/{orderId}/{filename}', function ($orderId, $filename) {
    $order = Order::where('id', $orderId)->firstOrFail();
    $filePath = 'payment_proofs/' . $filename;
    if (!Storage::disk('local')->exists($filePath) || 
        basename($order->payment_proof) !== $filename) {
        abort(404);
    }
    return Response::file(Storage::disk('local')->path($filePath));
})->name('admin-payment-proof');

Route::prefix('book')->middleware(['auth', 'verified'])->group(function () {
    Volt::route('/{id}', 'book')->name('book');
});

Route::get('/about', function () {
    return view('about');
})->name('about');

Route::prefix('admin')->middleware(['auth', 'verified', 'check.staff'])->group(function () {
    Volt::route('/', 'admin')->name('admin');

    Volt::route('/orders', 'admin-orders')->name('admin.orders');
    Volt::route('/booking/{id}', 'admin-booking')->name('admin.booking');

    Volt::route('/tags', 'admin-tags')->name('admin.tags');
    
    Volt::route('/users', 'admin-users')->middleware(['check.admin'])->name('admin.users');
    Volt::route('/user/edit/{id}', 'admin-user-edit')->middleware(['check.admin'])->name('admin.edit.user');
    Volt::route('/user/view/{id}', 'admin-user-view')->middleware(['check.admin'])->name('admin.view.user');

    Volt::route('/routes-schedules', 'admin-routes-schedules')->name('admin.routes-schedules');
    Volt::route('/routes-schedule/edit/{id}', 'admin-routes-schedule-edit')->name('admin.edit.routes-schedule');
    Volt::route('/routes-schedule/view/{id}', 'admin-routes-schedule-view')->name('admin.view.routes-schedule');

    Volt::route('/terminals', 'admin-terminals')->name('admin.terminals');
    Volt::route('/terminal/edit/{id}', 'admin-terminal-edit')->name('admin.edit.terminal');
    Volt::route('/terminal/view/{id}', 'admin-terminal-view')->name('admin.view.terminal');

    Volt::route('/buses', 'admin-buses')->name('admin.buses');
    Volt::route('/bus/edit/{id}', 'admin-bus-edit')->name('admin.edit.bus');
    Volt::route('/bus/view/{id}', 'admin-bus-view')->name('admin.view.bus');
    
    
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
