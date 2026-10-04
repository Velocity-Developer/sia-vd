@extends('pdf.layout')

{{-- Kartu Studi Tetap: KRS yang sudah disetujui, dicetak admin per mahasiswa (menu KRS → Cetak KST). --}}
@php($unitKop = $mahasiswa->prodi?->unitKop())

@section('judul-berkas', 'KST '.$mahasiswa->nim.' '.$tahunAkademik->label())

@section('isi')
    <p class="judul">Kartu Studi Tetap</p>
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
                <th style="width: 130px;">Dosen</th>
                <th style="width: 30px;">SKS</th>
                <th style="width: 50px;">Kelas</th>
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
                    <td style="height: 22px;"></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="kanan">Total</td>
                <td class="tengah">{{ $krs->sum(fn ($item) => $item->kelasKuliah?->mataKuliah?->sks ?? 0) }}</td>
                <td colspan="2">SKS</td>
            </tr>
        </tfoot>
    </table>

    @include('pdf.partials.pengesahan-mahasiswa')
@endsection
