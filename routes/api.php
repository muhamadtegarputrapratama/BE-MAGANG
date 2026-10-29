<?php

use App\Http\Controllers\Admin\BerkasPengajuanController;
use App\Http\Controllers\Admin\PengajuanMagangController as AdminPengajuanController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Mahasiswa\PengajuanMagangController as MahasiswaPengajuanController;
use App\Http\Controllers\VerifikasiSuratController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->name('login')->middleware('throttle:5,1');

// Verifikasi surat lewat QR
Route::get('/verifikasi/{kode}', [VerifikasiSuratController::class, 'surat'])
    ->where('kode', '[A-Za-z0-9]{32}')
    ->middleware('throttle:30,1');

// Preview/download file untuk admin. Dilindungi signed URL (10 menit),
// tanpa token, supaya bisa dipakai di <iframe>/<img>.
Route::get('/admin/pengajuan/{pengajuan}/berkas/{jenis}', [BerkasPengajuanController::class, 'tampil'])
    ->name('admin.pengajuan.berkas')
    ->middleware('signed');

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ----- Mahasiswa -----
    Route::middleware('role:mahasiswa')
        ->prefix('pengajuan-magang')
        ->group(function () {
            Route::get('/', [MahasiswaPengajuanController::class, 'index']);
            Route::post('/', [MahasiswaPengajuanController::class, 'store']);
            Route::get('/{pengajuan}', [MahasiswaPengajuanController::class, 'show']);
            Route::put('/{pengajuan}', [MahasiswaPengajuanController::class, 'update']);
            Route::get('/{pengajuan}/preview-surat', [MahasiswaPengajuanController::class, 'preview']);
            Route::get('/{pengajuan}/surat-magang', [MahasiswaPengajuanController::class, 'suratMagang']);
            Route::post('/{pengajuan}/tanda-tangan', [MahasiswaPengajuanController::class, 'tandaTangan']);
            Route::delete('/{pengajuan}/tanda-tangan', [MahasiswaPengajuanController::class, 'batalTandaTangan']);
            Route::post('/{pengajuan}/kirim', [MahasiswaPengajuanController::class, 'kirim']);
        });

    // ----- Admin KPAP -----
    Route::middleware('role:admin_kpap')
        ->prefix('admin/pengajuan-magang')
        ->group(function () {
            Route::get('/', [AdminPengajuanController::class, 'index']);
            Route::get('/ringkasan', [AdminPengajuanController::class, 'ringkasan']);
            Route::get('/{pengajuan}', [AdminPengajuanController::class, 'show']);
            Route::patch('/{pengajuan}/status', [AdminPengajuanController::class, 'ubahStatus']);
        });
});
