<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    /**
     * Register a new user and log them in.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create($validated);

        return $this->respondWithToken(auth('api')->login($user), 201);
    }

    /**
     * Exchange credentials for a token cookie.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $token = auth('api')->attempt($credentials);

        if (! $token) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Get the authenticated user.
     */
    public function me(): JsonResponse
    {
        return response()->json(auth('api')->user());
    }

    /**
     * Invalidate the current token and remove its cookie.
     */
    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return response()->json(['message' => 'Successfully logged out'])
            ->withoutCookie(
                config('jwt.cookie_key_name'),
                config('jwt.cookie.path'),
                config('jwt.cookie.domain'),
            );
    }

    /**
     * Issue a new token and invalidate the current one.
     */
    public function refresh(): JsonResponse
    {
        return $this->respondWithToken(auth('api')->refresh());
    }

    /**
     * Send the token as an httpOnly cookie; it is never exposed in the response body.
     */
    protected function respondWithToken(string $token, int $status = 200): JsonResponse
    {
        $ttl = auth('api')->factory()->getTTL();

        return response()->json([
            'expires_in' => $ttl * 60,
            'user' => auth('api')->user(),
        ], $status)->withCookie($this->tokenCookie($token, $ttl));
    }

    protected function tokenCookie(string $token, int $minutes): Cookie
    {
        return cookie(
            name: config('jwt.cookie_key_name'),
            value: $token,
            minutes: $minutes,
            path: config('jwt.cookie.path'),
            domain: config('jwt.cookie.domain'),
            secure: (bool) config('jwt.cookie.secure'),
            httpOnly: true,
            sameSite: config('jwt.cookie.same_site'),
        );
    }
}
