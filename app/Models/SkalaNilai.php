<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Skala nilai huruf umum (diatur admin di Pengaturan Akademik) untuk KRS, KHS, dan transkrip. Prodi yang punya
 * Bobot Nilai sendiri memakai bobot prodinya; semua fungsi statis di sini menerima prodi untuk itu.
 */
class SkalaNilai extends Model
{
    use SerializesDatesInAppTimezone;

    protected $fillable = ['huruf', 'bobot', 'angka_minimal', 'lulus', 'boleh_diulang'];

    protected function casts(): array
    {
        return [
            'bobot' => 'float',
            'angka_minimal' => 'float',
            'lulus' => 'boolean',
            'boleh_diulang' => 'boolean',
        ];
    }

    /**
     * Skala yang berlaku untuk prodi: Bobot Nilai prodi itu bila sudah diatur, selain itu skala umum ini.
     * Dimuat sekali per request untuk tiap prodi. Prodi dari mata kuliah (lihat Krs::prodiNilai()).
     *
     * @return Collection<string, self|BobotNilai>
     */
    public static function semua(?int $prodiId = null): Collection
    {
        return once(function () use ($prodiId): Collection {
            $prodi = $prodiId === null
                ? new Collection
                : BobotNilai::query()->where('prodi_id', $prodiId)->orderByDesc('bobot')->orderBy('huruf')->get()->keyBy('huruf');

            return $prodi->isNotEmpty() ? $prodi : static::query()->orderByDesc('bobot')->orderBy('huruf')->get()->keyBy('huruf');
        });
    }

    /**
     * @return list<string>
     */
    public static function huruf(?int $prodiId = null): array
    {
        return static::semua($prodiId)->keys()->all();
    }

    public static function bobot(?string $nilai, ?int $prodiId = null): ?float
    {
        return static::semua($prodiId)->get(strtoupper((string) $nilai))?->bobot;
    }

    public static function lulus(?string $nilai, ?int $prodiId = null): bool
    {
        return (bool) static::semua($prodiId)->get(strtoupper((string) $nilai))?->lulus;
    }

    public static function bolehDiulang(?string $nilai, ?int $prodiId = null): bool
    {
        return (bool) static::semua($prodiId)->get(strtoupper((string) $nilai))?->boleh_diulang;
    }

    /**
     * Huruf yang boleh diberikan dengan batas atas tertentu (bobotnya tidak melebihi huruf batas). Batas kosong = semua.
     * Bila huruf batas tidak ada di skala prodi, bobotnya diambil dari skala umum.
     *
     * @return list<string>
     */
    public static function hurufSampai(?string $maks, ?int $prodiId = null): array
    {
        $batas = static::bobot($maks, $prodiId) ?? static::bobot($maks);

        return $batas === null
            ? static::huruf($prodiId)
            : static::semua($prodiId)->filter(fn (Model $nilai): bool => (float) $nilai->bobot <= (float) $batas)->keys()->all();
    }

    /**
     * Huruf untuk nilai angka 0–100: huruf dengan angka minimal tertinggi yang masih terlampaui.
     * Null bila tidak ada huruf yang angka minimalnya diatur.
     */
    public static function dariAngka(float $angka, ?int $prodiId = null): ?string
    {
        return static::semua($prodiId)
            ->filter(fn (Model $nilai): bool => $nilai->angka_minimal !== null && $angka >= $nilai->angka_minimal)
            ->sortByDesc('angka_minimal')
            ->keys()
            ->first();
    }

    /**
     * Jumlah KRS bernilai per huruf. Dengan prodi: KRS mata kuliah prodi itu. Tanpa prodi: KRS yang memakai skala
     * umum, yaitu mata kuliah dari prodi yang belum punya Bobot Nilai sendiri.
     *
     * @return array<string, int>
     */
    public static function jumlahDipakai(?int $prodiId = null): array
    {
        return Krs::query()
            ->whereNotNull('nilai')
            ->whereHas('kelasKuliah.mataKuliah', fn ($query) => $prodiId !== null
                ? $query->where('prodi_id', $prodiId)
                : $query->whereNotIn('prodi_id', BobotNilai::query()->select('prodi_id')))
            ->selectRaw('UPPER(nilai) as huruf, COUNT(*) as jumlah')
            ->groupByRaw('UPPER(nilai)')
            ->pluck('jumlah', 'huruf')
            ->map(fn ($jumlah): int => (int) $jumlah)
            ->all();
    }
}
