<?php

namespace App;

use App\Models\Krs;
use App\Models\KrsSemester;
use App\Models\MahasiswaProfile;
use App\Models\PengaturanInstitusi;
use App\Models\TahunAkademik;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * PDF Kartu Studi Tetap (KRS yang sudah disetujui), dicetak admin (menu KRS → Cetak KST) maupun mahasiswa sendiri.
 */
class KartuStudiTetap
{
    /**
     * Alasan KST belum bisa dicetak, atau null bila bisa.
     */
    public static function alasanTidakBisa(MahasiswaProfile $mahasiswa, TahunAkademik $tahun): ?string
    {
        if (! KrsSemester::disetujui($mahasiswa->id, $tahun->id)) {
            return 'KRS belum disetujui pada tahun akademik ini.';
        }

        return self::kelas($mahasiswa, $tahun)->isEmpty() ? 'KRS belum berisi kelas.' : null;
    }

    public static function pdf(MahasiswaProfile $mahasiswa, TahunAkademik $tahun): Response
    {
        $mahasiswa->muatPengesahan();
        $institusi = PengaturanInstitusi::current();

        return Pdf::loadView('pdf.kst', [
            'institusi' => $institusi,
            'logoSrc' => $institusi->logoDataUri(),
            'kontak' => $institusi->kontakKop(),
            'mahasiswa' => $mahasiswa,
            'tahunAkademik' => $tahun,
            'krs' => self::kelas($mahasiswa, $tahun),
        ])->stream('kst-'.$mahasiswa->nim.'-'.Str::slug($tahun->label()).'.pdf');
    }

    /**
     * @return Collection<int, Krs>
     */
    private static function kelas(MahasiswaProfile $mahasiswa, TahunAkademik $tahun): Collection
    {
        return $mahasiswa->krs()
            ->whereHas('kelasKuliah', fn ($q) => $q->where('tahun_akademik_id', $tahun->id))
            ->with([
                'kelasKuliah:id,matkul_id,dosen_id,kode_kelas',
                'kelasKuliah.mataKuliah:id,kode_matkul,nama_matkul,sks',
                'kelasKuliah.dosen:id,user_id',
                'kelasKuliah.dosen.user:id,name',
            ])
            ->get()
            ->sortBy(fn (Krs $k): string => (string) $k->kelasKuliah?->mataKuliah?->kode_matkul)
            ->values();
    }
}
