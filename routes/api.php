<?php

use App\Http\Controllers\Admin\BerkasPengajuanController;
use App\Http\Controllers\Admin\PengajuanMagangController as AdminPengajuanController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Mahasiswa\PengajuanMagangController as MahasiswaPengajuanController;
use App\Http\Controllers\VerifikasiSuratController;
use Illuminate\Support\Facades\Route;


// Auth (publik)
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');


Route::get('/verifikasi/{kode}', [VerifikasiSuratController::class, 'show'])
    ->where('kode', '[A-Za-z0-9]{32}')
    ->middleware('throttle:30,1');

Route::get('/admin/pengajuan/{pengajuan}/berkas/{jenis}', [BerkasPengajuanController::class, 'tampil'])
    ->name('admin.pengajuan.berkas')
    ->middleware('signed');








Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // ----- Mahasiswa -----
    Route::prefix('pengajuan-magang')->group(function () {
        Route::get('/', [MahasiswaPengajuanController::class, 'index']);
        Route::post('/', [MahasiswaPengajuanController::class, 'store']);
        Route::get('/{pengajuan}', [MahasiswaPengajuanController::class, 'show']);
        Route::put('/{pengajuan}', [MahasiswaPengajuanController::class, 'update']);
        Route::get('/{pengajuan}/preview-surat', [MahasiswaPengajuanController::class, 'preview']);
        Route::post('/{pengajuan}/tanda-tangan', [MahasiswaPengajuanController::class, 'tandaTangan']);
        Route::post('/{pengajuan}/kirim', [MahasiswaPengajuanController::class, 'kirim']);
        Route::post('/{pengajuan}/tanda-tangan', [MahasiswaPengajuanController::class, 'tandaTangan']);
        Route::delete('/{pengajuan}/tanda-tangan', [MahasiswaPengajuanController::class, 'batalTandaTangan']);
    });

    // ----- Admin KPAP -----
    Route::middleware('role:admin_kpap')
        ->prefix('admin/pengajuan-magang')
        ->group(function () {
            Route::get('/', [AdminPengajuanController::class, 'index']);
            Route::get('/{pengajuan}', [AdminPengajuanController::class, 'show']);
            Route::patch('/{pengajuan}/status', [AdminPengajuanController::class, 'ubahStatus']);
        });
});
