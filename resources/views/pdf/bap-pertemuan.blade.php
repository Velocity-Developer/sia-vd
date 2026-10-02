@extends('pdf.layout')

@section('judul-berkas', 'BAP '.$kelas->kode_kelas.' Pertemuan '.$pertemuan->pertemuan_ke)

@section('isi')
    @include('pdf.partials.draf')

    <p class="judul">Berita Acara Perkuliahan</p>
    <p class="subjudul">Tahun Akademik {{ $kelas->tahunAkademik?->tahun }} Semester {{ $kelas->tahunAkademik?->semester }}</p>

    @php
        $pengajar = $pertemuan->dosen ?? $kelas->dosen;
        $status = $pertemuan->statusDosen($kelas->dosen_id);
    @endphp
    <table class="identitas">
        <tr>
            <td class="label">Mata Kuliah</td><td class="titik">:</td><td>{{ $kelas->mataKuliah?->kode_matkul }} – {{ $kelas->mataKuliah?->nama_matkul }} ({{ $kelas->mataKuliah?->sks }} SKS)</td>
        </tr>
        <tr><td class="label">Program Studi</td><td class="titik">:</td><td>{{ trim(($kelas->mataKuliah?->prodi?->jenjang ?? '').' '.($kelas->mataKuliah?->prodi?->nama_prodi ?? '-')) }}</td></tr>
        <tr><td class="label">Kelas</td><td class="titik">:</td><td>{{ $kelas->kode_kelas }}</td></tr>
        <tr><td class="label">Dosen Pengampu</td><td class="titik">:</td><td>{{ $kelas->dosen?->user?->name ?? '-' }} (NIDN {{ $kelas->dosen?->nidn ?? '-' }})</td></tr>
        @if ($status === 'digantikan')
            <tr><td class="label">Dosen Pengganti</td><td class="titik">:</td><td>{{ $pengajar?->user?->name ?? '-' }} (NIDN {{ $pengajar?->nidn ?? '-' }})</td></tr>
        @endif
        <tr>
            <td class="label">Pertemuan</td><td class="titik">:</td>
            <td>Ke-{{ $pertemuan->pertemuan_ke }}{{ $pertemuan->jenis !== 'kuliah' ? ' ('.strtoupper($pertemuan->jenis).')' : '' }}, {{ $pertemuan->tanggal->translatedFormat('l, d F Y') }}</td>
        </tr>
        <tr>
            <td class="label">Jadwal / Ruang</td><td class="titik">:</td>
            <td>{{ substr($pertemuan->jam_mulai, 0, 5) }}–{{ substr($pertemuan->jam_akhir, 0, 5) }} · {{ $pertemuan->ruang ? $pertemuan->ruang->kode_ruang.' – '.$pertemuan->ruang->nama_ruang : '-' }}</td>
        </tr>
        <tr>
            <td class="label">Kehadiran Dosen</td><td class="titik">:</td>
            <td>{{ $status ? $statusDosen[$status] : '-' }}, masuk {{ $pertemuan->dosen_masuk_at?->format('H.i') ?? '-' }}, keluar {{ $pertemuan->dosen_keluar_at?->format('H.i') ?? '-' }}</td>
        </tr>
    </table>

    <table class="grid" style="margin-bottom: 10px;">
        <thead><tr><th style="text-align: left;">Topik / Realisasi Materi</th></tr></thead>
        <tbody><tr><td style="min-height: 60px; white-space: pre-line;">{{ $pertemuan->topik ?: '-' }}</td></tr></tbody>
    </table>

    <table class="ringkasan">
        <tr>
            <td class="label">Jumlah mahasiswa</td><td>: {{ $presensi->count() }}</td>
        </tr>
        <tr>
            <td class="label">Hadir (termasuk terlambat)</td><td>: {{ ($rekap['hadir'] ?? 0) + ($rekap['terlambat'] ?? 0) }}</td>
        </tr>
        <tr>
            <td class="label">Izin / Sakit / Alpa</td><td>: {{ $rekap['izin'] ?? 0 }} / {{ $rekap['sakit'] ?? 0 }} / {{ $rekap['alpa'] ?? 0 }}</td>
        </tr>
    </table>

    <p class="tebal" style="margin-top: 10px;">Daftar Hadir Mahasiswa</p>
    <table class="grid">
        <thead>
            <tr>
                <th style="width: 24px;">No</th>
                <th style="width: 90px;">NIM</th>
                <th>Nama</th>
                <th style="width: 70px;">Status</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($presensi as $i => $p)
                <tr>
                    <td class="tengah">{{ $i + 1 }}</td>
                    <td>{{ $p->mahasiswa?->nim }}</td>
                    <td>{{ $p->mahasiswa?->user?->name }}</td>
                    <td class="tengah">{{ ucfirst($p->status) }}</td>
                    <td>{{ $p->keterangan }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="kosong">Tidak ada mahasiswa.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($pertemuan->terverifikasi())
        <p class="catatan">Diverifikasi oleh {{ $pertemuan->pemverifikasi?->name ?? '-' }} pada {{ $pertemuan->diverifikasi_at?->translatedFormat('d F Y, H.i') }}.</p>
    @else
        <p class="catatan merah">DRAF — presensi pertemuan ini belum diverifikasi.</p>
    @endif

    @include('pdf.partials.ttd-bap', ['dosen' => $pengajar])
@endsection
