<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\NilaiMahasiswa;
use App\Http\Controllers\Controller;
use App\Models\KomponenNilai;
use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\TahunAkademik;
use App\NilaiSemester;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Detail Nilai: daftar mahasiswa yang punya KRS di satu tahun akademik, lalu nilai per mata kuliah (angka tiap komponen,
 * nilai akhir, huruf) beserta status validasinya. Validasi sendiri ada di menu Validasi Nilai (ValidasiNilaiController).
 */
class DetailNilaiController extends Controller
{
    use NilaiMahasiswa;

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/DetailNilai', $this->daftarMahasiswaNilai($request));
    }

    public function show(Request $request, MahasiswaProfile $mahasiswa): Response
    {
        [$tahun, $tahunAkademiks] = $this->tahunKrs($request);
        $mahasiswa->load(['user:id,name', 'prodi:id,nama_prodi,jenjang']);
        $krs = $this->krsTahun($mahasiswa, $tahun);
        $komponen = KomponenNilai::urut();
        $otomatis = $komponen->first(fn (KomponenNilai $k): bool => $k->otomatis());

        return Inertia::render('Admin/DetailNilaiMahasiswa', [
            'mahasiswa' => $this->identitasMahasiswa($mahasiswa),
            'tahunAkademikId' => $tahun?->id,
            'tahunAkademikOptions' => $tahunAkademiks->map(fn (TahunAkademik $t): array => ['id' => $t->id, 'name' => $t->label().($t->status ? ' (aktif)' : '')]),
            'komponen' => $komponen->map(fn (KomponenNilai $k): array => $k->only(['id', 'nama', 'persen', 'sumber'])),
            'nilai' => $krs->map(function (Krs $k) use ($otomatis, $mahasiswa): array {
                $nilai = $k->nilaiKomponen->mapWithKeys(fn ($n): array => [$n->komponen_nilai_id => $n->nilai])->all();
                // Kehadiran yang belum tersimpan (nilai kelas belum disimpan) ditampilkan dari presensi saat ini.
                $mk = $k->kelasKuliah->mataKuliah;
                $tanpaKomponen = match (true) {
                    (bool) $mk?->kkm() => 'Dari Nilai KKM',
                    (bool) $mk?->nilaiLangsung() => 'Nilai langsung (tanpa komponen)',
                    $k->kelasKuliah->tugasAkhir() => 'Dari hasil pendadaran',
                    default => null,
                };
                if ($otomatis !== null && ! isset($nilai[$otomatis->id]) && $tanpaKomponen === null) {
                    $nilai[$otomatis->id] = NilaiSemester::kehadiran($k->kelasKuliah)[$mahasiswa->id] ?? null;
                }

                return [
                    'krs_id' => $k->id,
                    'kode_matkul' => $k->kelasKuliah->mataKuliah?->kode_matkul,
                    'nama_matkul' => $k->kelasKuliah->mataKuliah?->nama_matkul,
                    'sks' => (int) ($k->kelasKuliah->mataKuliah?->sks ?? 0),
                    'kode_kelas' => $k->kelasKuliah->kode_kelas,
                    'tanpa_komponen' => $tanpaKomponen,
                    'nilai' => (object) $nilai,
                    'nilai_angka' => $k->nilai_angka,
                    'huruf' => $k->nilai,
                    'divalidasi_at' => $k->nilai_divalidasi_at?->toIso8601String(),
                    'divalidasi_oleh' => $k->validator?->name,
                ];
            }),
        ]);
    }
}
