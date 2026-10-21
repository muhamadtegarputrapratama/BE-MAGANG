<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Pengantar Magang</title>
    <style>
        @page {
            margin: 1.5cm 2.3cm 2cm 2.3cm;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.45;
            color: #000;
        }

        .kop img {
            width: 100%;
        }

        .kop-teks {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 8px;
            margin-bottom: 18px;
        }

        .kop-teks .k1 { font-size: 10pt; font-weight: bold; }
        .kop-teks .k2 { font-size: 16pt; font-weight: bold; }
        .kop-teks .k3 { font-size: 11pt; }

        table.meta {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
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

        .ttd-ruang {
            height: 70px;
        }

        .ttd-gambar {
            height: 70px;
        }
    </style>
</head>
<body>

    <div class="kop">
        @if($kop)
            <img src="{{ $kop }}" alt="Kop surat">
        @else
            <div class="kop-teks">
                <div class="k1">KEMENTERIAN PENDIDIKAN TINGGI, SAINS, DAN TEKNOLOGI</div>
                <div class="k2">INSTITUT PERTANIAN BOGOR</div>
                <div class="k3">{{ strtoupper($fakultas) }}</div>
            </div>
        @endif
    </div>

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