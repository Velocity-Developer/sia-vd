@extends('pdf.layout')

@php($unitKop = $pendadaran->mahasiswa?->prodi?->unitKop())

@section('judul-berkas', 'Surat Pendadaran '.$pendadaran->mahasiswa?->nim)

@section('gaya')
    body { line-height: 1.5; }
    .identitas .label { width: 150px; }
    p { margin: 0 0 8px; text-align: justify; }
    p.judul, p.subjudul, .ttd-tunggal p { text-align: center; }
@endsection

@section('isi')
    <p class="judul">Surat Tugas &amp; Undangan Pendadaran</p>
    <p class="subjudul">Nomor: {{ $pendadaran->nomor_surat ?? '-' }}</p>

    <p>Dengan hormat, bersama ini kami menugaskan Bapak/Ibu dosen yang namanya tercantum di bawah sebagai penguji pada pendadaran (ujian akhir tugas akhir) mahasiswa berikut:</p>

    <table class="identitas" style="margin-bottom: 10px;">
        <tr><td class="label">Nama</td><td class="titik">:</td><td>{{ $pendadaran->mahasiswa?->user?->name }}</td></tr>
        <tr><td class="label">NIM</td><td class="titik">:</td><td>{{ $pendadaran->mahasiswa?->nim }}</td></tr>
        <tr><td class="label">Program Studi</td><td class="titik">:</td><td>{{ $pendadaran->mahasiswa?->prodi?->nama_prodi ?? '-' }}{{ $pendadaran->mahasiswa?->prodi?->jenjang ? ' ('.$pendadaran->mahasiswa->prodi->jenjang.')' : '' }}</td></tr>
        <tr><td class="label">Judul</td><td class="titik">:</td><td>{{ $pendadaran->tugasAkhir?->judul }}</td></tr>
        <tr><td class="label">Pembimbing</td><td class="titik">:</td><td>{{ implode(', ', $pendadaran->tugasAkhir?->namaPembimbing() ?? []) }}</td></tr>
    </table>

    <p>yang akan dilaksanakan pada:</p>
    <table class="identitas" style="margin-bottom: 12px;">
        <tr><td class="label">Hari, tanggal</td><td class="titik">:</td><td>{{ $pendadaran->tanggal->translatedFormat('l, d F Y') }}</td></tr>
        <tr><td class="label">Waktu</td><td class="titik">:</td><td>{{ substr($pendadaran->jam_mulai, 0, 5) }}–{{ substr($pendadaran->jam_akhir, 0, 5) }} WIB</td></tr>
        <tr><td class="label">Tempat</td><td class="titik">:</td><td>{{ trim(($pendadaran->ruang?->kode_ruang ?? '').' '.($pendadaran->ruang?->nama_ruang ?? '')) }}</td></tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 24px;">No</th>
                <th>Nama Penguji</th>
                <th style="width: 120px;">Jabatan</th>
                <th style="width: 110px;">Tanda Tangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pendadaran->daftarPenguji() as $i => $penguji)
                <tr>
                    <td class="tengah">{{ $i + 1 }}</td>
                    <td>{{ $penguji['nama'] }}</td>
                    <td>{{ $penguji['peran'] }}</td>
                    <td style="height: 30px;"></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top: 12px;">Mahasiswa diharap hadir 15 menit sebelum pendadaran dimulai dengan membawa naskah tugas akhir. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.</p>

    <div class="ttd-tunggal">
        <p>{{ $pendadaran->created_at?->translatedFormat('d F Y') }}</p>
        <p>Bagian Akademik</p>
        <div class="ruang"></div>
        <p>(..................................................)</p>
    </div>
@endsection
