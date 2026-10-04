<?php

namespace App;

use App\Models\KelasKuliah;
use App\Models\Krs;
use Illuminate\Validation\ValidationException;

/**
 * Komponen nilai Kehadiran ikut berubah saat presensi berubah. Perubahan presensi (atau status pertemuan) menandai
 * kelasnya; di akhir request/perintah, nilai akhir & huruf KRS kelas itu dihitung ulang dari angka komponen yang
 * tersimpan. Nilai tervalidasi dan peserta remidi terkunci tidak disentuh (lihat NilaiSemester::simpan).
 */
class SegarkanKehadiran
{
    /** @var array<int, true> kelas_id yang presensinya berubah */
    private static array $kelas = [];

    private static ?int $terdaftarDi = null;

    public static function tandai(?int $kelasId): void
    {
        if ($kelasId === null) {
            return;
        }
        self::$kelas[$kelasId] = true;

        // Satu callback per instance aplikasi (tes membuat aplikasi baru tiap kasus).
        $app = app();
        if (self::$terdaftarDi !== spl_object_id($app)) {
            self::$terdaftarDi = spl_object_id($app);
            $app->terminating(fn () => self::jalankan());
        }
    }

    /**
     * Hitung ulang semua kelas yang ditandai. Dipanggil otomatis saat aplikasi selesai menangani request.
     */
    public static function jalankan(): void
    {
        $ids = array_keys(self::$kelas);
        self::$kelas = [];

        foreach (KelasKuliah::query()->withoutGlobalScopes()->whereKey($ids)->get() as $kelas) {
            self::kelas($kelas);
        }
    }

    /**
     * Hitung ulang satu kelas: hanya baris yang sudah punya angka komponen lain (baris kosong tetap kosong).
     *
     * @return int jumlah nilai akhir yang berubah
     */
    public static function kelas(KelasKuliah $kelas): int
    {
        if (! NilaiSemester::aktif($kelas) || NilaiSemester::langsung($kelas)) {
            return 0;
        }
        $komponen = NilaiSemester::komponenKelas($kelas);
        if (! NilaiSemester::persenLengkap($komponen) || ! $komponen->contains(fn ($k): bool => $k->otomatis())) {
            return 0;
        }

        $manual = $komponen->reject(fn ($k): bool => $k->otomatis())->pluck('id')->all();
        $isian = $kelas->krs()->with('nilaiKomponen')->get()
            ->filter(fn (Krs $k): bool => $k->nilaiKomponen->whereIn('komponen_nilai_id', $manual)->isNotEmpty())
            ->mapWithKeys(fn (Krs $k): array => [$k->id => $k->nilaiKomponen->whereIn('komponen_nilai_id', $manual)
                ->mapWithKeys(fn ($n): array => [$n->komponen_nilai_id => $n->nilai])->all()])
            ->all();

        if ($isian === []) {
            return 0;
        }

        try {
            return NilaiSemester::simpan($kelas, $isian, $komponen);
        } catch (ValidationException $e) {
            // Nilai akhir di bawah skala: biarkan nilai lama; dosen/admin akan melihatnya saat menyimpan tabel nilai.
            report($e);

            return 0;
        }
    }
}
