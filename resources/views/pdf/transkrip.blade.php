@extends('pdf.layout')

@php($unitKop = $mahasiswa->prodi?->unitKop())

@section('judul-berkas', 'Transkrip Nilai '.$mahasiswa->nim)

@section('isi')
    <p class="judul">Transkrip Nilai Akademik</p>
    <p class="subjudul">Per {{ now()->translatedFormat('d F Y') }}</p>

    <table class="identitas">
        <tr>
            <td class="label">Nama Mahasiswa</td><td class="titik">:</td><td>{{ $mahasiswa->user?->name ?? '-' }}</td>
            <td class="label">Program Studi</td><td class="titik">:</td><td>{{ trim(($mahasiswa->prodi?->jenjang ?? '').' '.($mahasiswa->prodi?->nama_prodi ?? '-')) }}</td>
        </tr>
        <tr>
            <td class="label">NIM</td><td class="titik">:</td><td>{{ $mahasiswa->nim ?? '-' }}</td>
            <td class="label">Fakultas</td><td class="titik">:</td><td>{{ $mahasiswa->prodi?->fakultas?->nama_fakultas ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Tempat, Tgl. Lahir</td><td class="titik">:</td>
            <td>{{ $mahasiswa->tempat_lahir ?: '-' }}, {{ $mahasiswa->tanggal_lahir?->translatedFormat('d F Y') ?? '-' }}</td>
            <td class="label">Angkatan</td><td class="titik">:</td><td>{{ $mahasiswa->angkatan ?? '-' }}</td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 24px;">No</th>
                <th style="width: 64px;">Kode</th>
                <th>Mata Kuliah</th>
                <th style="width: 34px;">SKS</th>
                <th style="width: 40px;">Nilai</th>
                <th style="width: 40px;">Bobot</th>
                <th style="width: 44px;">Mutu</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($transkrip as $i => $item)
                <tr>
                    <td class="tengah">{{ $i + 1 }}</td>
                    <td class="tengah">{{ $item['kode'] }}</td>
                    <td>{{ $item['nama'] }}@if ($item['diambil'] > 1) <span class="kecil">(diulang)</span>@endif</td>
                    <td class="tengah">{{ $item['sks'] }}</td>
                    <td class="tengah tebal">{{ $item['nilai'] }}</td>
                    <td class="tengah">{{ number_format((float) $item['bobot'], 2, ',', '.') }}</td>
                    <td class="tengah">{{ number_format((float) $item['mutu'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td class="kosong" colspan="7">Belum ada mata kuliah yang dinilai.</td></tr>
            @endforelse
        </tbody>
        @if ($transkrip->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="3" class="kanan">Jumlah</td>
                    <td class="tengah">{{ $ringkasan['totalSks'] }}</td>
                    <td></td>
                    <td></td>
                    <td class="tengah">{{ number_format((float) $ringkasan['totalMutu'], 2, ',', '.') }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <table class="ringkasan">
        <tr><td class="label">Jumlah Mata Kuliah</td><td>: {{ $ringkasan['totalMatkul'] }}</td></tr>
        <tr><td class="label">Jumlah SKS Ditempuh</td><td>: {{ $ringkasan['totalSks'] }}</td></tr>
        <tr><td class="label">Jumlah SKS Lulus</td><td>: {{ $ringkasan['totalSksLulus'] }}</td></tr>
        <tr><td class="label tebal">Indeks Prestasi Kumulatif</td><td class="tebal">: {{ $ringkasan['ipk'] !== null ? number_format($ringkasan['ipk'], 2, ',', '.') : '-' }}</td></tr>
        @if ($kelulusan['judul_ta'] ?? null)
            <tr><td class="label">Judul Tugas Akhir</td><td>: {{ $kelulusan['judul_ta'] }}</td></tr>
        @endif
        @if ($kelulusan['tanggal_lulus'] ?? null)
            <tr><td class="label">Tanggal Lulus</td><td>: {{ \Illuminate\Support\Carbon::parse($kelulusan['tanggal_lulus'])->translatedFormat('d F Y') }}</td></tr>
        @endif
    </table>

    <p class="catatan">Mata kuliah yang diambil lebih dari sekali dihitung satu kali dengan nilai terbaik. Mutu = SKS × Bobot; IPK = Jumlah Mutu ÷ Jumlah SKS.</p>

    @include('pdf.partials.pengesahan-mahasiswa')
@endsection
