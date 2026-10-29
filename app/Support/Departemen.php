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
}