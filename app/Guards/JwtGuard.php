<?php

namespace App\Guards;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\User;

class JwtGuard implements Guard
{
    protected Request $request;
    protected ?Authenticatable $user = null;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Return the authenticated user, or null.
     */
    public function user(): ?Authenticatable
    {
        if ($this->user) return $this->user;

        $authHeader = $this->request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);

        try {
            $decoded = JWT::decode($token, new Key(env('JWT_SECRET'), 'HS256'));
            $this->user = User::find($decoded->sub);
            return $this->user;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Check if a user is authenticated.
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Check if the current user is a guest.
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Get the authenticated user ID.
     */
    public function id(): ?int
    {
        return $this->user() ? $this->user()->getAuthIdentifier() : null;
    }

    /**
     * Validate user credentials (unused in JWT guard).
     */
    public function validate(array $credentials = []): bool
    {
        return false;
    }

    /**
     * Set the current authenticated user.
     */
    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Whether a user has been set already.
     */
    public function hasUser(): bool
    {
        return $this->user !== null;
    }
}
