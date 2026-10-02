@extends('pdf.layout')

@section('judul-berkas', 'BAP '.$kelas->kode_kelas)

@section('isi')
    @include('pdf.partials.draf')

    <p class="judul">Berita Acara Perkuliahan</p>
    <p class="subjudul">Tahun Akademik {{ $kelas->tahunAkademik?->tahun }} Semester {{ $kelas->tahunAkademik?->semester }}</p>

    <table class="identitas">
        <tr>
            <td class="label">Mata Kuliah</td><td class="titik">:</td><td>{{ $kelas->mataKuliah?->kode_matkul }} – {{ $kelas->mataKuliah?->nama_matkul }} ({{ $kelas->mataKuliah?->sks }} SKS)</td>
        </tr>
        <tr><td class="label">Program Studi</td><td class="titik">:</td><td>{{ trim(($kelas->mataKuliah?->prodi?->jenjang ?? '').' '.($kelas->mataKuliah?->prodi?->nama_prodi ?? '-')) }}</td></tr>
        <tr><td class="label">Kelas</td><td class="titik">:</td><td>{{ $kelas->kode_kelas }} · rencana {{ $kelas->jumlah_pertemuan }} pertemuan</td></tr>
        <tr><td class="label">Dosen Pengampu</td><td class="titik">:</td><td>{{ $kelas->dosen?->user?->name ?? '-' }} (NIDN {{ $kelas->dosen?->nidn ?? '-' }})</td></tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 24px;">Ke</th>
                <th style="width: 62px;">Tanggal</th>
                <th style="width: 62px;">Masuk–Keluar</th>
                <th style="width: 100px;">Dosen</th>
                <th>Topik / Realisasi Materi</th>
                <th style="width: 44px;">Hadir</th>
                <th style="width: 50px;">Paraf</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pertemuan as $p)
                <tr>
                    <td class="tengah">{{ $p->pertemuan_ke }}{{ $p->jenis !== 'kuliah' ? ' '.strtoupper($p->jenis) : '' }}</td>
                    <td class="tengah nowrap">{{ $p->tanggal->format('d/m/Y') }}</td>
                    <td class="tengah nowrap">{{ $p->dosen_masuk_at?->format('H.i') ?? '-' }}–{{ $p->dosen_keluar_at?->format('H.i') ?? '-' }}</td>
                    <td>{{ $p->dosen?->user?->name ?? $kelas->dosen?->user?->name }}@if ($p->statusDosen($kelas->dosen_id) === 'digantikan')<br><span class="kecil">(pengganti)</span>@endif</td>
                    <td style="white-space: pre-line;">{{ $p->topik ?: '-' }}@unless ($p->terverifikasi())<br><span class="kecil merah">belum diverifikasi</span>@endunless</td>
                    <td class="tengah">{{ $p->jumlah_hadir }}/{{ $p->jumlah_peserta }}</td>
                    <td></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="catatan">Memuat {{ $pertemuan->count() }} pertemuan yang sudah selesai{{ $draf ? '' : ' dan diverifikasi' }}. Hadir = hadir + terlambat dibanding jumlah mahasiswa.</p>

    @include('pdf.partials.ttd-bap', ['dosen' => $kelas->dosen])
@endsection
