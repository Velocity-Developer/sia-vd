<?php

namespace App\Http\Controllers\Mahasiswa;

use App\BerandaMahasiswa;
use App\Http\Controllers\Controller;
use App\Models\TahunAkademik;
use App\PengingatCuti;
use App\PengingatJadwalPertemuan;
use App\PengingatRemidi;
use App\PengingatSusulan;
use App\PengingatTugasAkhir;
use App\PeringatanPresensi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beranda mahasiswa: ringkasan studi, pengingat yang perlu ditindaklanjuti, kuliah hari ini, dan tugas terdekat.
 * Setiap bagian hanya dikirim bila role pengguna punya izin halaman tujuannya.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $mahasiswa = $user->mahasiswaProfile;
        $tahunAkademik = TahunAkademik::aktif();

        if ($mahasiswa === null) {
            return Inertia::render('Mahasiswa/Dashboard', ['ringkasan' => null, 'tahunAkademik' => null, 'pengingat' => null]);
        }

        $bisa = fn (string $izin): bool => $user->hasPermission($izin);

        // Judul dan urutan tampil tiap kelompok diatur di halaman.
        $pengingat = [
            'semester' => BerandaMahasiswa::pengingatSemester($mahasiswa, $tahunAkademik, $bisa('mahasiswa.krs'), $bisa('mahasiswa.info-biaya')),
            'cuti' => $bisa('mahasiswa.pengajuan-cuti') ? PengingatCuti::untukMahasiswa($mahasiswa) : null,
            'jadwal' => $bisa('mahasiswa.presensi') ? PengingatJadwalPertemuan::untukMahasiswa($mahasiswa) : null,
            'tugasAkhir' => $bisa('mahasiswa.tugas-akhir') ? PengingatTugasAkhir::untukMahasiswa($mahasiswa) : null,
        ];

        return Inertia::render('Mahasiswa/Dashboard', [
            'ringkasan' => BerandaMahasiswa::ringkasan($mahasiswa, $tahunAkademik),
            'tahunAkademik' => $tahunAkademik?->label(),
            'pengingat' => $pengingat,
            'peringatanPresensi' => $bisa('mahasiswa.presensi') ? PeringatanPresensi::untukMahasiswa($mahasiswa) : null,
            'remidiMahasiswa' => PengingatRemidi::untukMahasiswa($mahasiswa, $bisa('mahasiswa.info-biaya'), $bisa('mahasiswa.ujian')),
            'susulanMahasiswa' => PengingatSusulan::untukMahasiswa($mahasiswa, $bisa('mahasiswa.info-biaya'), $bisa('mahasiswa.ujian')),
            'kuliahHariIni' => $tahunAkademik && $bisa('mahasiswa.jadwal-kuliah') ? BerandaMahasiswa::kuliahHariIni($mahasiswa, $tahunAkademik) : null,
            'tugasMendatang' => $tahunAkademik && $bisa('mahasiswa.jadwal-kuliah') ? BerandaMahasiswa::tugasMendatang($mahasiswa, $tahunAkademik) : null,
        ]);
    }
}
