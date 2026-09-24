<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Hadir {{ strtoupper($jenis) }} {{ $kelas->kode_kelas }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a1a; margin: 0; }
        .header { border-bottom: 2px solid #0075de; padding-bottom: 8px; margin-bottom: 12px; }
        .kop { width: 100%; }
        .kop td { vertical-align: middle; }
        .kop-logo { width: 66px; }
        .kop-logo img { max-height: 58px; max-width: 58px; }
        .kop-teks h1 { font-size: 14px; margin: 0; text-transform: uppercase; }
        .header p { margin: 2px 0 0; font-size: 9px; color: #555; }
        .title { text-align: center; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        .identitas { margin-bottom: 12px; }
        .identitas td { padding: 2px 0; }
        .identitas .label { width: 110px; color: #555; }
        .grid th, .grid td { border: 1px solid #cccccc; padding: 6px; }
        .grid th { background: #f2f2f2; font-size: 9px; text-transform: uppercase; }
        .center { text-align: center; }
        .tidak { color: #b42318; font-weight: bold; }
        .ttd-kolom { width: 110px; }
        .keterangan { margin-top: 6px; font-size: 8px; color: #555; }
        .ttd { margin-top: 28px; width: 40%; margin-left: 60%; text-align: center; }
        .ttd .ruang { height: 55px; }
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

    <p class="title">Daftar Hadir {{ $jenis === 'uts' ? 'Ujian Tengah Semester' : 'Ujian Akhir Semester' }}</p>

    <table class="identitas">
        <tr>
            <td class="label">Mata Kuliah</td><td>: {{ $kelas->mataKuliah?->kode_matkul }} — {{ $kelas->mataKuliah?->nama_matkul }}</td>
            <td class="label">Tahun Akademik</td><td>: {{ $kelas->tahunAkademik?->tahun }} {{ $kelas->tahunAkademik?->semester }}</td>
        </tr>
        <tr>
            <td class="label">Kelas</td><td>: {{ $kelas->kode_kelas }}</td>
            <td class="label">Hari, Tanggal</td><td>: {{ $jadwal->tanggal->translatedFormat('l, d F Y') }}</td>
        </tr>
        <tr>
            <td class="label">Dosen</td><td>: {{ $kelas->dosen?->user?->name ?? '-' }}</td>
            <td class="label">Jam / Ruang</td><td>: {{ substr($jadwal->jam_mulai, 0, 5) }}–{{ substr($jadwal->jam_akhir, 0, 5) }} / {{ $jadwal->ruang?->kode_ruang ?? '-' }}</td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 26px;">No</th>
                <th style="width: 90px;">NIM</th>
                <th>Nama</th>
                <th style="width: 70px;">Kehadiran</th>
                @if ($aktif)
                    <th style="width: 110px;">Syarat Ujian</th>
                @endif
                <th class="ttd-kolom">Tanda Tangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($peserta as $i => $mhs)
                @php($syarat = $mhs['syarat'])
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $mhs['nim'] }}</td>
                    <td>{{ $mhs['nama'] }}</td>
                    <td class="center">{{ isset($syarat['persen']) ? $syarat['persen'].'%' : '-' }}</td>
                    @if ($aktif)
                        <td class="center {{ ($syarat['memenuhi'] ?? true) ? '' : 'tidak' }}">
                            @if ($syarat['dispensasi'] ?? null)
                                Dispensasi
                            @elseif ($syarat['memenuhi'] ?? true)
                                Memenuhi
                            @else
                                Tidak memenuhi
                            @endif
                        </td>
                    @endif
                    <td></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="keterangan">
        Kehadiran dihitung dari pertemuan kuliah yang sudah selesai{{ $jenis === 'uts' ? ' sebelum UTS' : '' }}; izin dan sakit dihitung tidak hadir.
        @if ($aktif) Batas minimal {{ $min }}%. @endif
    </p>

    <div class="ttd">
        <p>Pengawas / Dosen,</p>
        <div class="ruang"></div>
        <p>(................................................)</p>
    </div>
</body>
</html>
