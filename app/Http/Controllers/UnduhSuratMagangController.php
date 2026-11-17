<?php

namespace App\Http\Controllers;

use App\Enums\StatusPengajuan;
use App\Models\PengajuanMagang;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UnduhSuratMagangController extends Controller
{
    public function __invoke(int $pengajuan, int $urutan)
    {
        $p = PengajuanMagang::find($pengajuan);

        abort_if(
            $p === null
            || $p->status !== StatusPengajuan::Setuju
            || $urutan < 1
            || $urutan > $p->jumlahInstansi(),
            404,
            'Surat tidak tersedia.'
        );

        $path = $p->fileSuratMagang($urutan);

        abort_if(blank($path), 404, 'Surat tidak tersedia.');

        // Hanya disk privat. Folder storage tidak pernah dibuka ke publik.
        $disk = Storage::disk('local');

        abort_unless($disk->exists($path), 404, 'Surat tidak tersedia.');

        $namaInstansi = $urutan === 2 ? $p->instansi_kedua : $p->instansi;
        $slug = Str::limit(Str::slug((string) $namaInstansi), 60, '');
        $namaFile = "surat-magang-{$urutan}" . ($slug !== '' ? "-{$slug}" : '') . '.pdf';

        return $disk->download($path, $namaFile, [
            'Content-Type'           => 'application/pdf',
            'Cache-Control'          => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag'           => 'noindex, nofollow',
        ]);
    }
}
