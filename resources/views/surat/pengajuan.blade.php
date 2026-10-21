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
            color: #000;
        }

        h1 {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            text-decoration: underline;
            margin: 0 0 28px 0;
        }

        p {
            margin: 0 0 10px 0;
            text-align: justify;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        table.data td {
            vertical-align: top;
            padding: 2px 0;
        }

        td.label {
            width: 185px;
        }

        td.titik {
            width: 14px;
        }

        .instansi {
            font-weight: bold;
            border-bottom: 1px dotted #000;
            padding-bottom: 2px;
            margin-bottom: 14px;
        }

        .penutup {
            margin-top: 18px;
        }

        .ttd {
            margin-top: 22px;
            width: 280px;
            page-break-inside: avoid;
        }

        .qr {
            margin: 8px 0 6px 0;
            height: 110px;
        }

        .belum-ttd {
            width: 110px;
            height: 110px;
            border: 1px dashed #999;
            color: #888;
            font-size: 9pt;
            text-align: center;
            padding-top: 44px;
            box-sizing: border-box;
        }

        .lampiran {
            margin-top: 26px;
            page-break-inside: avoid;
        }

        .lampiran table td {
            vertical-align: top;
            padding: 0;
        }
    </style>
</head>
<body>

    <h1>FORM PENGAJUAN MAGANG MANDIRI</h1>

    <p>
        Yang bertandatangan di bawah ini, menerangkan bahwa mahasiswa
        {{ $fakultas }} IPB:
    </p>

    <table class="data">
        <tr>
            <td class="label">Nama</td>
            <td class="titik">:</td>
            <td>{{ $nama }}</td>
        </tr>
        <tr>
            <td class="label">NIM</td>
            <td class="titik">:</td>
            <td>{{ $nim }}</td>
        </tr>
        <tr>
            <td class="label">Program Studi/Departemen</td>
            <td class="titik">:</td>
            <td>{{ $departemen }}</td>
        </tr>
    </table>

    <p>Bermaksud untuk mengajukan surat keterangan Magang Mandiri ke Instansi:</p>

    <div class="instansi">{{ $instansi }}</div>

    <table class="data">
        <tr>
            <td class="label">Alamat</td>
            <td class="titik">:</td>
            <td>{{ $alamat }}</td>
        </tr>
        <tr>
            <td class="label">Waktu pelaksanaan</td>
            <td class="titik">:</td>
            <td>{{ $tanggalMulai }} - {{ $tanggalSelesai }}</td>
        </tr>
    </table>

    <p class="penutup">
        Sehubungan dengan itu kami mohon dapat diberikan surat pengantar magang mandiri.<br>
        Atas segala bantuannya diucapkan terima kasih.
    </p>

    <div class="ttd">
        {{ $kota }}, {{ $tanggalSurat }}<br>
        Yang mengajukan,

        <div class="qr">
            @if($qr)
                <img src="{{ $qr }}" width="110" height="110" alt="QR verifikasi">
            @else
                <div class="belum-ttd">Belum ditandatangani</div>
            @endif
        </div>

        <strong>{{ $nama }}</strong><br>
        NIM {{ $nim }}
    </div>

    <div class="lampiran">
        <table>
            <tr>
                <td style="width: 200px;">Dokumen persyaratan yang dilampirkan</td>
                <td style="width: 14px;">:</td>
                <td>
                    1. Rekomendasi dari pembimbing akademik<br>
                    2. Bukti SPP
                </td>
            </tr>
        </table>
    </div>

</body>
</html>