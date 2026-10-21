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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nama_instansi');
            $table->text('alamat_instansi');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('file_rekomendasi');
            $table->string('file_spp');
            $table->string('status', 30)->default('diajukan')->index();
            $table->string('nomor_surat', 100)->nullable();
            $table->timestamp('dicetak_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_magang');
    }
};
