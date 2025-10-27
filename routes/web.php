<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This file is mainly for serving your static frontend or simple web pages.
| Your API routes should go into routes/api.php and use auth.api (JWT guard).
|
*/

// ✅ Serve the SPA (frontend.html) from the public folder
Route::get('/', function () {
    return file_get_contents(public_path('frontend.html'));
});

// (Optional) Route to check server status in browser
Route::get('/status', function () {
    return response()->json(['status' => 'ok', 'app' => config('app.name')]);
});

// (Optional) Redirect any unknown routes to your SPA frontend
Route::fallback(function () {
    return file_get_contents(public_path('frontend.html'));
});
