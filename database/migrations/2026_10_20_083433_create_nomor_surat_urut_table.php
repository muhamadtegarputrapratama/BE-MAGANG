<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // satu baris per tahun, menyimpan nomor urut terakhir yang dipakai
        Schema::create('nomor_surat_urut', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun')->primary();
            $table->unsignedInteger('terakhir')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomor_surat_urut');
    }
};