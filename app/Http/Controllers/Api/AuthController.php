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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    /**
     * Registrasi mahasiswa baru.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Departemen ditentukan backend berdasarkan prefix NIM.
        $departemen = Departemen::dariNim($data['nim']);

        $user = DB::transaction(function () use ($data, $departemen) {
            // Buat akun mahasiswa.
            $user = User::create([
                'name' => $data['name'],
                'nim' => $data['nim'],
                'departemen' => $departemen['kode'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            // Ambil role mahasiswa dengan guard yang sesuai.
            $role = Role::findByName('mahasiswa', 'web');

            // Hubungkan role Spatie secara otomatis.
            $user->assignRole($role);

            return $user;
        });

        return response()->json([
            'message' => 'Registrasi berhasil. Silakan login.',
            'user' => new UserResource($user),
        ], 201);
    }

    /**
     * Login pengguna.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        // Gunakan pesan yang sama untuk email atau password yang salah.
        if (
            ! $user ||
            ! Hash::check($data['password'], $user->password)
        ) {
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        // Buat token autentikasi Sanctum.
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Mendapatkan informasi pengguna yang sedang login.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Logout dan mencabut token yang sedang digunakan.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }
}
