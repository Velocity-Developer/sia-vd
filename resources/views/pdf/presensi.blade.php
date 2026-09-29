@extends('pdf.layout')

@section('judul-berkas', 'Rekap Presensi '.$kelas->kode_kelas)

@section('gaya')
    body { font-size: 8.5pt; }
    .grid { font-size: 7.8pt; }
    .grid th, .grid td { padding: 3px 3px; }
    .grid th { font-size: 7.2pt; }
    .identitas .label { width: 105px; }
    .kecil { font-size: 6.6pt; }
@endsection

@section('isi')
    <p class="judul">Rekap Presensi Perkuliahan</p>
    <p class="subjudul">Tahun Akademik {{ $kelas->tahunAkademik?->tahun }} Semester {{ $kelas->tahunAkademik?->semester }}</p>

    <table class="identitas">
        <tr>
            <td class="label">Mata Kuliah</td><td class="titik">:</td><td>{{ $kelas->mataKuliah?->kode_matkul }} – {{ $kelas->mataKuliah?->nama_matkul }} ({{ $kelas->mataKuliah?->sks }} SKS)</td>
            <td class="label">Program Studi</td><td class="titik">:</td><td>{{ $kelas->mataKuliah?->prodi?->nama_prodi ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Kelas</td><td class="titik">:</td><td>{{ $kelas->kode_kelas }}</td>
            <td class="label">Jumlah Pertemuan</td><td class="titik">:</td><td>{{ $kelas->jumlah_pertemuan }}</td>
        </tr>
        <tr>
            <td class="label">Dosen Pengampu</td><td class="titik">:</td><td>{{ $kelas->dosen?->user?->name ?? '-' }} (NIDN {{ $kelas->dosen?->nidn ?? '-' }})</td>
            <td class="label"></td><td class="titik"></td><td></td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 18px;">No</th>
                <th>NIM</th>
                <th>Nama</th>
                @foreach ($pertemuan as $p)
                    <th class="tengah">{{ $p->jenis === 'kuliah' ? $p->pertemuan_ke : strtoupper($p->jenis) }}<br><span class="kecil">{{ $p->tanggal->format('d/m') }}</span></th>
                @endforeach
                <th class="tengah">H</th>
                <th class="tengah">T</th>
                <th class="tengah">I</th>
                <th class="tengah">S</th>
                <th class="tengah">A</th>
                <th class="tengah">%</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($peserta as $i => $mhs)
                @php($rekap = $mhs['rekap'])
                <tr>
                    <td class="tengah">{{ $i + 1 }}</td>
                    <td>{{ $mhs['nim'] }}</td>
                    <td>{{ $mhs['nama'] }}</td>
                    @foreach ($pertemuan as $p)
                        <td class="tengah">{{ $p->status === 'dibatalkan' ? '×' : $singkat($mhs['presensi'][$p->id] ?? null) }}</td>
                    @endforeach
                    <td class="tengah">{{ $rekap['hadir'] ?? 0 }}</td>
                    <td class="tengah">{{ $rekap['terlambat'] ?? 0 }}</td>
                    <td class="tengah">{{ $rekap['izin'] ?? 0 }}</td>
                    <td class="tengah">{{ $rekap['sakit'] ?? 0 }}</td>
                    <td class="tengah">{{ $rekap['alpa'] ?? 0 }}</td>
                    <td class="tengah {{ isset($rekap['persen']) && $rekap['persen'] < $minKehadiran ? 'merah' : '' }}">{{ $rekap['persen'] ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $pertemuan->count() + 9 }}" class="tengah">Belum ada mahasiswa.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="catatan">
        H = Hadir, T = Terlambat, I = Izin, S = Sakit, A = Alpa, × = pertemuan dibatalkan. Persentase dihitung dari pertemuan kuliah yang sudah selesai;
        izin dan sakit dihitung tidak hadir. Batas minimal kehadiran ujian {{ $minKehadiran }}% (angka merah = di bawah batas).
    </p>

    <p class="judul halaman-baru">Jurnal Perkuliahan</p>
    <p class="subjudul">{{ $kelas->mataKuliah?->kode_matkul }} – {{ $kelas->mataKuliah?->nama_matkul }} · Kelas {{ $kelas->kode_kelas }}</p>
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
                    <td class="tengah">{{ $p->pertemuan_ke }}{{ $p->jenis !== 'kuliah' ? ' '.strtoupper($p->jenis) : '' }}</td>
                    <td>{{ $p->tanggal->translatedFormat('l, d M Y') }}</td>
                    <td>{{ substr($p->jam_mulai, 0, 5) }}–{{ substr($p->jam_akhir, 0, 5) }}</td>
                    <td>{{ $p->topik ?? ($p->status === 'dibatalkan' ? 'Dibatalkan: '.$p->catatan : '') }}</td>
                    <td>{{ $p->dosen?->user?->name ?? $kelas->dosen?->user?->name }}</td>
                    <td class="tengah">{{ $p->dosen_masuk_at?->format('H:i') ?? '-' }}–{{ $p->dosen_keluar_at?->format('H:i') ?? '-' }}</td>
                    <td class="tengah">{{ $p->jumlah_tercatat ? $p->jumlah_hadir.'/'.$p->jumlah_tercatat : '-' }}</td>
                    <td>{{ $p->terlewat() ? 'Terlewat' : ucfirst($p->status) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="ttd-tunggal">
        <p>{{ now()->translatedFormat('d F Y') }}<br>Dosen Pengampu,</p>
        <div class="ruang"></div>
        <p><span class="tebal" style="text-decoration: underline;">{{ $kelas->dosen?->user?->name ?? '-' }}</span><br>NIDN. {{ $kelas->dosen?->nidn ?: '....................' }}</p>
    </div>
@endsection
