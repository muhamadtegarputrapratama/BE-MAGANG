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
            'departemen' => [
                'kode' => $this->departemen,
                'nama' => Departemen::namaDariKode($this->departemen),
            ],
            'email' => $this->email,
            'role' => $this->role ?? 'mahasiswa',   
        ];
    }
}
