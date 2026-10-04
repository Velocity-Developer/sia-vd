<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\NilaiMahasiswa;
use App\Http\Controllers\Controller;
use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\TahunAkademik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Validasi Nilai: daftar mahasiswa yang punya KRS di satu tahun akademik, lalu nilai akhir & huruf per mata kuliah
 * dengan kolom validasi. Validasi bisa per mata kuliah atau sekaligus; nilai tervalidasi terkunci untuk dosen dan admin
 * sampai validasinya dibatalkan (atau dibuka lewat remidi). Nilai kelas berdosen pengampu baru bisa divalidasi setelah
 * dosen mengirimnya ke validasi (atau batas input nilai lewat); koreksi = admin mengembalikan nilai kelas ke dosen.
 */
class ValidasiNilaiController extends Controller
{
    use NilaiMahasiswa;

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/ValidasiNilai', $this->daftarMahasiswaNilai($request));
    }

    public function show(Request $request, MahasiswaProfile $mahasiswa): Response
    {
        [$tahun, $tahunAkademiks] = $this->tahunKrs($request);
        $mahasiswa->load(['user:id,name', 'prodi:id,nama_prodi,jenjang']);

        return Inertia::render('Admin/ValidasiNilaiMahasiswa', [
            'mahasiswa' => $this->identitasMahasiswa($mahasiswa),
            'tahunAkademikId' => $tahun?->id,
            'tahunAkademikOptions' => $tahunAkademiks->map(fn (TahunAkademik $t): array => ['id' => $t->id, 'name' => $t->label().($t->status ? ' (aktif)' : '')]),
            'nilai' => $this->krsTahun($mahasiswa, $tahun)->map(fn (Krs $k): array => [
                'krs_id' => $k->id,
                'kode_matkul' => $k->kelasKuliah->mataKuliah?->kode_matkul,
                'nama_matkul' => $k->kelasKuliah->mataKuliah?->nama_matkul,
                'sks' => (int) ($k->kelasKuliah->mataKuliah?->sks ?? 0),
                'kode_kelas' => $k->kelasKuliah->kode_kelas,
                'kelas_id' => $k->kelasKuliah->id,
                'menunggu_dosen' => self::menungguDosen($k),
                'nilai_angka' => $k->nilai_angka,
                'huruf' => $k->nilai,
                'divalidasi_at' => $k->nilai_divalidasi_at?->toIso8601String(),
                'divalidasi_oleh' => $k->validator?->name,
            ]),
        ]);
    }

    /**
     * Validasi nilai yang sudah berhuruf dan belum divalidasi: semua mata kuliah di tahun itu, atau hanya `krs_id` bila dikirim.
     */
    public function validasi(Request $request, MahasiswaProfile $mahasiswa): RedirectResponse
    {
        $krs = $this->krsDipilih($request, $mahasiswa);
        $belumDivalidasi = $krs->filter(fn (Krs $k): bool => filled($k->nilai) && ! $k->nilaiTervalidasi());
        $menungguDosen = $belumDivalidasi->filter(fn (Krs $k): bool => self::menungguDosen($k))->count();
        $siap = $belumDivalidasi->reject(fn (Krs $k): bool => self::menungguDosen($k));
        $kosong = $krs->filter(fn (Krs $k): bool => blank($k->nilai))->count();
        $catatan = ($menungguDosen > 0 ? " {$menungguDosen} nilai belum dikirim dosen ke validasi." : '')
            .($kosong > 0 ? " {$kosong} mata kuliah belum bernilai sehingga belum divalidasi." : '');

        if ($siap->isEmpty()) {
            return back()->with('error', match (true) {
                $menungguDosen > 0 => 'Nilai belum bisa divalidasi: dosen pengampu belum mengirim nilai kelasnya ke validasi.',
                $kosong > 0 => 'Belum ada nilai yang bisa divalidasi: mata kuliah yang belum bernilai tidak bisa divalidasi.',
                default => 'Semua nilai sudah divalidasi.',
            });
        }

        Krs::query()->whereKey($siap->modelKeys())->update(['nilai_divalidasi_at' => now(), 'nilai_divalidasi_oleh' => $request->user()->id]);

        return back()->with('success', "{$siap->count()} nilai mata kuliah divalidasi.".$catatan);
    }

    public function batalValidasi(Request $request, MahasiswaProfile $mahasiswa): RedirectResponse
    {
        $ids = $this->krsDipilih($request, $mahasiswa)->filter(fn (Krs $k): bool => $k->nilaiTervalidasi())->modelKeys();

        if ($ids === []) {
            return back()->with('error', 'Belum ada nilai yang divalidasi.');
        }

        Krs::query()->whereKey($ids)->update(['nilai_divalidasi_at' => null, 'nilai_divalidasi_oleh' => null]);

        return back()->with('success', 'Validasi '.count($ids).' nilai mata kuliah dibatalkan; nilainya bisa diubah lagi.');
    }

    /**
     * Nilai kelas berdosen pengampu yang belum dikirim dosen ke validasi (dan batas input nilainya belum lewat).
     * Kelas TA/Skripsi tanpa pengampu dan mata kuliah KKM dinilai admin, jadi tidak menunggu dosen.
     */
    private static function menungguDosen(Krs $krs): bool
    {
        $kelas = $krs->kelasKuliah;

        return $kelas !== null && $kelas->dosen_id !== null && ! $kelas->tugasAkhir()
            && ! (bool) $kelas->mataKuliah?->kkm() && ! $kelas->nilaiFinal();
    }

    /**
     * @return Collection<int, Krs>
     */
    private function krsDipilih(Request $request, MahasiswaProfile $mahasiswa): Collection
    {
        $request->validate(['krs_id' => ['nullable', 'integer']]);
        $krs = $this->krsTahun($mahasiswa, $this->tahunDipilih($request));

        if (! $request->filled('krs_id')) {
            return $krs;
        }

        $dipilih = $krs->where('id', $request->integer('krs_id'));
        abort_if($dipilih->isEmpty(), 404);

        return $dipilih;
    }
}
