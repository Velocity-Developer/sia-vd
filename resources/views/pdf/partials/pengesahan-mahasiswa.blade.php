{{--
    Blok tanda tangan dokumen mahasiswa: Dosen Pembimbing Akademik, Ketua Program Studi, dan mahasiswa.
    $mahasiswa perlu relasi user, dosenWali.user, prodi.ketuaProgramStudi.user (lihat MahasiswaProfile::muatPengesahan).
--}}
@php
    $dosenPa = $mahasiswa->dosenWali;
    $kaprodi = $mahasiswa->prodi?->ketuaProgramStudi;
    $titik = '(..................................................)';
@endphp
<table class="ttd">
    <tr>
        <td style="width: 33.3%;">Menyetujui,</td>
        <td style="width: 33.3%;">Mengetahui,</td>
        <td style="width: 33.4%;">{{ ($tanggal ?? now())->translatedFormat('d F Y') }}</td>
    </tr>
    <tr>
        <td>Dosen Pembimbing Akademik</td>
        <td>Ketua Program Studi</td>
        <td>Mahasiswa</td>
    </tr>
    <tr>
        <td class="ruang"></td>
        <td class="ruang"></td>
        <td class="ruang"></td>
    </tr>
    <tr>
        <td>{!! $dosenPa?->user?->name ? '<span class="nama">'.e($dosenPa->user->name).'</span>' : $titik !!}</td>
        <td>{!! $kaprodi?->user?->name ? '<span class="nama">'.e($kaprodi->user->name).'</span>' : $titik !!}</td>
        <td><span class="nama">{{ $mahasiswa->user?->name ?? '-' }}</span></td>
    </tr>
    <tr>
        <td>NIDN. {{ $dosenPa?->nidn ?: '....................' }}</td>
        <td>NIDN. {{ $kaprodi?->nidn ?: '....................' }}</td>
        <td>NIM. {{ $mahasiswa->nim ?? '-' }}</td>
    </tr>
</table>
