<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>SKL {{ $wisuda->mahasiswa?->nim }}</title>
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

    <p class="title">Surat Keterangan Lulus</p>
    <p class="nomor">Nomor: {{ $wisuda->nomor_skl }}</p>

    <p>Yang bertanda tangan di bawah ini menerangkan bahwa:</p>

    <table class="data" style="margin-bottom: 10px;">
        <tr><td class="label">Nama</td><td>: {{ $isian['nama_ijazah'] ?? $wisuda->mahasiswa?->user?->name }}</td></tr>
        <tr><td class="label">NIM</td><td>: {{ $wisuda->mahasiswa?->nim }}</td></tr>
        <tr><td class="label">Tempat, tanggal lahir</td><td>: {{ $isian['tempat_lahir'] ?? '-' }}, {{ isset($isian['tanggal_lahir']) ? \Illuminate\Support\Carbon::parse($isian['tanggal_lahir'])->translatedFormat('d F Y') : '-' }}</td></tr>
        <tr><td class="label">Program Studi</td><td>: {{ $wisuda->mahasiswa?->prodi?->nama_prodi ?? '-' }}{{ $wisuda->mahasiswa?->prodi?->jenjang ? ' ('.$wisuda->mahasiswa->prodi->jenjang.')' : '' }}</td></tr>
        @if ($wisuda->mahasiswa?->prodi?->fakultas)
            <tr><td class="label">Fakultas</td><td>: {{ $wisuda->mahasiswa->prodi->fakultas->nama_fakultas }}</td></tr>
        @endif
    </table>

    <p>telah menyelesaikan seluruh kewajiban akademik dan dinyatakan <strong>LULUS</strong> dengan keterangan:</p>

    <table class="data" style="margin-bottom: 12px;">
        <tr><td class="label">Tanggal lulus</td><td>: {{ $wisuda->tanggal_lulus?->translatedFormat('d F Y') }}</td></tr>
        <tr><td class="label">Judul tugas akhir</td><td>: {{ $wisuda->tugasAkhir?->judul }}</td></tr>
        <tr><td class="label">Jumlah SKS</td><td>: {{ $wisuda->total_sks }}</td></tr>
        <tr><td class="label">IPK</td><td>: {{ number_format((float) $wisuda->ipk, 2, ',', '.') }}</td></tr>
        <tr><td class="label">Predikat</td><td>: {{ $wisuda->predikat }}</td></tr>
    </table>

    <p>Surat keterangan ini berlaku sebagai pengganti ijazah sampai ijazah diterbitkan, dan dibuat untuk dipergunakan sebagaimana mestinya.</p>

    <div class="ttd">
        <p>{{ $wisuda->skl_terbit_at?->translatedFormat('d F Y') }}</p>
        <p>Bagian Akademik</p>
        <div class="ruang"></div>
        <p>( ............................................ )</p>
    </div>
</body>
</html>
