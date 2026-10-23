<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Pengantar Magang</title>
    <style>
        @page {
            margin: 1.3cm 2.3cm 2.2cm 2.3cm;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.45;
            color: #000;
        }

        table.kop {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 26px;
        }

        table.kop td {
            vertical-align: middle;
            padding: 0;
        }

        td.kop-logo { width: 70px; }
        td.kop-logo img { width: 62px; height: 62px; }
        td.kop-teks { padding-left: 6px; }

        .k1 { font-size: 8pt; font-weight: bold; }
        .k2 { font-size: 16pt; font-weight: bold; line-height: 1.15; }
        .k3 { font-size: 10pt; color: #444; }

        td.kop-kontak {
            width: 200px;
            border-left: 1px solid #000;
            padding-left: 10px;
            font-size: 8pt;
            line-height: 1.4;
        }

        table.meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        table.meta td {
            vertical-align: top;
            padding: 1px 0;
        }

        td.l { width: 60px; }
        td.t { width: 14px; }
        td.r { text-align: right; width: 190px; }

        p {
            margin: 0 0 10px 0;
            text-align: justify;
        }

        table.mhs {
            margin: 4px 0 12px 20px;
            border-collapse: collapse;
        }

        table.mhs td {
            vertical-align: top;
            padding: 1px 0;
        }

        table.ttd {
            width: 100%;
            margin-top: 22px;
            page-break-inside: avoid;
        }

        .ttd-ruang  { height: 70px; }
        .ttd-gambar { height: 70px; }

        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -1.5cm;
            text-align: center;
            font-size: 11pt;
            color: #444;
        }
    </style>
</head>
<body>

    <div class="footer">{{ $kop['slogan'] }}</div>

    <table class="kop">
        <tr>
            <td class="kop-logo">
                @if($logo)
                    <img src="{{ $logo }}" alt="Logo IPB">
                @endif
            </td>
            <td class="kop-teks">
                <div class="k1">{{ $kop['kementerian'] }}</div>
                <div class="k2">{{ $kop['institusi'] }}</div>
                <div class="k3">{{ strtoupper($fakultas) }}</div>
            </td>
            <td class="kop-kontak">
                @foreach($kop['kontak'] as $baris)
                    {{ $baris }}<br>
                @endforeach
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td class="l">Nomor</td>
            <td class="t">:</td>
            <td>{{ $nomor }}</td>
            <td class="r">{{ $kota }}, {{ $tanggalSurat }}</td>
        </tr>
        <tr>
            <td class="l">Hal</td>
            <td class="t">:</td>
            <td colspan="2">Pengantar Magang</td>
        </tr>
    </table>

    <p>
        Yth. Direktur {{ $instansi }}<br>
        {{ $alamat }}
    </p>

    <p>
        Dalam rangka meningkatkan keterampilan dan pemahaman mahasiswa {{ $fakultas }} IPB
        tentang Kehutanan dan dunia kerja, disampaikan dengan hormat bahwa mahasiswa
        {{ $fakultas }} Institut Pertanian Bogor berikut ini :
    </p>

    <table class="mhs">
        <tr><td style="width: 90px;">Nama</td><td style="width: 14px;">:</td><td>{{ $nama }}</td></tr>
        <tr><td>NIM</td><td>:</td><td>{{ $nim }}</td></tr>
        <tr><td>Departemen</td><td>:</td><td>{{ $departemen }}</td></tr>
    </table>

    <p>
        Bermaksud untuk melaksanakan Magang di {{ $instansi }} pada tanggal
        {{ $tanggalMulai }} &ndash; {{ $tanggalSelesai }}. Sehubungan dengan hal tersebut,
        kami mohon Saudara berkenan menerima mahasiswa kami untuk melaksanakan magang tersebut.
    </p>

    <p>Demikian atas perhatian dan kerjasamanya, diucapkan terima kasih.</p>

    <table class="ttd">
        <tr>
            <td style="width: 50%;"></td>
            <td>
                @foreach($jabatan as $baris)
                    {{ $baris }}<br>
                @endforeach

                @if($ttd)
                    <img class="ttd-gambar" src="{{ $ttd }}" alt="Tanda tangan">
                @else
                    <div class="ttd-ruang"></div>
                @endif

                {{ $namaPenandatangan }}<br>
                NIP {{ $nip }}
            </td>
        </tr>
    </table>

</body>
</html>