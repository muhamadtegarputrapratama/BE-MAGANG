<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <title>Surat Pengantar Magang</title>

    <style>
        @page {
            size: A4;
            margin: 1.2cm 1.8cm 2cm 2.6cm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
            font-size: 10.5pt;
            line-height: 1.4;
            color: #111111;
        }

        /* =========================
           ORNAMEN SISI KIRI (bagian bawah halaman)
           fixed di dompdf mengacu ke area margin:
           left = posisi dari tepi kertas - margin kiri (2.6cm)
           top  = posisi dari tepi kertas - margin atas (1.2cm)
        ========================= */

        .orn {
            position: fixed;
            left: -2.3cm;   /* = 0.3cm dari tepi kertas */
            width: 1.4cm;
        }

        /* =========================
           KOP SURAT
           ukuran disamakan dengan surat asli (font Helvetica,
           lebar teks: institusi & kementerian 9.3cm, fakultas 7.7cm,
           kontak 4.9cm; total kop 17.35cm)
        ========================= */

        table.kop {
            width: 17.35cm;
            margin-right: -0.75cm;      /* kop boleh melebar sedikit ke margin kanan */
            border-collapse: collapse;
            margin-bottom: 40px;
            font-family: Helvetica, Arial, sans-serif;
        }

        table.kop td {
            vertical-align: middle;
            padding: 0;
        }

        td.kop-logo {
            width: 2.15cm;
            text-align: left;
        }

        td.kop-logo img {
            width: 1.85cm;
            height: auto;
        }

        td.kop-teks {
            width: 9.8cm;
            text-align: left;
            white-space: nowrap;
        }

        .k1 {
            font-size: 8.75pt;
            font-weight: bold;
            line-height: 1.15;
            margin-bottom: 2px;
            color: #000000;
        }

        .k2 {
            font-size: 18pt;
            font-weight: bold;
            line-height: 1.05;
            margin-bottom: 3px;
            color: #000000;
        }

        .k3 {
            font-size: 10.4pt;
            font-weight: normal;
            line-height: 1.15;
            color: #222222;
        }

        td.kop-kontak {
            width: 5.4cm;
            vertical-align: middle;
            text-align: left;
            font-size: 8.2pt;
            font-weight: bold;
            line-height: 10.9pt;
            color: #222222;
            padding-left: 0.5cm !important;
            border-left: 1px solid #999999;
            white-space: nowrap;
        }


        /* =========================
           NOMOR DAN TANGGAL SURAT
        ========================= */

        table.meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        table.meta td {
            vertical-align: top;
            padding: 1px 0;
        }

        td.l {
            width: 90px;
        }

        td.t {
            width: 20px;
        }

        td.r {
            text-align: right;
            width: 200px;
            white-space: nowrap;
        }


        /* =========================
           ISI SURAT
        ========================= */

        p {
            margin: 0 0 10px 0;
            text-align: justify;
        }

        .tujuan {
            margin-bottom: 20px;
        }

        table.mhs {
            margin: 4px 0 14px 20px;
            border-collapse: collapse;
        }

        table.mhs td {
            vertical-align: top;
            padding: 1px 0;
        }


        /* =========================
           TANDA TANGAN
        ========================= */

        table.ttd {
            width: 100%;
            margin-top: 34px;
            page-break-inside: avoid;
        }

        table.ttd td {
            vertical-align: top;
        }

        .ttd-ruang {
            height: 75px;
        }

        .ttd-gambar {
            height: 75px;
            max-width: 180px;
            object-fit: contain;
        }

        .ttd-nama {
            white-space: nowrap;
        }


        /* =========================
           FOOTER (garis biru + slogan)
        ========================= */

        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -1.35cm;
            padding-top: 5pt;
            border-top: 2pt solid #1f56a5;   /* garis biru */
            text-align: center;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 9.7pt;
            color: #333333;
        }

    </style>
</head>

<body>

    {{-- ORNAMEN KIRI: biru (gradasi bertahap) lalu abu, di bagian bawah halaman --}}
    @php
        // [top dari margin atas (cm), tinggi (cm), warna]
        $ornamen = [
            [15.1, 1.2, '#e3edf8'],
            [16.3, 1.2, '#cfe0f2'],
            [17.5, 1.2, '#b9d1ec'],
            [18.7, 1.2, '#a1c2e5'],
            [19.9, 1.2, '#88b0dc'],
            [21.1, 1.2, '#6e9ccf'],
            [22.3, 1.2, '#5a8cc4'],
            [23.5, 1.3, '#b8bbc0'],
            [24.8, 1.3, '#a9acb2'],
            [26.1, 1.3, '#9a9da3'],
            [27.4, 1.3, '#8c8f95'],
        ];
    @endphp

    @foreach ($ornamen as $o)
        <div class="orn" style="top: {{ $o[0] }}cm; height: {{ $o[1] }}cm; background: {{ $o[2] }};"></div>
    @endforeach


    {{-- FOOTER --}}
    <div class="footer">
        {{ $kop['slogan'] }}
    </div>


    {{-- =========================
         KOP SURAT
    ========================== --}}

    <table class="kop">
        <tr>

            {{-- LOGO --}}
            <td class="kop-logo">
                @if ($logo)
                    <img src="{{ $logo }}" alt="Logo IPB">
                @endif
            </td>

            {{-- IDENTITAS INSTITUSI --}}
            <td class="kop-teks">

                <div class="k1">
                    {{ $kop['kementerian'] }}
                </div>

                <div class="k2">
                    {{ $kop['institusi'] }}
                </div>

                <div class="k3">
                    {{ strtoupper($fakultas) }}
                </div>

            </td>

            {{-- KONTAK --}}
            <td class="kop-kontak">

                @foreach ($kop['kontak'] as $baris)
                    {{ $baris }}<br>
                @endforeach

            </td>

        </tr>
    </table>


    {{-- =========================
         NOMOR DAN TANGGAL
    ========================== --}}

    <table class="meta">

        <tr>
            <td class="l">Nomor</td>
            <td class="t">:</td>
            <td>{{ $nomor }}</td>

            <td class="r">
                {{ $kota }}, {{ $tanggalSurat }}
            </td>
        </tr>

        <tr>
            <td class="l">Hal</td>
            <td class="t">:</td>
            <td colspan="2">Pengantar Magang</td>
        </tr>

    </table>


    {{-- =========================
         ALAMAT TUJUAN
    ========================== --}}

    <p class="tujuan">
        Yth. Direktur {{ $instansi }}<br>
        {{ $alamat }}
    </p>


    {{-- =========================
         PEMBUKA SURAT
    ========================== --}}

    <p>
        Dalam rangka meningkatkan keterampilan dan pemahaman mahasiswa
        {{ $fakultas }} IPB tentang Kehutanan dan dunia kerja,
        disampaikan dengan hormat bahwa mahasiswa
        {{ $fakultas }} Institut Pertanian Bogor berikut ini:
    </p>


    {{-- =========================
         DATA MAHASISWA
    ========================== --}}

    <table class="mhs">

        <tr>
            <td style="width: 90px;">Nama</td>
            <td style="width: 14px;">:</td>
            <td>{{ $nama }}</td>
        </tr>

        <tr>
            <td>NIM</td>
            <td>:</td>
            <td>{{ $nim }}</td>
        </tr>

        <tr>
            <td>Departemen</td>
            <td>:</td>
            <td>{{ $departemen }}</td>
        </tr>

    </table>


    {{-- =========================
         ISI SURAT
    ========================== --}}

    <p>
        Bermaksud untuk melaksanakan Magang di {{ $instansi }}
        pada tanggal {{ $tanggalMulai }} &ndash; {{ $tanggalSelesai }}.
        Sehubungan dengan hal tersebut, kami mohon Saudara berkenan
        menerima mahasiswa kami untuk melaksanakan magang tersebut.
    </p>

    <p>
        Demikian atas perhatian dan kerjasamanya, diucapkan terima kasih.
    </p>


    {{-- =========================
         TANDA TANGAN
    ========================== --}}

    <table class="ttd">

        <tr>

            <td style="width: 52%;"></td>

            <td>

                @foreach ($jabatan as $baris)
                    {{ $baris }}<br>
                @endforeach

                @if ($ttd)

                    <img
                        class="ttd-gambar"
                        src="{{ $ttd }}"
                        alt="Tanda tangan"
                    >

                @else

                    <div class="ttd-ruang"></div>

                @endif

                <div class="ttd-nama">
                    {{ $namaPenandatangan }}
                </div>

                <div>
                    NIP {{ $nip }}
                </div>

            </td>

        </tr>

    </table>

</body>

</html>
