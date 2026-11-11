
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengajuan_magang', function (Blueprint $table) {
            $table->string('instansi_kedua')->nullable()->after('instansi');
            $table->text('alamat_instansi_kedua')->nullable()->after('alamat_instansi');
            $table->date('tanggal_mulai_kedua')->nullable()->after('tanggal_selesai');
            $table->date('tanggal_selesai_kedua')->nullable()->after('tanggal_mulai_kedua');
        });
    }

    public function down(): void
    {
        Schema::table('pengajuan_magang', function (Blueprint $table) {
            $table->dropColumn([
                'instansi_kedua',
                'alamat_instansi_kedua',
                'tanggal_mulai_kedua',
                'tanggal_selesai_kedua',
            ]);
        });
    }
};
