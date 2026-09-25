<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kartu {{ $jenis === 'remidi' ? 'Remidi' : strtoupper($jenis) }} {{ $mahasiswa->nim }}</title>
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
        .title { text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin: 0 0 2px; }
        .subtitle { text-align: center; font-size: 10px; color: #555; margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        .identitas { margin-bottom: 12px; }
        .identitas td { padding: 2px 0; }
        .identitas .label { width: 110px; color: #555; }
        .grid th, .grid td { border: 1px solid #cccccc; padding: 6px; vertical-align: top; }
        .grid th { background: #f2f2f2; font-size: 9px; text-transform: uppercase; }
        .center { text-align: center; }
        .tidak { color: #b42318; font-weight: bold; }
        .kecil { font-size: 8px; color: #666; }
        .catatan { margin-top: 8px; font-size: 8px; color: #555; }
        .ttd { margin-top: 26px; width: 40%; margin-left: 60%; text-align: center; }
        .ttd .ruang { height: 50px; }
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

    <p class="title">Kartu {{ ['uts' => 'Ujian Tengah Semester', 'uas' => 'Ujian Akhir Semester', 'remidi' => 'Ujian Remidi'][$jenis] }}</p>
    <p class="subtitle">Tahun Akademik {{ $tahun->tahun }} {{ $tahun->semester }}</p>

    <table class="identitas">
        <tr>
            <td class="label">Nama</td><td>: {{ $mahasiswa->user?->name }}</td>
            <td class="label">NIM</td><td>: {{ $mahasiswa->nim }}</td>
        </tr>
        <tr>
            <td class="label">Program Studi</td><td>: {{ $mahasiswa->prodi?->nama_prodi ?? '-' }}{{ $mahasiswa->prodi?->jenjang ? ' ('.$mahasiswa->prodi->jenjang.')' : '' }}</td>
            <td class="label">Dicetak</td><td>: {{ now()->translatedFormat('d F Y H.i') }}</td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 22px;">No</th>
                <th>Mata Kuliah</th>
                <th style="width: 70px;">Kelas</th>
                <th style="width: 120px;">Hari, Tanggal</th>
                <th style="width: 70px;">Jam</th>
                <th style="width: 120px;">Tempat</th>
                @if ($syaratAktif)
                    <th style="width: 80px;">Syarat</th>
                @endif
                <th style="width: 70px;">Paraf Pengawas</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ujians as $i => $u)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $u['nama_matkul'] }}<br><span class="kecil">{{ $u['kode_matkul'] }} · {{ $u['sks'] }} SKS</span></td>
                    <td class="center">{{ $u['kode_kelas'] }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($u['tanggal'])->translatedFormat('l, d M Y') }}</td>
                    <td class="center">{{ substr($u['jam_mulai'], 0, 5) }}–{{ substr($u['jam_akhir'], 0, 5) }}</td>
                    <td>{{ $u['mode'] === 'tatap_muka' ? ($u['ruang'] ?? '-') : $u['label_mode'] }}</td>
                    @if ($syaratAktif)
                        @php($s = $u['syarat'])
                        <td class="center {{ ($s['memenuhi'] ?? true) ? '' : 'tidak' }}">
                            {{ ($s['dispensasi'] ?? null) ? 'Dispensasi' : (($s['memenuhi'] ?? true) ? 'Memenuhi' : 'Tidak memenuhi') }}
                        </td>
                    @endif
                    <td></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="catatan">
        Bawa kartu ini dan tunjukkan kepada pengawas pada setiap ujian tatap muka. Ujian online dikerjakan melalui menu Jadwal Ujian di SIA
        sesuai jam yang tertera.
        @if ($syaratAktif) Mahasiswa yang tidak memenuhi syarat kehadiran tidak dapat mengikuti ujian kecuali mendapat dispensasi. @endif
    </p>

    <div class="ttd">
        <p>Bagian Akademik,</p>
        <div class="ruang"></div>
        <p>(................................................)</p>
    </div>
</body>
</html>
