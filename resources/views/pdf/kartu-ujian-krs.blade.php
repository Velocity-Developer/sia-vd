@extends('pdf.layout')

{{--
    Kartu UTS/UAS dari KRS yang disetujui, dicetak admin per mahasiswa (menu KRS → Cetak Kartu Ujian).
    Kolom Tanggal diisi dari jadwal ujian yang sudah terbit; kosong bila belum dijadwalkan.
--}}
@php
    $namaJenis = ['uts' => 'Ujian Tengah Semester', 'uas' => 'Ujian Akhir Semester'][$jenis];
    $singkat = strtoupper($jenis);
    $unitKop = $mahasiswa->prodi?->unitKop();
    $kaprodi = $mahasiswa->prodi?->ketuaProgramStudi;
@endphp

@section('judul-berkas', 'Kartu '.$singkat.' '.$mahasiswa->nim)

@section('isi')
    <p class="judul">Kartu {{ $namaJenis }}</p>
    <p class="subjudul">Tahun Akademik {{ $tahunAkademik->tahun }} Semester {{ $tahunAkademik->semester }}</p>

    <table class="identitas">
        <tr>
            <td class="label">Nama</td><td class="titik">:</td><td>{{ $mahasiswa->user?->name ?? '-' }}</td>
            <td class="label">Tahun Ajaran</td><td class="titik">:</td><td>{{ strtoupper($tahunAkademik->semester) }} {{ $tahunAkademik->tahun }}</td>
        </tr>
        <tr>
            <td class="label">NIM</td><td class="titik">:</td><td>{{ $mahasiswa->nim ?? '-' }}</td>
            <td class="label">Program Studi</td><td class="titik">:</td><td>{{ trim(($mahasiswa->prodi?->jenjang ?? '').' '.($mahasiswa->prodi?->nama_prodi ?? '-')) }}</td>
        </tr>
        <tr>
            <td class="label">Angkatan</td><td class="titik">:</td><td>{{ $mahasiswa->angkatan ?? '-' }}</td>
            <td class="label">Semester</td><td class="titik">:</td><td>{{ $mahasiswa->semesterPada($tahunAkademik) ?? '-' }}</td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 22px;">No</th>
                <th style="width: 70px;">Kode MK</th>
                <th>Mata Kuliah</th>
                <th style="width: 120px;">Dosen</th>
                <th style="width: 30px;">SKS</th>
                <th style="width: 46px;">Kelas</th>
                <th style="width: 62px;">Tanggal</th>
                <th style="width: 50px;">Paraf</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($krs as $i => $item)
                @php($kelas = $item->kelasKuliah)
                <tr>
                    <td class="tengah">{{ $i + 1 }}</td>
                    <td class="tengah">{{ $kelas?->mataKuliah?->kode_matkul ?? '-' }}</td>
                    <td>{{ $kelas?->mataKuliah?->nama_matkul ?? '-' }}</td>
                    <td>{{ $kelas?->dosen?->user?->name ?? '-' }}</td>
                    <td class="tengah">{{ $kelas?->mataKuliah?->sks ?? '-' }}</td>
                    <td class="tengah nowrap">{{ $kelas?->kode_kelas ?? '-' }}</td>
                    <td class="tengah nowrap">{{ $tanggalUjian[$item->kelas_id] ?? '' }}</td>
                    <td style="height: 22px;"></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="kanan">Total</td>
                <td class="tengah">{{ $krs->sum(fn ($item) => $item->kelasKuliah?->mataKuliah?->sks ?? 0) }}</td>
                <td colspan="3">SKS</td>
            </tr>
        </tfoot>
    </table>

    <div class="catatan">
        <p class="catatan-judul">Catatan:</p>
        <ol>
            <li>Kartu {{ $namaJenis }} ({{ $singkat }}) harus dibawa saat {{ $singkat }}.</li>
            <li>Terlambat lebih dari 40 menit tidak diperkenankan mengikuti {{ $singkat }}.</li>
            <li>Pelanggaran atau susulan {{ $singkat }} dikenakan sanksi potongan nilai {{ $singkat }} 15%.</li>
        </ol>
    </div>

    <div class="ttd-tunggal">
        <p>{{ now()->translatedFormat('d F Y') }}</p>
        <p>Sekretariat {{ $institusi->nama_pt }}</p>
        <div class="ruang"></div>
        @if ($kaprodi?->user?->name)
            <p class="tebal" style="text-decoration: underline; margin: 0;">{{ $kaprodi->user->name }}</p>
            <p>NIDN. {{ $kaprodi->nidn ?: '....................' }}</p>
        @else
            <p>(..................................................)</p>
        @endif
    </div>
@endsection
