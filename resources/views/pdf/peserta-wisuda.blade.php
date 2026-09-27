<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Peserta Wisuda {{ $periode->nama }}</title>
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

    <p class="title">Daftar Mahasiswa Wisuda</p>
    <p class="nomor">{{ $periode->nama }} · {{ $periode->tanggal_acara->translatedFormat('l, d F Y') }}{{ $periode->tempat ? ' · '.$periode->tempat : '' }}</p>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 24px;">No</th>
                <th style="width: 80px;">NIM</th>
                <th>Nama</th>
                <th>Program Studi</th>
                <th style="width: 40px;">Toga</th>
                <th style="width: 40px;">IPK</th>
                <th style="width: 110px;">No. SKL</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($peserta as $i => $w)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $w->mahasiswa?->nim }}</td>
                    <td>{{ $w->pengajuan?->isian['nama_ijazah'] ?? $w->mahasiswa?->user?->name }}</td>
                    <td>{{ $w->mahasiswa?->prodi ? $w->mahasiswa->prodi->jenjang.' '.$w->mahasiswa->prodi->nama_prodi : '-' }}</td>
                    <td class="center">{{ $w->pengajuan?->isian['ukuran_toga'] ?? '-' }}</td>
                    <td class="center">{{ $w->ipk !== null ? number_format($w->ipk, 2, ',', '.') : '-' }}</td>
                    <td>{{ $w->nomor_skl ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="center">Belum ada peserta.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p style="margin-top: 8px; font-size: 9px; color: #555;">Dicetak {{ now()->translatedFormat('d F Y H.i') }} · {{ $peserta->count() }} peserta</p>
</body>
</html>
