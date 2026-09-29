@extends('pdf.layout')

@section('judul-berkas', 'Peserta Wisuda '.$periode->nama)

@section('gaya')
    body { line-height: 1.5; }
    .identitas .label { width: 150px; }
    p { margin: 0 0 8px; text-align: justify; }
    p.judul, p.subjudul, .ttd-tunggal p { text-align: center; }
@endsection

@section('isi')
    <p class="judul">Daftar Mahasiswa Wisuda</p>
    <p class="subjudul">{{ $periode->nama }} · {{ $periode->tanggal_acara->translatedFormat('l, d F Y') }}{{ $periode->tempat ? ' · '.$periode->tempat : '' }}</p>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 24px;">No</th>
                <th style="width: 80px;">NIM</th>
                <th>Nama</th>
                <th>Program Studi</th>
                <th style="width: 40px;">Toga</th>
                <th style="width: 40px;">IPK</th>
                <th style="width: 110px;">No. SKL</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($peserta as $i => $w)
                <tr>
                    <td class="tengah">{{ $i + 1 }}</td>
                    <td>{{ $w->mahasiswa?->nim }}</td>
                    <td>{{ $w->pengajuan?->isian['nama_ijazah'] ?? $w->mahasiswa?->user?->name }}</td>
                    <td>{{ $w->mahasiswa?->prodi ? $w->mahasiswa->prodi->jenjang.' '.$w->mahasiswa->prodi->nama_prodi : '-' }}</td>
                    <td class="tengah">{{ $w->pengajuan?->isian['ukuran_toga'] ?? '-' }}</td>
                    <td class="tengah">{{ $w->ipk !== null ? number_format($w->ipk, 2, ',', '.') : '-' }}</td>
                    <td>{{ $w->nomor_skl ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="tengah">Belum ada peserta.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="catatan">Jumlah peserta: {{ $peserta->count() }} orang.</p>
@endsection
