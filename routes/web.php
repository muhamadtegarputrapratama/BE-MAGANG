<?php

use App\Http\Controllers\VerifikasiSuratController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Verifikasi surat lewat QR (URL publik web untuk scanner kamera HP)
Route::get('/verifikasi/{kode}', [VerifikasiSuratController::class, 'surat'])
    ->where('kode', '[A-Za-z0-9]{32}')
    ->middleware('throttle:30,1')
    ->name('verifikasi.surat');
