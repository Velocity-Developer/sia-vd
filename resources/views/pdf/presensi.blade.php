<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Presensi {{ $kelas->kode_kelas }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a1a1a; margin: 0; }
        .header { border-bottom: 2px solid #0075de; padding-bottom: 8px; margin-bottom: 10px; }
        .kop { width: 100%; }
        .kop td { vertical-align: middle; }
        .kop-logo { width: 62px; }
        .kop-logo img { max-height: 54px; max-width: 54px; }
        .kop-teks h1 { font-size: 13px; margin: 0; text-transform: uppercase; }
        .header p { margin: 2px 0 0; font-size: 9px; color: #555; }
        .title { text-align: center; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        .identitas { margin-bottom: 10px; }
        .identitas td { padding: 2px 0; }
        .identitas .label { width: 95px; color: #555; }
        .grid th, .grid td { border: 1px solid #cccccc; padding: 3px 4px; }
        .grid th { background: #f2f2f2; font-size: 8px; text-transform: uppercase; }
        .center { text-align: center; }
        .kecil { font-size: 7px; color: #666; }
        .kurang { color: #b42318; font-weight: bold; }
        h2 { font-size: 10px; text-transform: uppercase; margin: 14px 0 6px; }
        .keterangan { margin-top: 6px; font-size: 8px; color: #555; }
        .ttd { margin-top: 24px; width: 40%; margin-left: 60%; text-align: center; font-size: 9px; }
        .ttd .ruang { height: 50px; }
        .halaman-baru { page-break-before: always; }
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

    <p class="title">Rekap Presensi Perkuliahan</p>

    <table class="identitas">
        <tr>
            <td class="label">Mata Kuliah</td><td>: {{ $kelas->mataKuliah?->kode_matkul }} — {{ $kelas->mataKuliah?->nama_matkul }} ({{ $kelas->mataKuliah?->sks }} SKS)</td>
            <td class="label">Tahun Akademik</td><td>: {{ $kelas->tahunAkademik?->tahun }} {{ $kelas->tahunAkademik?->semester }}</td>
        </tr>
        <tr>
            <td class="label">Kelas</td><td>: {{ $kelas->kode_kelas }}</td>
            <td class="label">Program Studi</td><td>: {{ $kelas->mataKuliah?->prodi?->nama_prodi ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Dosen Pengampu</td><td>: {{ $kelas->dosen?->user?->name ?? '-' }} (NIDN {{ $kelas->dosen?->nidn ?? '-' }})</td>
            <td class="label">Jumlah Pertemuan</td><td>: {{ $kelas->jumlah_pertemuan }}</td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 18px;">No</th>
                <th>NIM</th>
                <th>Nama</th>
                @foreach ($pertemuan as $p)
                    <th class="center">{{ $p->jenis === 'kuliah' ? $p->pertemuan_ke : strtoupper($p->jenis) }}<br><span class="kecil">{{ $p->tanggal->format('d/m') }}</span></th>
                @endforeach
                <th class="center">H</th>
                <th class="center">T</th>
                <th class="center">I</th>
                <th class="center">S</th>
                <th class="center">A</th>
                <th class="center">%</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($peserta as $i => $mhs)
                @php($rekap = $mhs['rekap'])
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $mhs['nim'] }}</td>
                    <td>{{ $mhs['nama'] }}</td>
                    @foreach ($pertemuan as $p)
                        <td class="center">{{ $p->status === 'dibatalkan' ? '×' : $singkat($mhs['presensi'][$p->id] ?? null) }}</td>
                    @endforeach
                    <td class="center">{{ $rekap['hadir'] ?? 0 }}</td>
                    <td class="center">{{ $rekap['terlambat'] ?? 0 }}</td>
                    <td class="center">{{ $rekap['izin'] ?? 0 }}</td>
                    <td class="center">{{ $rekap['sakit'] ?? 0 }}</td>
                    <td class="center">{{ $rekap['alpa'] ?? 0 }}</td>
                    <td class="center {{ isset($rekap['persen']) && $rekap['persen'] < $minKehadiran ? 'kurang' : '' }}">{{ $rekap['persen'] ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $pertemuan->count() + 9 }}" class="center">Belum ada mahasiswa.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="keterangan">
        H = Hadir, T = Terlambat, I = Izin, S = Sakit, A = Alpa, × = pertemuan dibatalkan. Persentase dihitung dari pertemuan kuliah yang sudah selesai;
        izin dan sakit dihitung tidak hadir. Batas minimal kehadiran ujian {{ $minKehadiran }}% (angka merah = di bawah batas).
    </p>

    <h2 class="halaman-baru">Jurnal Perkuliahan</h2>
    <table class="grid">
        <thead>
            <tr>
                <th style="width: 26px;">Ke</th>
                <th style="width: 110px;">Tanggal</th>
                <th style="width: 70px;">Jam</th>
                <th>Topik / Realisasi Materi</th>
                <th style="width: 130px;">Dosen</th>
                <th style="width: 70px;">Masuk–Keluar</th>
                <th style="width: 50px;">Hadir</th>
                <th style="width: 70px;">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pertemuan as $p)
                <tr>
                    <td class="center">{{ $p->pertemuan_ke }}{{ $p->jenis !== 'kuliah' ? ' '.strtoupper($p->jenis) : '' }}</td>
                    <td>{{ $p->tanggal->translatedFormat('l, d M Y') }}</td>
                    <td>{{ substr($p->jam_mulai, 0, 5) }}–{{ substr($p->jam_akhir, 0, 5) }}</td>
                    <td>{{ $p->topik ?? ($p->status === 'dibatalkan' ? 'Dibatalkan: '.$p->catatan : '') }}</td>
                    <td>{{ $p->dosen?->user?->name ?? $kelas->dosen?->user?->name }}</td>
                    <td class="center">{{ $p->dosen_masuk_at?->format('H:i') ?? '-' }}–{{ $p->dosen_keluar_at?->format('H:i') ?? '-' }}</td>
                    <td class="center">{{ $p->jumlah_tercatat ? $p->jumlah_hadir.'/'.$p->jumlah_tercatat : '-' }}</td>
                    <td>{{ $p->terlewat() ? 'Terlewat' : ucfirst($p->status) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="ttd">
        <p>Dosen Pengampu,</p>
        <div class="ruang"></div>
        <p><strong>{{ $kelas->dosen?->user?->name ?? '-' }}</strong><br>NIDN {{ $kelas->dosen?->nidn ?? '-' }}</p>
    </div>
</body>
</html>
