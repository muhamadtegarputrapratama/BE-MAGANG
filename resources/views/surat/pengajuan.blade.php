<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Form Pengajuan Magang Mandiri</title>

    <style>
        @page {
            margin: 2.5cm 2.5cm 2cm 2.5cm;
        }

        body {
            font-family: "Times New Roman", Times, serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #000000;
        }

        h1 {
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            text-decoration: underline;
            margin: 0 0 22px 0;
        }

        p {
            margin: 0 0 10px 0;
            text-align: justify;
        }

        /* =========================
           BARIS DATA (label : isi)
        ========================= */

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }

        table.data > tbody > tr > td {
            vertical-align: top;
            padding: 1px 0;
        }

        td.label {
            width: 4.6cm;
        }

        td.titik {
            width: 0.5cm;
        }

        /* =========================
           DAFTAR BERNOMOR (nama + NIM, departemen)
        ========================= */

        table.daftar {
            width: 100%;
            border-collapse: collapse;
            line-height: 1.3;
        }

        table.daftar td {
            vertical-align: top;
            padding: 0;
        }

        table.daftar td.no {
            width: 0.8cm;
        }

        table.daftar td.nim {
            width: 3.2cm;
        }

        /* =========================
           TANDA TANGAN
        ========================= */

        .ttd {
            margin-top: 16px;
            width: 300px;
            page-break-inside: avoid;
        }

        .qr {
            margin: 6px 0 4px 0;
            height: 80px;
        }

        .qr img {
            height: 80px;
        }

        .belum-ttd {
            width: 80px;
            height: 80px;
            border: 1px dashed #999999;
            color: #888888;
            font-size: 8pt;
            line-height: 1.2;
            text-align: center;
            padding-top: 30px;
            box-sizing: border-box;
        }

        .garis {
            text-decoration: underline;
        }

        /* =========================
           LAMPIRAN
        ========================= */

        .lampiran {
            margin-top: 50px;
            line-height: 1.35;
            page-break-inside: avoid;
        }
    </style>
</head>

<body>

    @php
        $daftar  = array_values($anggota);
        $banyak  = count($daftar) > 1;
        $pengaju = $daftar[0] ?? ['nama' => '-', 'nim' => '-', 'departemen' => '-'];

        // Departemen unik, urut sesuai kemunculan (satu departemen bisa dipakai banyak mahasiswa)
        $deptList = collect($daftar)->pluck('departemen')->filter()->unique()->values();

        // Lampiran bisa diubah dari controller/service; default sesuai contoh surat
        $daftarLampiran = $lampiran ?? ['Surat Rekomendasi Dosen Pembimbing Magang'];
    @endphp


    <h1>FORM PENGAJUAN MAGANG MANDIRI</h1>

    <p>
        Yang bertandatangan di bawah ini, menerangkan bahwa mahasiswa
        {{ $fakultas }} IPB:
    </p>

    <table class="data">
        <tr>
            <td class="label">Nama</td>
            <td class="titik">:</td>
            <td>
                <table class="daftar">
                    @foreach ($daftar as $i => $a)
                        <tr>
                            @if ($banyak)
                                <td class="no">{{ $i + 1 }}.</td>
                            @endif
                            <td>{{ $a['nama'] }}</td>
                            @if ($banyak)
                                <td class="nim">{{ $a['nim'] }}</td>
                            @endif
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>

        @unless ($banyak)
            <tr>
                <td class="label">NIM</td>
                <td class="titik">:</td>
                <td>{{ $pengaju['nim'] }}</td>
            </tr>
        @endunless

        <tr>
            <td class="label" style="padding-top: 8px;">Program studi/Departemen</td>
            <td class="titik" style="padding-top: 8px;">:</td>
            <td style="padding-top: 8px;">
                <table class="daftar">
                    @foreach ($deptList as $i => $dept)
                        <tr>
                            @if ($deptList->count() > 1)
                                <td class="no">{{ $i + 1 }}.</td>
                            @endif
                            <td>{{ $dept }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    <p>Bermaksud untuk mengajukan surat keterangan Magang Mandiri ke Instansi:</p>

    <table class="data">
        <tr>
            <td class="label">Nama Instansi</td>
            <td class="titik">:</td>
            <td>{{ $instansi }}</td>
        </tr>
        <tr>
            <td class="label">Alamat</td>
            <td class="titik">:</td>
            <td style="text-align: justify;">{!! nl2br(e($alamat)) !!}</td>
        </tr>
        <tr>
            <td class="label">Waktu Pelaksanaan</td>
            <td class="titik">:</td>
            <td>{{ $tanggalMulai }} s/d {{ $tanggalSelesai }}</td>
        </tr>
    </table>


    <p>
        Sehubungan dengan itu kami mohon dapat diberikan surat pengantar magang mandiri.
        Atas segala bantuannya kami mengucapkan terima kasih.
    </p>


    <div class="ttd">
        {{ $kota }}, {{ $tanggalSurat }}<br>
        Yang mengajukan,

        <div class="qr">
            @if ($qr)
                <img src="{{ $qr }}" alt="QR verifikasi">
            @else
                <div class="belum-ttd">Belum ditandatangani</div>
            @endif
        </div>

        <span class="garis">{{ $pengaju['nama'] }}</span><br>
        NIM {{ $pengaju['nim'] }}
    </div>


    <div class="lampiran">
        Dokumen persyaratan yang dilampirkan:<br>

        @if (count($daftarLampiran) > 1)
            @foreach ($daftarLampiran as $i => $item)
                {{ $i + 1 }}. {{ $item }}<br>
            @endforeach
        @else
            {{ $daftarLampiran[0] ?? '' }}
        @endif
    </div>

</body>
</html>