<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Surat untuk instansi kedua: nomor surat, PDF form pengajuan, dan PDF surat pengantar.
     * ->after() hanya berpengaruh di MySQL/MariaDB.
     */
    public function up(): void
    {
        Schema::table('pengajuan_magang', function (Blueprint $table) {
            $table->string('nomor_surat_kedua', 100)->nullable()->after('nomor_surat');
            $table->string('file_surat_pengajuan_kedua')->nullable()->after('file_surat_pengajuan');
            $table->string('file_surat_magang_kedua')->nullable()->after('file_surat_magang');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_magang', function (Blueprint $table) {
            $table->dropColumn([
                'nomor_surat_kedua',
                'file_surat_pengajuan_kedua',
                'file_surat_magang_kedua',
            ]);
        });
    }
};