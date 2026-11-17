<?php

use App\Http\Controllers\Admin\BerkasPengajuanController;
use App\Http\Controllers\Admin\PengajuanMagangController as AdminPengajuanController;
use App\Http\Controllers\Admin\SuratWhatsAppController; // BARU
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Mahasiswa\PengajuanMagangController as MahasiswaPengajuanController;
use App\Http\Controllers\UnduhSuratMagangController; // BARU
use App\Http\Controllers\VerifikasiSuratController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->name('login')->middleware('throttle:5,1');

// Verifikasi surat lewat QR
Route::get('/verifikasi/{kode}', [VerifikasiSuratController::class, 'show'])
    ->where('kode', '[A-Za-z0-9]{32}')
    ->middleware('throttle:30,1');

// Preview/download file (admin dan pemilik pengajuan). Dilindungi signed URL,
// tanpa token, supaya bisa dipakai di <iframe>/<img>.
Route::get('/admin/pengajuan/{pengajuan}/berkas/{jenis}', [BerkasPengajuanController::class, 'tampil'])
    ->name('admin.pengajuan.berkas')
    ->middleware('signed');

// BARU: unduh surat magang oleh mahasiswa lewat link WhatsApp. Tanpa token, hanya signed URL + masa berlaku.
Route::get('/surat-magang/{pengajuan}/{urutan}/unduh', UnduhSuratMagangController::class)
    ->name('surat-magang.unduh')
    ->whereNumber('pengajuan')
    ->whereIn('urutan', ['1', '2'])
    ->middleware(['throttle:20,1', 'signed']);

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

            // Edit data pengajuan
            Route::patch('/{pengajuan}', [AdminPengajuanController::class, 'update']);

            // Ubah status pengajuan
            Route::patch('/{pengajuan}/status', [AdminPengajuanController::class, 'ubahStatus']);

            // BARU: buat link unduh sementara + URL WhatsApp untuk surat instansi ke-1 / ke-2
            Route::post('/{pengajuan}/surat-magang/{urutan}/whatsapp', [SuratWhatsAppController::class, 'buat'])
                ->whereIn('urutan', ['1', '2']);
        });
});
