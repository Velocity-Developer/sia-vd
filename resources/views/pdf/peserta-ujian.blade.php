@extends('pdf.layout')

@php($namaJenis = ['uts' => 'Ujian Tengah Semester', 'uas' => 'Ujian Akhir Semester', 'remidi' => 'Ujian Remidi', 'uts_susulan' => 'Ujian Tengah Semester Susulan', 'uas_susulan' => 'Ujian Akhir Semester Susulan'][$jenis])

@section('judul-berkas', 'Daftar Hadir '.$namaJenis.' '.$kelas->kode_kelas)

@section('isi')
    <p class="judul">Daftar Hadir {{ $namaJenis }}</p>
    <p class="subjudul">Tahun Akademik {{ $kelas->tahunAkademik?->tahun }} Semester {{ $kelas->tahunAkademik?->semester }}</p>

    <table class="identitas">
        <tr>
            <td class="label">Mata Kuliah</td><td class="titik">:</td><td>{{ $kelas->mataKuliah?->kode_matkul }} – {{ $kelas->mataKuliah?->nama_matkul }}</td>
            <td class="label">Hari, Tanggal</td><td class="titik">:</td><td>{{ $jadwal->tanggal->translatedFormat('l, d F Y') }}</td>
        </tr>
        <tr>
            <td class="label">Kelas</td><td class="titik">:</td><td>{{ $kelas->kode_kelas }}</td>
            <td class="label">Waktu</td><td class="titik">:</td><td>{{ substr($jadwal->jam_mulai, 0, 5) }}–{{ substr($jadwal->jam_akhir, 0, 5) }} {{ \App\Models\PengaturanInstitusi::singkatanZona() }}</td>
        </tr>
        <tr>
            <td class="label">Dosen Pengampu</td><td class="titik">:</td><td>{{ $kelas->dosen?->user?->name ?? '-' }}</td>
            <td class="label">Ruang</td><td class="titik">:</td><td>{{ $jadwal->ruang?->kode_ruang ?? '-' }}</td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 24px;">No</th>
                <th style="width: 84px;">NIM</th>
                <th>Nama Mahasiswa</th>
                @if (in_array($jenis, ['uts', 'uas'], true))
                    <th style="width: 58px;">Kehadiran</th>
                @endif
                @if ($aktif)
                    <th style="width: 84px;">Syarat Ujian</th>
                @endif
                <th style="width: 110px;">Tanda Tangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($peserta as $i => $mhs)
                @php($syarat = $mhs['syarat'])
                <tr>
                    <td class="tengah">{{ $i + 1 }}</td>
                    <td class="tengah">{{ $mhs['nim'] }}</td>
                    <td>{{ $mhs['nama'] }}</td>
                    @if (in_array($jenis, ['uts', 'uas'], true))
                        <td class="tengah">{{ isset($syarat['persen']) ? $syarat['persen'].'%' : '-' }}</td>
                    @endif
                    @if ($aktif)
                        <td class="tengah {{ ($syarat['memenuhi'] ?? true) ? '' : 'merah' }}">
                            @if ($syarat['dispensasi'] ?? null)
                                Dispensasi
                            @elseif ($syarat['memenuhi'] ?? true)
                                Memenuhi
                            @else
                                Tidak memenuhi
                            @endif
                        </td>
                    @endif
                    <td style="height: 22px;"><span class="kecil">{{ $i + 1 }}.</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="catatan">
        @if ($jenis === 'remidi')
            Peserta remidi{{ ($keuangan ?? false) ? ' yang tagihan remidinya sudah lunas' : '' }}.
        @elseif (! in_array($jenis, ['uts', 'uas'], true))
            Peserta ujian susulan yang pengajuannya disetujui{{ ($keuangan ?? false) ? ' dan tagihannya lunas' : '' }}.
        @else
            Kehadiran dihitung dari pertemuan kuliah yang sudah selesai{{ $jenis === 'uts' ? ' sebelum UTS' : '' }}; izin dan sakit dihitung tidak hadir.
            @if ($aktif) Batas minimal {{ $min }}%. @endif
        @endif
    </p>

    <table class="ttd">
        <tr>
            <td style="width: 50%;">Pengawas Ujian,</td>
            <td style="width: 50%;">Dosen Pengampu,</td>
        </tr>
        <tr><td class="ruang"></td><td class="ruang"></td></tr>
        <tr>
            <td>(..................................................)</td>
            <td><span class="nama">{{ $kelas->dosen?->user?->name ?? '-' }}</span></td>
        </tr>
        <tr>
            <td></td>
            <td>NIDN. {{ $kelas->dosen?->nidn ?: '....................' }}</td>
        </tr>
    </table>
@endsection
