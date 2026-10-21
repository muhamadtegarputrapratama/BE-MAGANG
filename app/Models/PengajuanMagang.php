<?php

namespace App\Models;

use App\Enums\StatusPengajuan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanMagang extends Model
{
    protected $table = 'pengajuan_magang';

    protected $fillable = [
        'user_id',
        'instansi',
        'alamat_instansi',
        'tanggal_mulai',
        'tanggal_selesai',
        'status',
        'catatan_revisi',
        'diverifikasi_oleh',
        'diverifikasi_at',
        'file_bukti_spp',
        'file_surat_rekomendasi',
        'kode_verifikasi',
        'ditandatangani_at',
        'dikirim_at',
        'nomor_surat',
        'file_surat_pengajuan',
        'file_surat_magang',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusPengajuan::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'diverifikasi_at' => 'datetime',
            'ditandatangani_at' => 'datetime',
            'dikirim_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function sudahDitandatangani(): bool
    {
        return $this->ditandatangani_at !== null;
    }

    /** Mahasiswa hanya boleh mengubah saat draft atau diminta revisi. */
    public function bisaDiedit(): bool
    {
        return in_array($this->status, [StatusPengajuan::Draft, StatusPengajuan::Revisi], true);
    }
}