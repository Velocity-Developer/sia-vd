@extends('pdf.layout')

@php($unitKop = $mahasiswa->prodi?->unitKop())
@php($verifikasi = \App\Models\KrsSemester::verifikasiAktif())

@section('judul-berkas', 'KRS '.$mahasiswa->nim.' '.$tahunAkademik->tahun.' '.$tahunAkademik->semester)

@section('isi')
    <p class="judul">Kartu Rencana Studi</p>
    <p class="subjudul">Tahun Akademik {{ $tahunAkademik->tahun }} Semester {{ $tahunAkademik->semester }}</p>
    @if ($verifikasi && $kunci?->status !== \App\Models\KrsSemester::DISETUJUI)
        {{-- KRS yang belum final tetap bisa dicetak, tetapi jelas ditandai agar tidak dipakai sebagai KRS sah. --}}
        <p style="margin: 6px 0 10px; padding: 5px 8px; border: 1.5px solid #b91c1c; color: #b91c1c; text-align: center; font-weight: bold; letter-spacing: 1px;">
            BELUM DISETUJUI &mdash; KRS INI MASIH DAPAT BERUBAH
        </p>
    @endif

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
            <td class="label">IPS Sebelumnya</td><td class="titik">:</td>
            <td>{{ $ipsSebelumnya !== null ? number_format($ipsSebelumnya, 2, ',', '.') : '-' }} (maks. {{ $maksSks }} SKS)</td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th style="width: 22px;">No</th>
                <th style="width: 58px;">Kode</th>
                <th>Mata Kuliah</th>
                <th style="width: 50px;">Kelas</th>
                <th style="width: 30px;">SKS</th>
                <th style="width: 112px;">Jadwal</th>
                <th style="width: 118px;">Dosen Pengampu</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($krs as $i => $item)
                @php($kelas = $item->kelasKuliah)
                <tr>
                    <td class="tengah">{{ $i + 1 }}</td>
                    <td class="tengah">{{ $kelas?->mataKuliah?->kode_matkul ?? '-' }}</td>
                    <td>{{ $kelas?->mataKuliah?->nama_matkul ?? '-' }}@if ($kelas?->mataKuliah?->jenis)<br><span class="kecil">{{ $kelas->mataKuliah->jenis }}</span>@endif</td>
                    <td class="tengah nowrap">{{ $kelas?->kode_kelas ?? '-' }}</td>
                    <td class="tengah">{{ $kelas?->mataKuliah?->sks ?? '-' }}</td>
                    <td>
                        @forelse ($kelas?->jadwals ?? [] as $jadwal)
                            {{ $jadwal->hari }}, {{ substr($jadwal->jam_mulai, 0, 5) }}–{{ substr($jadwal->jam_akhir, 0, 5) }}@if ($jadwal->ruang?->kode_ruang) <span class="kecil">({{ $jadwal->ruang->kode_ruang }})</span>@endif<br>
                        @empty
                            -
                        @endforelse
                    </td>
                    <td>{{ $kelas?->dosen?->user?->name ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" class="kanan">Jumlah SKS Diambil</td>
                <td class="tengah">{{ $krs->sum(fn ($item) => $item->kelasKuliah?->mataKuliah?->sks ?? 0) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>

    <p class="catatan">
        Status KRS:
        <strong>{{ match ($kunci?->status) {
            \App\Models\KrsSemester::DISETUJUI => ($verifikasi ? 'Disetujui' : 'Disimpan dan dikunci').(($waktu = $kunci->diverifikasi_pada ?? $kunci->disimpan_pada) ? ' pada '.$waktu->translatedFormat('d F Y, H.i').' '.\App\Models\PengaturanInstitusi::singkatanZona() : ''),
            \App\Models\KrsSemester::DIAJUKAN => 'Diajukan, menunggu verifikasi (masih dapat berubah)',
            \App\Models\KrsSemester::PERLU_REVISI => 'Perlu revisi (masih dapat berubah)',
            default => 'Belum disimpan (masih dapat berubah)',
        } }}</strong>.
        KRS ini sah setelah ditandatangani Dosen Pembimbing Akademik dan diketahui Ketua Program Studi.
    </p>

    @include('pdf.partials.pengesahan-mahasiswa')
@endsection
