<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{

    use ApiResponse;

    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
        ]);

        $token = JWTAuth::fromUser($user);

        return $this->successResponse([
            'user' => $user,
            'token' => $token,
            'User registered successfully.',
            201
        ]);
    }


    public function login(LoginRequest $request)
    {
        // If the request already contains a valid token, invalidate it
        try {
            if ($oldToken = JWTAuth::getToken()) {
                JWTAuth::invalidate($oldToken);
            }
        } catch (\Exception $e) {
            // Ignore if there's no valid token
        }

        $credentials = $request->validated();

        if (! $token = JWTAuth::attempt($credentials)) {
            return $this->errorResponse('Invalid credentials.', 401);
        }

        return $this->successResponse([
            'user' => auth()->user(),
            'token' => $token,
        ], 'Login successful.');
    }

    public function profile()
    {
        return $this->successResponse(
                auth()->user(), 
                'User profile retrieved successfully.'
        );
    }
}