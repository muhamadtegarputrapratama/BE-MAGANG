<?php

namespace App\Enums;

enum StatusPengajuan: string
{
    case Draft = 'draft';
    case MenungguVerifikasi = 'menunggu_verifikasi';
    case Setuju = 'setuju';
    case Revisi = 'revisi';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::MenungguVerifikasi => 'Menunggu Verifikasi',
            self::Setuju => 'Setuju',
            self::Revisi => 'Revisi',
        };
    }
}