<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_magang', function (Blueprint $table) {
            $table->string('nama_pembimbing_akademik')->nullable()->after('tanggal_selesai');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_magang', function (Blueprint $table) {
            $table->dropColumn('nama_pembimbing_akademik');
        });
    }
};
