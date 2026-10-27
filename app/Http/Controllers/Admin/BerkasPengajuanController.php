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
        'spp' => 'file_bukti_spp',
        'rekomendasi' => 'file_surat_rekomendasi',
    ];

    public function tampil(Request $request, PengajuanMagang $pengajuan)
{
    // Akses dijaga oleh middleware 'signed' pada route.
    $jenis = (string) $request->route('jenis');

    abort_unless(
        isset(self::BERKAS[$jenis]),
        404,
        'Jenis berkas tidak dikenal: ' . $jenis
    );

    abort_if(
        $pengajuan->status === StatusPengajuan::Draft,
        404,
        'Pengajuan masih draft.'
    );

    $path = $pengajuan->{self::BERKAS[$jenis]};

    /** @var FilesystemAdapter $disk */
    $disk = Storage::disk('local');

    abort_if(! $path, 404, 'Kolom berkas kosong di database.');
    abort_if(! $disk->exists($path), 404, 'File tidak ada di disk: ' . $path);

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
