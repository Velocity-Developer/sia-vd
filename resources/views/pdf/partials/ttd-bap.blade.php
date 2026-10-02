{{--
    Tanda tangan BAP: dosen pengajar, Ketua Program Studi (mengetahui), dan petugas akademik (pemverifikasi).
    Variabel: $dosen (DosenProfile|null, relasi user), $kelas (relasi mataKuliah.prodi.ketuaProgramStudi.user), $petugas (?string), $tanggal (Carbon).
--}}
@php
    $kaprodi = $kelas->mataKuliah?->prodi?->ketuaProgramStudi;
    $titik = '(..................................................)';
@endphp
<table class="ttd">
    <tr>
        <td style="width: 33.3%;">Mengetahui,</td>
        <td style="width: 33.3%;">Diverifikasi,</td>
        <td style="width: 33.4%;">{{ $tanggal->translatedFormat('d F Y') }}</td>
    </tr>
    <tr>
        <td>Ketua Program Studi</td>
        <td>Petugas Akademik</td>
        <td>Dosen</td>
    </tr>
    <tr>
        <td class="ruang"></td>
        <td class="ruang"></td>
        <td class="ruang"></td>
    </tr>
    <tr>
        <td>{!! $kaprodi?->user?->name ? '<span class="nama">'.e($kaprodi->user->name).'</span>' : $titik !!}</td>
        <td>{!! $petugas ? '<span class="nama">'.e($petugas).'</span>' : $titik !!}</td>
        <td>{!! $dosen?->user?->name ? '<span class="nama">'.e($dosen->user->name).'</span>' : $titik !!}</td>
    </tr>
    <tr>
        <td>NIDN. {{ $kaprodi?->nidn ?: '....................' }}</td>
        <td></td>
        <td>NIDN. {{ $dosen?->nidn ?: '....................' }}</td>
    </tr>
</table>
