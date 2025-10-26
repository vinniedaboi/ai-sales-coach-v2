<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\LeadController; // Assuming this is your leads controller

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| These routes are typically used for the SPA/Session based authentication flow.
|
*/

// --- SANCTUM SPA AUTHENTICATION ROUTES ---

// 1. Route to get the XSRF-TOKEN cookie required by the frontend
Route::get('/sanctum/csrf-cookie', function (Request $request) {
    return response()->noContent();
});

// 2. Core Authentication Endpoints
Route::post('/login', [LoginController::class, 'authenticate']);
Route::post('/logout', [LoginController::class, 'logout']);

// 3. PROTECTED API ROUTES (requires session cookie set by successful login)
Route::middleware('auth:sanctum')->group(function () {
    // Check user status (essential for SPA initialization)
    Route::get('/api/user', function (Request $request) {
        // Return only necessary user fields
        return $request->user()->only('id', 'name', 'email');
    });

    // Your existing protected routes go here:
    // Google Auth redirects (These are usually protected on the callback side)
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirectToGoogle'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('google.callback');

    // Your existing API endpoints
    Route::post('/api/leads/send-email', [LeadController::class, 'sendEmailApi']);
    
    // Placeholder for other protected dashboard routes/data fetching
    Route::get('/api/dashboard-data', function () {
        return response()->json(['message' => 'Protected dashboard data accessed!']);
    });
});

// Note: Ensure your `index.html` (or `frontend-spa.html` in this case) 
// is served by a catch-all route if you are not using Nginx/Apache for serving static files.
// For the purpose of this file, we assume the frontend is served correctly.
