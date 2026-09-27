<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Surat Pendadaran {{ $pendadaran->mahasiswa?->nim }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1a1a1a; margin: 0; line-height: 1.45; }
        .header { border-bottom: 2px solid #0075de; padding-bottom: 8px; margin-bottom: 14px; }
        .kop { width: 100%; }
        .kop td { vertical-align: middle; }
        .kop-logo { width: 66px; }
        .kop-logo img { max-height: 58px; max-width: 58px; }
        .kop-teks h1 { font-size: 14px; margin: 0; text-transform: uppercase; }
        .header p { margin: 2px 0 0; font-size: 9px; color: #555; }
        .title { text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin: 0; }
        .nomor { text-align: center; font-size: 10px; color: #555; margin: 2px 0 14px; }
        table { width: 100%; border-collapse: collapse; }
        .data td { padding: 2px 0; vertical-align: top; }
        .data .label { width: 120px; color: #555; }
        .grid th, .grid td { border: 1px solid #cccccc; padding: 6px; vertical-align: top; }
        .grid th { background: #f2f2f2; font-size: 9px; text-transform: uppercase; }
        .center { text-align: center; }
        .ttd { margin-top: 28px; width: 42%; margin-left: 58%; text-align: center; }
        .ttd .ruang { height: 56px; }
        p { margin: 0 0 8px; }
    </style>
</head>
<body>
    <div class="header">
        <table class="kop">
            <tr>
                @if ($logoSrc)
                    <td class="kop-logo"><img src="{{ $logoSrc }}" alt="Logo {{ $institusi->nama_pt }}"></td>
                @endif
                <td class="kop-teks">
                    <h1>{{ $institusi->nama_pt }}</h1>
                    @if ($kontak)
                        <p>{{ implode(' | ', $kontak) }}</p>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <p class="title">Surat Tugas &amp; Undangan Pendadaran</p>
    <p class="nomor">Nomor: {{ $pendadaran->nomor_surat ?? '-' }}</p>

    <p>Dengan hormat, bersama ini kami menugaskan Bapak/Ibu dosen yang namanya tercantum di bawah sebagai penguji pada pendadaran (ujian akhir tugas akhir) mahasiswa berikut:</p>

    <table class="data" style="margin-bottom: 10px;">
        <tr><td class="label">Nama</td><td>: {{ $pendadaran->mahasiswa?->user?->name }}</td></tr>
        <tr><td class="label">NIM</td><td>: {{ $pendadaran->mahasiswa?->nim }}</td></tr>
        <tr><td class="label">Program Studi</td><td>: {{ $pendadaran->mahasiswa?->prodi?->nama_prodi ?? '-' }}{{ $pendadaran->mahasiswa?->prodi?->jenjang ? ' ('.$pendadaran->mahasiswa->prodi->jenjang.')' : '' }}</td></tr>
        <tr><td class="label">Judul</td><td>: {{ $pendadaran->tugasAkhir?->judul }}</td></tr>
        <tr><td class="label">Pembimbing</td><td>: {{ implode(', ', $pendadaran->tugasAkhir?->namaPembimbing() ?? []) }}</td></tr>
    </table>

    <p>yang akan dilaksanakan pada:</p>
    <table class="data" style="margin-bottom: 12px;">
        <tr><td class="label">Hari, tanggal</td><td>: {{ $pendadaran->tanggal->translatedFormat('l, d F Y') }}</td></tr>
        <tr><td class="label">Waktu</td><td>: {{ substr($pendadaran->jam_mulai, 0, 5) }}–{{ substr($pendadaran->jam_akhir, 0, 5) }} WIB</td></tr>
        <tr><td class="label">Tempat</td><td>: {{ trim(($pendadaran->ruang?->kode_ruang ?? '').' '.($pendadaran->ruang?->nama_ruang ?? '')) }}</td></tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 24px;">No</th>
                <th>Nama Penguji</th>
                <th style="width: 120px;">Jabatan</th>
                <th style="width: 110px;">Tanda Tangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pendadaran->daftarPenguji() as $i => $penguji)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $penguji['nama'] }}</td>
                    <td>{{ $penguji['peran'] }}</td>
                    <td style="height: 30px;"></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top: 12px;">Mahasiswa diharap hadir 15 menit sebelum pendadaran dimulai dengan membawa naskah tugas akhir. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.</p>

    <div class="ttd">
        <p>{{ $pendadaran->created_at?->translatedFormat('d F Y') }}</p>
        <p>Bagian Akademik</p>
        <div class="ruang"></div>
        <p>( ............................................ )</p>
    </div>
</body>
</html>
