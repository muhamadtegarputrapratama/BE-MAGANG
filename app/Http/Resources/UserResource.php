<?php

namespace App\Http\Resources;

use App\Support\Departemen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nim' => $this->nim,

            // Admin tidak punya departemen, jadi dikirim null.
            'departemen' => $this->departemen
                ? [
                    'kode' => $this->departemen,
                    'nama' => Departemen::namaDariKode($this->departemen),
                ]
                : null,

            'email' => $this->email,

            // Role dibaca dari Spatie (admin_kpap, mahasiswa, dst.), fallback ke kolom users.role.
            'role' => $this->getRoleNames()->first()
                ?? $this->role
                ?? 'mahasiswa',
        ];
    }
}
