<?php

return [
    // alamat frontend, dipakai untuk isi QR verifikasi
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),

    'fakultas' => 'Fakultas Kehutanan dan Lingkungan',
    'kota'     => 'Bogor',

    // Nomor surat pengantar magang. {urut} naik otomatis, {tahun} tahun saat disetujui.
    'nomor_surat' => [
        'format'     => '{urut}/IT3.F4/PK.01.06/{tahun}',
        // nomor urut pertama setiap tahun (ubah lewat .env kalau melanjutkan dari angka tertentu)
        'mulai_dari' => env('NOMOR_SURAT_MULAI', 1),
    ],

    // Logo di kop surat, relatif terhadap folder public
    'logo_path' => env('LOGO_PATH', 'images/logo-ipb.png'),

    // Isi kop surat pengantar magang
    'kop' => [
        'kementerian' => 'KEMENTERIAN PENDIDIKAN TINGGI, SAINS, DAN TEKNOLOGI',
        'institusi'   => 'INSTITUT PERTANIAN BOGOR',
        'kontak'      => [
            'Kampus IPB Dramaga, Bogor 16680',
            'Telepon (0251) 8621677',
            'fahutan@apps.ipb.ac.id',
            'fahutan.ipb.ac.id',
        ],
        'slogan'      => 'Inspiring Innovation with Integrity',
    ],

    // Penandatangan surat pengantar magang
    'penandatangan' => [
        'jabatan'  => ['Wakil Dekan', 'Bidang Akademik, Kemahasiswaan dan Alumni'],
        'nama'     => env('PENANDATANGAN_NAMA', 'Nama Wakil Dekan, gelar'),
        'nip'      => env('PENANDATANGAN_NIP', '000000000000000000'),
        // gambar tanda tangan di folder public. Kosong = ruang kosong untuk tanda tangan basah
        'ttd_path' => env('PENANDATANGAN_TTD'),
    ],
];