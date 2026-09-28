<?php

namespace App;

use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\MataKuliah;
use App\Models\SkalaNilai;
use App\Models\TahunAkademik;
use Illuminate\Support\Collection;

/**
 * Mata kuliah yang ditawarkan di KRS seorang mahasiswa pada satu tahun akademik:
 * 1. semester mata kuliah sama dengan semester mahasiswa;
 * 2. tertunda: semester lebih kecil dengan paritas sama (Ganjil/Genap) dan belum pernah diambil,
 *    misalnya karena cuti. Mata kuliah itu muncul lagi tiap dua semester sampai diambil;
 * 3. mengulang: pernah diambil dengan nilai yang boleh diulang.
 *
 * Mata kuliah yang prasyaratnya belum lulus tetap ditawarkan tetapi terkunci.
 */
class TawaranKrs
{
    public const SEMESTER_INI = 'semester';

    public const TERTUNDA = 'tertunda';

    public const MENGULANG = 'mengulang';

    private readonly ?int $semester;

    /** @var Collection<int, Collection<int, Krs>> KRS mahasiswa per mata kuliah, termasuk tahun akademik ini. */
    private readonly Collection $riwayat;

    /**
     * @param  Collection<int, Krs>  $semuaKrs  seluruh KRS mahasiswa, dengan relasi kelasKuliah
     */
    public function __construct(MahasiswaProfile $mahasiswa, private readonly ?TahunAkademik $tahunAkademik, Collection $semuaKrs)
    {
        $this->semester = $mahasiswa->semesterPada($tahunAkademik);
        $this->riwayat = $semuaKrs
            ->filter(fn (Krs $krs): bool => $krs->kelasKuliah !== null)
            ->groupBy(fn (Krs $krs): int => $krs->kelasKuliah->matkul_id);
    }

    public function semester(): ?int
    {
        return $this->semester;
    }

    /**
     * Jenis tawaran mata kuliah (lihat konstanta), atau null bila tidak ditawarkan.
     */
    public function jenis(MataKuliah $mataKuliah): ?string
    {
        if ($this->semester === null) {
            return null;
        }

        $sebelumnya = $this->pengambilanSebelumnya($mataKuliah->id);

        // Sudah lulus atau nilainya belum keluar: tidak ditawarkan lagi.
        if ($sebelumnya->isNotEmpty()) {
            return $this->alasanTidakBolehMengulang($sebelumnya) === null ? self::MENGULANG : null;
        }

        if ($mataKuliah->semester === $this->semester) {
            return self::SEMESTER_INI;
        }

        $tertunda = $mataKuliah->semester < $this->semester && ($this->semester - $mataKuliah->semester) % 2 === 0;

        return $tertunda ? self::TERTUNDA : null;
    }

    /**
     * Label singkat untuk halaman KRS, atau null untuk mata kuliah semester ini.
     */
    public function label(MataKuliah $mataKuliah): ?string
    {
        return match ($this->jenis($mataKuliah)) {
            self::TERTUNDA => "Tertunda smt {$mataKuliah->semester}",
            self::MENGULANG => 'Mengulang',
            default => null,
        };
    }

    /**
     * Alasan mata kuliah tidak boleh diambil tahun ini, atau null bila boleh.
     */
    public function alasanTidakBolehAmbil(MataKuliah $mataKuliah): ?string
    {
        $diambilTahunIni = $this->riwayat->get($mataKuliah->id, collect())
            ->contains(fn (Krs $krs): bool => $krs->kelasKuliah->tahun_akademik_id === $this->tahunAkademik?->id);

        if ($diambilTahunIni) {
            return 'Anda sudah mengambil kelas di mata kuliah ini. Silakan isi form pindah kelas apabila ingin pindah kelas.';
        }

        $alasan = $this->alasanTidakBolehMengulang($this->pengambilanSebelumnya($mataKuliah->id));

        if ($alasan !== null) {
            return $alasan;
        }

        if ($this->jenis($mataKuliah) === null) {
            return 'Mata kuliah ini tidak ditawarkan untuk semester Anda.';
        }

        return $this->alasanPrasyarat($mataKuliah);
    }

    /**
     * Alasan mata kuliah terkunci karena prasyaratnya belum lulus, atau null bila tidak terkunci.
     * Prasyarat dianggap lulus bila nilainya sudah keluar dan lulus, termasuk nilai yang masih boleh diulang.
     */
    public function alasanPrasyarat(MataKuliah $mataKuliah): ?string
    {
        $belumLulus = $mataKuliah->prasyarat
            ->reject(fn (MataKuliah $prasyarat): bool => $this->riwayat->get($prasyarat->id, collect())
                ->contains(fn (Krs $krs): bool => filled($krs->nilai) && SkalaNilai::lulus($krs->nilai)));

        return $belumLulus->isEmpty() ? null : 'Prasyarat: '.$belumLulus->pluck('nama_matkul')->implode(', ').' belum lulus';
    }

    /**
     * @return Collection<int, Krs>
     */
    private function pengambilanSebelumnya(int $matkulId): Collection
    {
        return $this->riwayat->get($matkulId, collect())
            ->reject(fn (Krs $krs): bool => $krs->kelasKuliah->tahun_akademik_id === $this->tahunAkademik?->id)
            ->values();
    }

    /**
     * @param  Collection<int, Krs>  $sebelumnya
     */
    private function alasanTidakBolehMengulang(Collection $sebelumnya): ?string
    {
        foreach ($sebelumnya as $krs) {
            if (blank($krs->nilai)) {
                return 'Mata kuliah ini masih menunggu nilai dari pengambilan sebelumnya.';
            }

            if (! SkalaNilai::bolehDiulang($krs->nilai)) {
                return "Anda sudah lulus mata kuliah ini dengan nilai {$krs->nilai}.";
            }
        }

        return null;
    }
}
