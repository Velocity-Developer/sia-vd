<?php

namespace App;

use App\Models\KelasKuliah;
use App\Models\KomponenNilai;
use App\Models\Krs;
use App\Models\NilaiKomponen;
use App\Models\PresensiMahasiswa;
use App\Models\SkalaNilai;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nilai per komponen (Akademik → Penilaian): angka 0–100 per komponen nilai global, nilai akhir = rata-rata berbobot
 * persen komponen, lalu huruf dari angka minimal skala nilai prodi mata kuliah (Bobot Nilai, atau Skala Nilai umum).
 * Dipakai Nilai Semester (admin) dan tabel Nilai Mahasiswa di halaman kelas (dosen & admin).
 *
 * Mata kuliah PPL (dan TA/Skripsi bila pendadaran mati) dinilai "langsung": satu kolom Nilai Akhir 0–100 tanpa komponen,
 * disimpan di krs.nilai_angka. Mata kuliah KKM tidak lewat sini (menu Nilai KKM).
 */
class NilaiSemester
{
    /** Id kolom semu "Nilai Akhir" pada penilaian langsung. */
    public const LANGSUNG = 0;

    public static function langsung(KelasKuliah $kelas): bool
    {
        return (bool) $kelas->loadMissing('mataKuliah')->mataKuliah?->nilaiLangsung();
    }

    public static function kkm(KelasKuliah $kelas): bool
    {
        return (bool) $kelas->loadMissing('mataKuliah')->mataKuliah?->kkm();
    }

    /**
     * Komponen yang dipakai kelas ini: komponen global, atau satu kolom Nilai Akhir 100% untuk penilaian langsung.
     *
     * @return Collection<int, KomponenNilai>
     */
    public static function komponenKelas(KelasKuliah $kelas): Collection
    {
        if (! self::langsung($kelas)) {
            return KomponenNilai::urut();
        }

        $kolom = new KomponenNilai(['nama' => 'Nilai Akhir', 'persen' => 100, 'urutan' => 1, 'sumber' => KomponenNilai::MANUAL]);
        $kolom->id = self::LANGSUNG;

        return new Collection([$kolom]);
    }

    /**
     * @param  Collection<int, KomponenNilai>  $komponen
     */
    public static function totalPersen(Collection $komponen): float
    {
        return round((float) $komponen->sum('persen'), 2);
    }

    /**
     * @param  Collection<int, KomponenNilai>  $komponen
     */
    public static function persenLengkap(Collection $komponen): bool
    {
        return $komponen->isNotEmpty() && abs(self::totalPersen($komponen) - 100) < 0.005;
    }

    /**
     * Skala prodi punya angka minimal, jadi nilai akhir bisa diubah menjadi huruf.
     */
    public static function skalaSiap(?int $prodiId): bool
    {
        return SkalaNilai::semua($prodiId)->contains(fn ($nilai): bool => $nilai->angka_minimal !== null);
    }

    /**
     * Kelas dinilai lewat komponen: komponen lengkap (100%), skala prodi punya angka minimal, dan bukan TA/Skripsi.
     * Selain itu huruf tetap dipilih langsung seperti sebelumnya.
     */
    public static function aktif(KelasKuliah $kelas): bool
    {
        if (self::kkm($kelas)) {
            return false;
        }

        return (self::langsung($kelas) || (! $kelas->tugasAkhir() && self::persenLengkap(KomponenNilai::urut())))
            && self::skalaSiap($kelas->mataKuliah?->prodi_id);
    }

    /**
     * Alasan kelas belum bisa dinilai lewat komponen, atau null bila siap.
     */
    public static function alasanBelumSiap(KelasKuliah $kelas): ?string
    {
        return match (true) {
            self::kkm($kelas) => 'Nilai mata kuliah KKM diisi admin di Akademik → Penilaian → Nilai KKM.',
            ! self::langsung($kelas) && ! self::persenLengkap(KomponenNilai::urut()) => 'Komponen nilai belum diatur atau jumlah persennya belum 100%. Admin mengaturnya di Akademik → Penilaian → Tambah Komponen Nilai.',
            ! self::skalaSiap($kelas->loadMissing('mataKuliah')->mataKuliah?->prodi_id) => 'Angka minimal huruf untuk prodi mata kuliah ini belum diatur. Admin mengisinya di Akademik → Konfigurasi → Bobot Nilai.',
            default => null,
        };
    }

    /**
     * Rata-rata berbobot; null selama masih ada komponen yang belum diisi.
     *
     * @param  array<int, float|null>  $angka  komponen_nilai_id => angka
     * @param  Collection<int, KomponenNilai>  $komponen
     */
    public static function nilaiAkhir(array $angka, Collection $komponen): ?float
    {
        if ($komponen->isEmpty()) {
            return null;
        }

        $total = 0.0;
        foreach ($komponen as $item) {
            if (! isset($angka[$item->id])) {
                return null;
            }
            $total += $angka[$item->id] * $item->persen / 100;
        }

        return round($total, 2);
    }

    /**
     * Persentase kehadiran tiap mahasiswa di kelas: hadir/terlambat dibagi presensi di pertemuan kuliah yang sudah
     * selesai (dasar yang sama dengan syarat UAS). Mahasiswa tanpa presensi tidak punya angka.
     *
     * @return array<int, float> mahasiswa_id => persen
     */
    public static function kehadiran(KelasKuliah $kelas): array
    {
        $pertemuan = SyaratUjian::pertemuanDihitung($kelas->pertemuans()->get(['id', 'kelas_id', 'jenis', 'status']));

        return PresensiMahasiswa::query()
            ->whereIn('pertemuan_id', $pertemuan->pluck('id'))
            ->get(['mahasiswa_id', 'status'])
            ->groupBy('mahasiswa_id')
            ->map(fn (Collection $presensi): float => round($presensi->whereIn('status', PresensiMahasiswa::DIHITUNG_HADIR)->count() / $presensi->count() * 100, 2))
            ->all();
    }

    /**
     * Peserta remidi setelah daftar remidi dikunci: huruf akhirnya hanya berubah lewat remidi, jadi barisnya tidak
     * dihitung ulang dari komponen (kalau tidak, huruf hasil remidi tertimpa).
     *
     * @return list<int> mahasiswa_id
     */
    public static function terkunciRemidi(KelasKuliah $kelas): array
    {
        return $kelas->remidi_dikunci_at === null ? [] : $kelas->remidiPesertas()->pluck('mahasiswa_id')->all();
    }

    /**
     * Data tabel nilai komponen untuk halaman (Nilai Semester admin & halaman kelas).
     *
     * @return array<string, mixed>
     */
    public static function tabel(KelasKuliah $kelas): array
    {
        $prodiId = $kelas->loadMissing('mataKuliah')->mataKuliah?->prodi_id;
        $komponen = self::komponenKelas($kelas);
        $langsung = self::langsung($kelas);
        $kehadiran = $langsung ? [] : self::kehadiran($kelas);
        $terkunci = array_flip(self::terkunciRemidi($kelas));

        $krs = $kelas->krs()
            ->with(['mahasiswa:id,user_id,nim,prodi_id', 'mahasiswa.user:id,name', 'mahasiswa.prodi:id,nama_prodi', 'nilaiKomponen:id,krs_id,komponen_nilai_id,nilai'])
            ->get()
            ->sortBy(fn (Krs $k): string => (string) $k->mahasiswa?->nim)
            ->values();

        return [
            'komponen' => $komponen->map(fn (KomponenNilai $k): array => $k->only(['id', 'nama', 'persen', 'sumber'])),
            'langsung' => $langsung,
            'persenLengkap' => self::persenLengkap($komponen),
            'skalaSiap' => self::skalaSiap($prodiId),
            'skala' => SkalaNilai::semua($prodiId)
                ->filter(fn ($n): bool => $n->angka_minimal !== null)
                ->sortByDesc('angka_minimal')
                ->map(fn ($n): array => ['huruf' => $n->huruf, 'angka_minimal' => $n->angka_minimal, 'lulus' => $n->lulus])
                ->values(),
            'mahasiswa' => $krs->map(fn (Krs $k): array => [
                'krs_id' => $k->id,
                'mahasiswa_id' => $k->mahasiswa_id,
                'nim' => $k->mahasiswa?->nim,
                'nama' => $k->mahasiswa?->user?->name,
                'prodi' => $k->mahasiswa?->prodi?->nama_prodi,
                'nilai' => (object) ($langsung
                    ? [self::LANGSUNG => $k->nilai_angka]
                    : $k->nilaiKomponen->mapWithKeys(fn ($n): array => [$n->komponen_nilai_id => $n->nilai])->all()),
                'kehadiran' => $kehadiran[$k->mahasiswa_id] ?? null,
                'nilai_angka' => $k->nilai_angka,
                'huruf' => $k->nilai,
                'terkunci_remidi' => isset($terkunci[$k->mahasiswa_id]),
                'tervalidasi' => $k->nilaiTervalidasi(),
            ]),
        ];
    }

    /**
     * Simpan angka komponen untuk KRS kelas yang dikirim. Komponen Kehadiran selalu diambil dari presensi, bukan dari
     * isian. Baris lengkap → nilai akhir + huruf otomatis. Baris yang belum lengkap hanya menyimpan angkanya; bila
     * sebelumnya hurufnya berasal dari komponen, nilai akhir dan huruf dikosongkan agar tidak basi. KRS yang hurufnya
     * diisi manual tanpa komponen, peserta remidi yang terkunci, dan nilai yang sudah divalidasi tidak disentuh.
     *
     * @param  array<int|string, array<int|string, mixed>>  $isian  krs_id => [komponen_nilai_id => angka|null]
     * @param  Collection<int, KomponenNilai>  $komponen
     */
    public static function simpan(KelasKuliah $kelas, array $isian, Collection $komponen): int
    {
        $prodiId = $kelas->loadMissing('mataKuliah')->mataKuliah?->prodi_id;
        if (self::langsung($kelas)) {
            return self::simpanLangsung($kelas, $isian, $prodiId);
        }
        $krs = $kelas->krs()->with(['nilaiKomponen', 'mahasiswa:id,nim'])->get()->keyBy('id');
        $kehadiran = $komponen->contains(fn (KomponenNilai $k): bool => $k->otomatis()) ? self::kehadiran($kelas) : [];
        $terkunci = array_flip(self::terkunciRemidi($kelas));

        return DB::transaction(function () use ($isian, $krs, $komponen, $prodiId, $kehadiran, $terkunci): int {
            $berubah = 0;

            foreach ($isian as $krsId => $baris) {
                /** @var Krs|null $item */
                $item = $krs->get((int) $krsId);
                if ($item === null || ! is_array($baris) || isset($terkunci[$item->mahasiswa_id]) || $item->nilaiTervalidasi()) {
                    continue;
                }

                $angka = [];
                foreach ($komponen->reject(fn (KomponenNilai $k): bool => $k->otomatis()) as $k) {
                    $nilai = $baris[$k->id] ?? null;
                    $angka[$k->id] = $nilai === null || $nilai === '' ? null : round((float) $nilai, 2);
                }
                // Kehadiran ikut tersimpan hanya bila ada komponen lain yang diisi, agar baris kosong tetap kosong.
                $adaIsian = $angka === [] || collect($angka)->contains(fn (?float $nilai): bool => $nilai !== null);
                foreach ($komponen->filter(fn (KomponenNilai $k): bool => $k->otomatis()) as $k) {
                    $angka[$k->id] = $adaIsian ? ($kehadiran[$item->mahasiswa_id] ?? null) : null;
                }

                $lama = $item->nilaiKomponen->keyBy('komponen_nilai_id');
                foreach ($angka as $komponenId => $nilai) {
                    $ada = $lama->get($komponenId);
                    if ($nilai === null) {
                        $ada?->delete();
                    } elseif ($ada === null) {
                        NilaiKomponen::create(['krs_id' => $item->id, 'komponen_nilai_id' => $komponenId, 'nilai' => $nilai]);
                    } elseif ($ada->nilai !== $nilai) {
                        $ada->update(['nilai' => $nilai]);
                    }
                }

                $akhir = self::nilaiAkhir($angka, $komponen);
                if ($akhir !== null) {
                    $huruf = SkalaNilai::dariAngka($akhir, $prodiId);
                    if ($huruf === null) {
                        throw ValidationException::withMessages([
                            "nilai.{$item->id}" => "Nilai akhir {$akhir} ({$item->mahasiswa?->nim}) di bawah angka minimal semua huruf pada skala nilai.",
                        ]);
                    }
                    $data = ['nilai_angka' => $akhir, 'nilai' => $huruf];
                } elseif ($item->nilai_angka !== null) {
                    $data = ['nilai_angka' => null, 'nilai' => null];
                } else {
                    continue;
                }

                $item->fill($data);
                if ($item->isDirty()) {
                    $item->save();
                    $berubah++;
                }
            }

            return $berubah;
        });
    }

    /**
     * Penilaian langsung: isian krs_id => [0 => angka|null]. Angka → nilai_angka + huruf; kosong → keduanya dikosongkan
     * bila hurufnya berasal dari angka.
     * Peserta remidi terkunci dan nilai tervalidasi dilewati, sama seperti penilaian komponen.
     *
     * @param  array<int|string, array<int|string, mixed>>  $isian
     */
    private static function simpanLangsung(KelasKuliah $kelas, array $isian, ?int $prodiId): int
    {
        $krs = $kelas->krs()->with('mahasiswa:id,nim')->get()->keyBy('id');
        $terkunci = array_flip(self::terkunciRemidi($kelas));

        return DB::transaction(function () use ($isian, $krs, $terkunci, $prodiId): int {
            $berubah = 0;

            foreach ($isian as $krsId => $baris) {
                /** @var Krs|null $item */
                $item = $krs->get((int) $krsId);
                if ($item === null || ! is_array($baris) || isset($terkunci[$item->mahasiswa_id]) || $item->nilaiTervalidasi()) {
                    continue;
                }

                $nilai = $baris[self::LANGSUNG] ?? null;
                if ($nilai === null || $nilai === '') {
                    // Huruf yang diisi tanpa angka (mis. data lama) tidak dihapus oleh baris kosong.
                    if ($item->nilai_angka === null) {
                        continue;
                    }
                    $data = ['nilai_angka' => null, 'nilai' => null];
                } else {
                    $akhir = round((float) $nilai, 2);
                    $huruf = SkalaNilai::dariAngka($akhir, $prodiId);
                    if ($huruf === null) {
                        throw ValidationException::withMessages([
                            "nilai.{$item->id}" => "Nilai akhir {$akhir} ({$item->mahasiswa?->nim}) di bawah angka minimal semua huruf pada skala nilai.",
                        ]);
                    }
                    $data = ['nilai_angka' => $akhir, 'nilai' => $huruf];
                }

                $item->fill($data);
                if ($item->isDirty()) {
                    $item->save();
                    $berubah++;
                }
            }

            return $berubah;
        });
    }
}
