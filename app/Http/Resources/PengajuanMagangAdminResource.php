<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PengajuanMagangAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mahasiswa' => [
                // SESUAIKAN dengan nama kolom di tabel users milik temanmu
                'nama'       => $this->user->name,
                'nim'        => $this->user->nim,
                'departemen' => $this->user->departemen,
            ],
            'instansi' => $this->instansi,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'dikirim_at' => $this->dikirim_at?->toIso8601String(),
        ];
    }
}