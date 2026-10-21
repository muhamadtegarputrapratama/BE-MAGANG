<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengajuan_magang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // data form
            $table->string('instansi');
            $table->text('alamat_instansi');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');

            // file upload mahasiswa
            $table->string('file_bukti_spp')->nullable();
            $table->string('file_surat_rekomendasi')->nullable();

            // status dan verifikasi admin
            $table->string('status', 30)->default('draft')->index();
            $table->text('catatan_revisi')->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_at')->nullable();

            // surat dan alur kirim
            $table->string('kode_verifikasi', 40)->unique();
            $table->timestamp('ditandatangani_at')->nullable();
            $table->timestamp('dikirim_at')->nullable();
            $table->string('nomor_surat')->nullable()->unique();
            $table->string('file_surat_pengajuan')->nullable();
            $table->string('file_surat_magang')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_magang');
    }
};