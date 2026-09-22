<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu Hasil Studi</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; margin: 0; }
        .header { border-bottom: 2px solid #0075de; padding-bottom: 10px; margin-bottom: 14px; }
        .kop { width: 100%; }
        .kop td { vertical-align: middle; }
        .kop-logo { width: 72px; }
        .kop-logo img { max-height: 64px; max-width: 64px; }
        .kop-teks h1 { font-size: 15px; margin: 0; letter-spacing: 0.3px; text-transform: uppercase; }
        .header p { margin: 3px 0 0; font-size: 10px; color: #555; }
        .title { text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 4px; }
        .subtitle { text-align: center; font-size: 10px; color: #555; margin: 0 0 14px; }
        table { width: 100%; border-collapse: collapse; }
        .identitas { margin-bottom: 14px; }
        .identitas td { padding: 3px 0; vertical-align: top; }
        .identitas .label { width: 130px; color: #555; }
        .identitas .separator { width: 10px; color: #555; }
        .nilai th, .nilai td { border: 1px solid #cccccc; padding: 6px 8px; }
        .nilai th { background: #f2f2f2; font-size: 10px; text-transform: uppercase; letter-spacing: 0.4px; }
        .nilai .center { text-align: center; }
        .nilai .kosong { text-align: center; padding: 22px; color: #666; }
        .ringkasan { margin-top: 14px; }
        .ringkasan td { padding: 3px 0; }
        .ringkasan .label { width: 200px; color: #555; }
        .ringkasan .value { font-weight: bold; }
        .ttd { margin-top: 40px; }
        .ttd td { width: 50%; text-align: center; font-size: 10px; }
        .ttd .ruang { height: 55px; }
        .catatan { margin-top: 18px; font-size: 9px; color: #777; border-top: 1px solid #e0e0e0; padding-top: 6px; }
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
                    @if ($mahasiswa->prodi?->nama_prodi)
                        <p>{{ $mahasiswa->prodi->nama_prodi }}@if ($mahasiswa->prodi->jenjang) — {{ $mahasiswa->prodi->jenjang }}@endif</p>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <p class="title">Kartu Hasil Studi</p>
    <p class="subtitle">
        {{ $tahunAkademik?->tahun ?? '-' }}
        @if ($tahunAkademik?->semester) — Semester {{ $tahunAkademik->semester }} @endif
    </p>

    <table class="identitas">
        <tr>
            <td class="label">Nama Mahasiswa</td>
            <td class="separator">:</td>
            <td>{{ $mahasiswa->user?->name ?? '-' }}</td>
            <td class="label">NIM</td>
            <td class="separator">:</td>
            <td>{{ $mahasiswa->nim ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Program Studi</td>
            <td class="separator">:</td>
            <td>{{ $mahasiswa->prodi?->nama_prodi ?? '-' }}</td>
            <td class="label">Angkatan</td>
            <td class="separator">:</td>
            <td>{{ $mahasiswa->angkatan ?? '-' }}</td>
        </tr>
    </table>

    <table class="nilai">
        <thead>
            <tr>
                <th style="width: 32px;">No.</th>
                <th style="width: 70px;">Kode</th>
                <th>Mata Kuliah</th>
                <th style="width: 40px;">SKS</th>
                <th style="width: 55px;">Nilai</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($krs as $index => $item)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $item->kelasKuliah?->mataKuliah?->kode_matkul ?? '-' }}</td>
                    <td>{{ $item->kelasKuliah?->mataKuliah?->nama_matkul ?? '-' }}</td>
                    <td class="center">{{ $item->kelasKuliah?->mataKuliah?->sks ?? '-' }}</td>
                    <td class="center">{{ $item->nilai ?? '-' }}</td>
                </tr>
            @empty
                <tr><td class="kosong" colspan="5">Belum ada hasil studi pada tahun akademik ini.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="ringkasan">
        <tr>
            <td class="label">Total SKS Diambil</td>
            <td class="value">: {{ $ringkasan['totalSks'] }}</td>
        </tr>
        <tr>
            <td class="label">Total SKS Dinilai</td>
            <td class="value">: {{ $ringkasan['totalSksDinilai'] }}</td>
        </tr>
        <tr>
            <td class="label">Indeks Prestasi</td>
            <td class="value">: {{ $ringkasan['ip'] !== null ? number_format($ringkasan['ip'], 2) : '-' }}</td>
        </tr>
    </table>

    <table class="ttd">
        <tr>
            <td>Mengetahui,<br>Dosen Pembimbing Akademik</td>
            <td>Dicetak pada {{ now()->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="ruang"></td>
            <td class="ruang"></td>
        </tr>
        <tr>
            <td>{{ $mahasiswa->dosenWali?->user?->name ?? '..............................' }}</td>
            <td></td>
        </tr>
    </table>

    <p class="catatan">Dokumen ini dihasilkan secara otomatis oleh sistem akademik.</p>
</body>
</html>
