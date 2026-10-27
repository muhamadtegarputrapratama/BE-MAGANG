<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusPengajuan;
use App\Http\Controllers\Controller;
use App\Models\PengajuanMagang;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BerkasPengajuanController extends Controller
{
    /**
     * Jenis berkas pada URL => nama kolom database.
     */
    private const BERKAS = [
        'surat-pengajuan'   => 'file_surat_pengajuan',
        'bukti-spp'         => 'file_bukti_spp',
        'surat-rekomendasi' => 'file_surat_rekomendasi',
        'surat-magang'      => 'file_surat_magang',
    ];

    public function tampil(
        Request $request,
        PengajuanMagang $pengajuan,
        string $jenis
    ) {
        // Pastikan pengguna sudah login dan memiliki role admin.
        $user = $request->user();

        abort_unless($user, 401);
        abort_unless($user->hasRole('admin_kpap'), 403);

        // Hanya jenis berkas yang diizinkan yang bisa diakses.
        abort_unless(isset(self::BERKAS[$jenis]), 404);

        // Sembunyikan pengajuan draft.
        abort_if(
            $pengajuan->status === StatusPengajuan::Draft,
            404
        );

        $path = $pengajuan->{self::BERKAS[$jenis]};

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('local');

        // Jangan tampilkan error internal jika berkas tidak tersedia.
        abort_if(
            ! $path || ! $disk->exists($path),
            404
        );

        $ekstensi = pathinfo($path, PATHINFO_EXTENSION);
        $nama = "{$jenis}-{$pengajuan->id}.{$ekstensi}";

        if ($request->query('mode') === 'download') {
            return $disk->download($path, $nama);
        }

        return $disk->response(
            $path,
            $nama,
            ['X-Content-Type-Options' => 'nosniff'],
            'inline'
        );
    }
}
