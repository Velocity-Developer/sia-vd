@extends('pdf.layout')

@php($unitKop = $mahasiswa->prodi?->unitKop())

@section('judul-berkas', 'Kartu Hasil Studi '.$mahasiswa->nim)

@section('isi')
    <p class="judul">Kartu Hasil Studi</p>
    <p class="subjudul">Tahun Akademik {{ $tahunAkademik?->tahun ?? '-' }}@if ($tahunAkademik?->semester) Semester {{ $tahunAkademik->semester }}@endif</p>

    <table class="identitas">
        <tr>
            <td class="label">Nama Mahasiswa</td><td class="titik">:</td><td>{{ $mahasiswa->user?->name ?? '-' }}</td>
            <td class="label">Program Studi</td><td class="titik">:</td><td>{{ trim(($mahasiswa->prodi?->jenjang ?? '').' '.($mahasiswa->prodi?->nama_prodi ?? '-')) }}</td>
        </tr>
        <tr>
            <td class="label">NIM</td><td class="titik">:</td><td>{{ $mahasiswa->nim ?? '-' }}</td>
            <td class="label">Semester</td><td class="titik">:</td><td>{{ $mahasiswa->semesterPada($tahunAkademik) ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Angkatan</td><td class="titik">:</td><td>{{ $mahasiswa->angkatan ?? '-' }}</td>
            <td class="label"></td><td class="titik"></td><td></td>
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
            @forelse ($krs as $index => $item)
                @php($bobot = \App\Models\SkalaNilai::bobot($item['nilai']))
                <tr>
                    <td class="tengah">{{ $index + 1 }}</td>
                    <td class="tengah">{{ $item['kode'] ?? '-' }}</td>
                    <td>{{ $item['nama'] ?? '-' }}</td>
                    <td class="tengah">{{ $item['sks'] ?? '-' }}</td>
                    <td class="tengah tebal">{{ $item['nilai'] ? strtoupper($item['nilai']) : '-' }}</td>
                    <td class="tengah">{{ $bobot !== null ? number_format($bobot, 2, ',', '.') : '-' }}</td>
                    <td class="tengah">{{ $bobot !== null ? number_format($bobot * ($item['sks'] ?? 0), 2, ',', '.') : '-' }}</td>
                </tr>
            @empty
                <tr><td class="kosong" colspan="7">Belum ada hasil studi pada tahun akademik ini.</td></tr>
            @endforelse
        </tbody>
        @if ($krs->isNotEmpty())
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
        <tr><td class="label">Jumlah SKS Diambil</td><td>: {{ $ringkasan['totalSks'] }}</td></tr>
        <tr><td class="label">Jumlah SKS Dinilai</td><td>: {{ $ringkasan['totalSksDinilai'] }}</td></tr>
        <tr><td class="label tebal">Indeks Prestasi Semester</td><td class="tebal">: {{ $ringkasan['ip'] !== null ? number_format($ringkasan['ip'], 2, ',', '.') : '-' }}</td></tr>
    </table>

    @include('pdf.partials.pengesahan-mahasiswa')
@endsection
