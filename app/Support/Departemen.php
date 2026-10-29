<?php

namespace App\Support;

class Departemen
{
    /**
     * Prefix NIM (4 karakter pertama) => kode departemen.
     * Harus sejalan dengan regex NIM di RegisterRequest dan kunci di config/departemen.php.
     */
    private const PREFIX_NIM = [
        'E141' => 'MNH',
        'E241' => 'THH',
        'E341' => 'KSHE',
        'E441' => 'SVK',
    ];

    /** Nama lengkap dari kode departemen. Kalau kode tidak dikenal, dikembalikan apa adanya. */
    public static function nama(?string $kode): string
    {
        if (! $kode) {
            return '-';
        }

        return config("departemen.{$kode}", $kode);
    }

    /**
     * Nama lengkap untuk respons API. Akun tanpa departemen (misalnya admin)
     * menghasilkan null, bukan "-".
     */
    public static function namaDariKode(?string $kode): ?string
    {
        if (! $kode) {
            return null;
        }

        return self::nama($kode);
    }

    /**
     * Departemen dari prefix NIM (4 karakter pertama, contoh: "E441").
     * Hasil: ['kode' => 'SVK', 'nama' => 'Silvikultur'], atau null kalau prefix tidak dikenal.
     * Pemetaan prefix ada di konstanta PREFIX_NIM di atas.
     */
    public static function dariNim(?string $nim): ?array
    {
        $prefix = substr(strtoupper(trim((string) $nim)), 0, 4);
        $kode = self::PREFIX_NIM[$prefix] ?? null;

        if (! $kode) {
            return null;
        }

        return [
            'kode' => $kode,
            'nama' => self::nama($kode),
        ];
    }
}