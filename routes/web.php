<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\WarningAcknowledgementController;
use App\Http\Controllers\ProfileController;

// ============================================
// GUEST ROUTES (No authentication needed)
// ============================================

Route::get('/', function () {
    return redirect()->route('login');
});

// LOGIN ROUTES (#51-#58)
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'authenticate']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ============================================
// PROTECTED ROUTES (Authentication required)
// ============================================

Route::middleware('auth')->group(function () {
    // DASHBOARD (#79)
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // WARNING ACKNOWLEDGEMENT (#80)
    Route::get('/warning-acknowledgement', [WarningAcknowledgementController::class, 'show'])
        ->name('warning-acknowledgement');
    Route::post('/warning-acknowledgement', [WarningAcknowledgementController::class, 'acknowledge'])
        ->name('warning-acknowledgement.acknowledge');

    // PROFILE ROUTES (#66-#71)
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile/edit', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/picture', [ProfileController::class, 'showPictureUpload'])->name('profile.picture');
    Route::post('/profile/picture', [ProfileController::class, 'uploadPicture'])->name('profile.picture.upload');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ============================================
// PLACEHOLDER ROUTES (To be built by other team members)
// ============================================

Route::get('/register', function () {
    return "Registration page (Person B)";
})->name('register');

Route::get('/admin/users', function () {
    return "Admin users page (Person E)";
})->name('admin.users-index');

Route::get('/admin/dashboard', function () {
    return "Admin dashboard (Person E)";
})->name('admin.dashboard');

Route::get('/admin/statistics', function () {
    return "Admin statistics (Person E)";
})->name('admin.statistics');

Route::get('/forum', function () {
    return "Forum page (other module)";
})->name('forum.index');

