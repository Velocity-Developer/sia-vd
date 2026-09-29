@extends('pdf.layout')

@php($unitKop = $wisuda->mahasiswa?->prodi?->unitKop())

@section('judul-berkas', 'SKL '.$wisuda->mahasiswa?->nim)

@section('gaya')
    body { line-height: 1.5; }
    .identitas .label { width: 150px; }
    p { margin: 0 0 8px; text-align: justify; }
    p.judul, p.subjudul, .ttd-tunggal p { text-align: center; }
@endsection

@section('isi')
    <p class="judul">Surat Keterangan Lulus</p>
    <p class="subjudul">Nomor: {{ $wisuda->nomor_skl }}</p>

    <p>Yang bertanda tangan di bawah ini menerangkan bahwa:</p>

    <table class="identitas" style="margin-bottom: 10px;">
        <tr><td class="label">Nama</td><td class="titik">:</td><td>{{ $isian['nama_ijazah'] ?? $wisuda->mahasiswa?->user?->name }}</td></tr>
        <tr><td class="label">NIM</td><td class="titik">:</td><td>{{ $wisuda->mahasiswa?->nim }}</td></tr>
        <tr><td class="label">Tempat, tanggal lahir</td><td class="titik">:</td><td>{{ $isian['tempat_lahir'] ?? '-' }}, {{ isset($isian['tanggal_lahir']) ? \Illuminate\Support\Carbon::parse($isian['tanggal_lahir'])->translatedFormat('d F Y') : '-' }}</td></tr>
        <tr><td class="label">Program Studi</td><td class="titik">:</td><td>{{ $wisuda->mahasiswa?->prodi?->nama_prodi ?? '-' }}{{ $wisuda->mahasiswa?->prodi?->jenjang ? ' ('.$wisuda->mahasiswa->prodi->jenjang.')' : '' }}</td></tr>
        @if ($wisuda->mahasiswa?->prodi?->fakultas)
            <tr><td class="label">Fakultas</td><td class="titik">:</td><td>{{ $wisuda->mahasiswa->prodi->fakultas->nama_fakultas }}</td></tr>
        @endif
    </table>

    <p>telah menyelesaikan seluruh kewajiban akademik dan dinyatakan <strong>LULUS</strong> dengan keterangan:</p>

    <table class="identitas" style="margin-bottom: 12px;">
        <tr><td class="label">Tanggal lulus</td><td class="titik">:</td><td>{{ $wisuda->tanggal_lulus?->translatedFormat('d F Y') }}</td></tr>
        <tr><td class="label">Judul tugas akhir</td><td class="titik">:</td><td>{{ $wisuda->tugasAkhir?->judul }}</td></tr>
        <tr><td class="label">Jumlah SKS</td><td class="titik">:</td><td>{{ $wisuda->total_sks }}</td></tr>
        <tr><td class="label">IPK</td><td class="titik">:</td><td>{{ number_format((float) $wisuda->ipk, 2, ',', '.') }}</td></tr>
        <tr><td class="label">Predikat</td><td class="titik">:</td><td>{{ $wisuda->predikat }}</td></tr>
    </table>

    <p>Surat keterangan ini berlaku sebagai pengganti ijazah sampai ijazah diterbitkan, dan dibuat untuk dipergunakan sebagaimana mestinya.</p>

    <div class="ttd-tunggal">
        <p>{{ $wisuda->skl_terbit_at?->translatedFormat('d F Y') }}</p>
        <p>Bagian Akademik</p>
        <div class="ruang"></div>
        <p>(..................................................)</p>
    </div>
@endsection
