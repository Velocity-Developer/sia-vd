@extends('pdf.layout')

@php
    $namaJenis = ['uts' => 'Ujian Tengah Semester', 'uas' => 'Ujian Akhir Semester', 'remidi' => 'Ujian Remidi', 'uts_susulan' => 'Ujian Tengah Semester Susulan', 'uas_susulan' => 'Ujian Akhir Semester Susulan'][$jenis];
    $unitKop = $mahasiswa->prodi?->unitKop();
@endphp

@section('judul-berkas', 'Kartu '.$namaJenis.' '.$mahasiswa->nim)

@section('isi')
    <p class="judul">Kartu {{ $namaJenis }}</p>
    <p class="subjudul">Tahun Akademik {{ $tahun->tahun }} Semester {{ $tahun->semester }}</p>

    <table class="identitas">
        <tr>
            <td class="label">Nama Mahasiswa</td><td class="titik">:</td><td>{{ $mahasiswa->user?->name }}</td>
            <td class="label">Program Studi</td><td class="titik">:</td><td>{{ trim(($mahasiswa->prodi?->jenjang ?? '').' '.($mahasiswa->prodi?->nama_prodi ?? '-')) }}</td>
        </tr>
        <tr>
            <td class="label">NIM</td><td class="titik">:</td><td>{{ $mahasiswa->nim }}</td>
            <td class="label">Semester</td><td class="titik">:</td><td>{{ $mahasiswa->semesterPada($tahun) ?? '-' }}</td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 22px;">No</th>
                <th>Mata Kuliah</th>
                <th style="width: 50px;">Kelas</th>
                <th style="width: 104px;">Hari, Tanggal</th>
                <th style="width: 62px;">Waktu</th>
                <th style="width: 82px;">Ruang / Mode</th>
                @if ($syaratAktif)
                    <th style="width: 62px;">Syarat Hadir</th>
                @endif
                <th style="width: 62px;">Paraf Pengawas</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($ujians as $i => $u)
                <tr>
                    <td class="tengah">{{ $i + 1 }}</td>
                    <td>{{ $u['nama_matkul'] }}<br><span class="kecil">{{ $u['kode_matkul'] }} · {{ $u['sks'] }} SKS</span></td>
                    <td class="tengah nowrap">{{ $u['kode_kelas'] }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($u['tanggal'])->translatedFormat('l, d M Y') }}</td>
                    <td class="tengah">{{ substr($u['jam_mulai'], 0, 5) }}–{{ substr($u['jam_akhir'], 0, 5) }}</td>
                    <td class="tengah">{{ $u['mode'] === 'tatap_muka' ? ($u['ruang'] ?? '-') : $u['label_mode'] }}</td>
                    @if ($syaratAktif)
                        @php($s = $u['syarat'])
                        <td class="tengah {{ ($s['memenuhi'] ?? true) ? '' : 'merah' }}">
                            {{ ($s['dispensasi'] ?? null) ? 'Dispensasi' : (($s['memenuhi'] ?? true) ? 'Memenuhi' : 'Tidak memenuhi') }}
                        </td>
                    @endif
                    <td style="height: 26px;"></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="catatan">
        <p class="catatan-judul">Ketentuan:</p>
        <ol>
            <li>Kartu ini wajib dibawa dan ditunjukkan kepada pengawas pada setiap ujian tatap muka.</li>
            <li>Ujian online dikerjakan melalui menu Jadwal Ujian di SIA sesuai waktu yang tertera.</li>
            @if ($syaratAktif)
                <li>Mahasiswa yang tidak memenuhi syarat kehadiran tidak dapat mengikuti ujian kecuali mendapat dispensasi.</li>
            @endif
        </ol>
    </div>

    @include('pdf.partials.pengesahan-mahasiswa')
@endsection
