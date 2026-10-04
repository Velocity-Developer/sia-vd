<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\NilaiMahasiswa;
use App\Http\Controllers\Controller;
use App\Models\Krs;
use App\Models\MahasiswaProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pendataan Nilai Akhir (alur Yapika: Nilai Semester → Detail Nilai → Pendataan Nilai Akhir → Validasi Nilai): rekap hasil
 * konversi nilai akhir angka menjadi huruf sesuai Bobot Nilai, per mata kuliah yang pernah diambil mahasiswa (semua tahun
 * akademik), beserta status validasinya. Hanya dilihat; angka diubah lewat Nilai Semester/halaman kelas (atau Nilai KKM),
 * lalu nilainya disahkan di Validasi Nilai.
 */
class PendataanNilaiController extends Controller
{
    use NilaiMahasiswa;

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/PendataanNilai', $this->daftarSemuaMahasiswaNilai($request));
    }

    public function show(MahasiswaProfile $mahasiswa): Response
    {
        $mahasiswa->load(['user:id,name', 'prodi:id,nama_prodi,jenjang']);

        $krs = $mahasiswa->krs()
            ->with([
                'kelasKuliah:id,matkul_id,kode_kelas,tahun_akademik_id,remidi_dikunci_at',
                'kelasKuliah.mataKuliah:id,prodi_id,kode_matkul,nama_matkul,sks,tugas_akhir,jenis_penilaian',
                'kelasKuliah.tahunAkademik',
                'kelasKuliah.remidiPesertas:id,kelas_id,mahasiswa_id',
            ])
            ->get()
            ->sortBy(fn (Krs $k): string => ($k->kelasKuliah?->tahunAkademik?->tanggal_mulai?->format('Y-m-d') ?? '').'|'.$k->kelasKuliah?->mataKuliah?->kode_matkul)
            ->values();

        return Inertia::render('Admin/PendataanNilaiMahasiswa', [
            'mahasiswa' => $this->identitasMahasiswa($mahasiswa),
            'nilai' => $krs->map(fn (Krs $k): array => [
                'krs_id' => $k->id,
                'tahun_akademik_id' => $k->kelasKuliah?->tahun_akademik_id,
                'tahun_akademik' => $k->kelasKuliah?->tahunAkademik?->label(),
                'kode_matkul' => $k->kelasKuliah?->mataKuliah?->kode_matkul,
                'nama_matkul' => $k->kelasKuliah?->mataKuliah?->nama_matkul,
                'sks' => (int) ($k->kelasKuliah?->mataKuliah?->sks ?? 0),
                'nilai_angka' => $k->nilai_angka,
                'huruf' => $k->nilai,
                'bobot' => filled($k->nilai) ? $k->bobotNilai() : null,
                'sumber' => $this->sumber($k),
                'status' => match (true) {
                    blank($k->nilai) => 'belum',
                    $k->nilaiTervalidasi() => 'tervalidasi',
                    default => 'menunggu',
                },
            ]),
        ]);
    }

    /**
     * Asal huruf akhir: konversi angka komponen, hasil remidi, menu Nilai KKM, atau huruf lama tanpa angka.
     */
    private function sumber(Krs $k): ?string
    {
        if (blank($k->nilai)) {
            return null;
        }

        return match (true) {
            $k->kelasKuliah?->remidi_dikunci_at !== null && $k->kelasKuliah->remidiPesertas->contains('mahasiswa_id', $k->mahasiswa_id) => 'Remidi',
            (bool) $k->kelasKuliah?->mataKuliah?->kkm() => 'Nilai KKM',
            $k->nilai_angka !== null => 'Konversi angka',
            default => 'Huruf tanpa angka',
        };
    }
}
