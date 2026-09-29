<?php

namespace App\Http\Controllers\Dosen;

use App\BerandaDosen;
use App\Http\Controllers\Controller;
use App\Models\TahunAkademik;
use App\PengingatRemidi;
use App\PengingatSusulan;
use App\PengingatTugasAkhir;
use App\PeringatanPresensi;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beranda dosen: profil & angka, presensi hari ini, pekerjaan yang perlu dinilai, pengingat (batas nilai, remidi,
 * ujian susulan, tugas akhir), dan kelas yang diampu. Setiap bagian hanya dikirim bila role pengguna punya izinnya.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $dosen = $user->dosenProfile;
        $tahunAkademik = TahunAkademik::aktif();

        if ($dosen === null) {
            return Inertia::render('Dosen/Dashboard', ['ringkasan' => null, 'tahunAkademik' => null]);
        }

        $bisa = fn (string $izin): bool => $user->hasPermission($izin);
        $kelas = $tahunAkademik && $bisa('dosen.kelas-kuliah');

        return Inertia::render('Dosen/Dashboard', [
            'ringkasan' => BerandaDosen::ringkasan($dosen, $tahunAkademik),
            'tahunAkademik' => $tahunAkademik?->label(),
            'presensiDosen' => $bisa('dosen.presensi') ? PeringatanPresensi::untukDosen($dosen) : null,
            'perluDinilai' => $kelas ? BerandaDosen::perluDinilai($dosen, $tahunAkademik) : null,
            'pengingatNilai' => $kelas ? BerandaDosen::pengingatNilai($dosen, $tahunAkademik) : null,
            'remidiDosen' => $bisa('dosen.kelas-kuliah') ? PengingatRemidi::untukDosen($dosen) : null,
            'susulanDosen' => $bisa('dosen.ujian') ? PengingatSusulan::untukDosen($dosen) : null,
            'pengingatTugasAkhir' => $bisa('dosen.bimbingan') ? PengingatTugasAkhir::untukDosen($dosen) : null,
            'kelas' => $kelas ? BerandaDosen::kelas($dosen, $tahunAkademik) : null,
        ]);
    }
}
