<?php

namespace App\Support;

class Departemen
{
    /** Nama lengkap dari kode departemen. Kalau kode tidak dikenal, dikembalikan apa adanya. */
    public static function nama(?string $kode): string
    {
        if (! $kode) {
            return '-';
        }

        return config("departemen.{$kode}", $kode);
    }

    /**
     * Kode departemen dari NIM, contoh: "E4419827364" -> "SVK".
     * Dibaca dari huruf fakultas + digit pertama setelahnya (lihat config/nim_departemen.php).
     * Mengembalikan null kalau NIM tidak dikenal.
     */
    public static function dariNim(?string $nim): ?string
    {
        $nim = strtoupper(trim((string) $nim));

        if (! preg_match('/^([A-Z])(\d)/', $nim, $cocok)) {
            return null;
        }

        return config("nim_departemen.{$cocok[1]}.{$cocok[2]}");
    }
}