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

    // Gambar kop surat, relatif terhadap folder public (contoh: images/kop-surat.png).
    // Kosong/tidak ada filenya = memakai kop teks sederhana.
    'kop_path' => env('KOP_SURAT_PATH'),

    // Penandatangan surat pengantar magang
    'penandatangan' => [
        'jabatan'  => ['Wakil Dekan', 'Bidang Akademik, Kemahasiswaan dan Alumni'],
        'nama'     => env('PENANDATANGAN_NAMA', 'Nama Wakil Dekan, gelar'),
        'nip'      => env('PENANDATANGAN_NIP', '000000000000000000'),
        // gambar tanda tangan di folder public. Kosong = ruang kosong untuk tanda tangan basah
        'ttd_path' => env('PENANDATANGAN_TTD'),
    ],
]; 