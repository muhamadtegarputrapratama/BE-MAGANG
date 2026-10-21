<?php

use App\Http\Controllers\Admin\BerkasPengajuanController;
use App\Http\Controllers\Admin\PengajuanMagangController as AdminPengajuanController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Mahasiswa\PengajuanMagangController as MahasiswaPengajuanController;
use App\Http\Controllers\VerifikasiSuratController;
use Illuminate\Support\Facades\Route;


Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');


Route::get('/verifikasi/{kode}', [VerifikasiSuratController::class, 'show']);


Route::get('/admin/pengajuan/{pengajuan}/berkas/{jenis}', [BerkasPengajuanController::class, 'tampil'])
    ->name('admin.pengajuan.berkas')
    ->middleware('signed');


Route::middleware('auth:sanctum')->group(function () {


    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);


    Route::prefix('mahasiswa')->name('mahasiswa.')->group(function () {
        Route::get('/pengajuan', [MahasiswaPengajuanController::class, 'index']);
        Route::post('/pengajuan', [MahasiswaPengajuanController::class, 'store']);
        Route::get('/pengajuan/{pengajuan}', [MahasiswaPengajuanController::class, 'show']);
        Route::match(['put', 'patch'], '/pengajuan/{pengajuan}', [MahasiswaPengajuanController::class, 'update']);
        Route::get('/pengajuan/{pengajuan}/preview', [MahasiswaPengajuanController::class, 'preview']);
        Route::post('/pengajuan/{pengajuan}/tanda-tangan', [MahasiswaPengajuanController::class, 'tandaTangan']);
        Route::post('/pengajuan/{pengajuan}/kirim', [MahasiswaPengajuanController::class, 'kirim']);
    });

  
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/pengajuan', [AdminPengajuanController::class, 'index']);
        Route::get('/pengajuan/{pengajuan}', [AdminPengajuanController::class, 'show']);
        Route::post('/pengajuan/{pengajuan}/status', [AdminPengajuanController::class, 'ubahStatus']);
    });
});
