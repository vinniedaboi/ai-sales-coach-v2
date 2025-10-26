<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Handle an incoming authentication request for the SPA.
     */
    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            // Regeneration of session ID is handled automatically by Auth::attempt
            // The XSRF-TOKEN cookie is set by Sanctum after the initial CSRF cookie request
            // We just return success and the user object.

            return response()->json([
                'user' => Auth::user()->only('id', 'name', 'email'), // Return essential user data
                'message' => 'Login successful!'
            ]);
        }

        // Return a JSON error response for the SPA
        return response()->json(['message' => 'Invalid credentials.'], 401);
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        // Invalidate the session and regenerate the CSRF token
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Return a JSON success response for the SPA
        return response()->json(['message' => 'Logged out successfully!']);
    }
}
