<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Departemen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Departemen SELALU ditentukan backend dari prefix NIM.
        $departemen = Departemen::dariNim($data['nim']);

        $user = User::create([
            'name' => $data['name'],
            'nim' => $data['nim'],
            'departemen' => $departemen['kode'],
            'email' => $data['email'],
            'password' => $data['password'], // di-hash otomatis oleh cast 'hashed'
        ]);

        return response()->json([
            'message' => 'Registrasi berhasil. Silakan login.',
            'user' => new UserResource($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        // Pesan sama untuk email tidak ditemukan maupun password salah,
        // agar tidak membocorkan email mana yang terdaftar.
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        // Hanya mencabut token yang sedang dipakai, bukan semua token.
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }
}
