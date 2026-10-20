<?php

namespace App\Support;

class Departemen
{
    public const MAP = [
        'E141' => ['kode' => 'MNH',  'nama' => 'Manajemen Hutan'],
        'E241' => ['kode' => 'THH',  'nama' => 'Teknologi Hasil Hutan'],
        'E341' => ['kode' => 'KSHE', 'nama' => 'Konservasi Sumber Daya Hutan dan Ekowisata'],
        'E441' => ['kode' => 'SVK',  'nama' => 'Silvikultur'],
    ];


    public static function dariNim(?string $nim): ?array
    {
        if ($nim === null || strlen($nim) < 4) {
            return null;
        }

        return self::MAP[substr($nim, 0, 4)] ?? null;
    }


    public static function namaDariKode(?string $kode): ?string
    {
        foreach (self::MAP as $item) {
            if ($item['kode'] === $kode) {
                return $item['nama'];
            }
        }

        return null;
    }
}
