<?php

namespace App\Models;

use App\Feature;
use App\Models\Concerns\DibatasiProdi;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MataKuliah extends Model
{
    use DibatasiProdi, SerializesDatesInAppTimezone;

    /**
     * Jenis penilaian: reguler (komponen nilai), TA/Skripsi, PPL (satu nilai akhir di Nilai Semester), dan KKM
     * (nilai diisi admin di menu Nilai KKM). Kolom `tugas_akhir` selalu mengikuti jenis tugas_akhir.
     */
    public const REGULER = 'reguler';

    public const TUGAS_AKHIR = 'tugas_akhir';

    public const PPL = 'ppl';

    public const KKM = 'kkm';

    public const JENIS_PENILAIAN = [
        self::REGULER => 'Reguler',
        self::TUGAS_AKHIR => 'TA/Skripsi',
        self::PPL => 'PPL',
        self::KKM => 'KKM/PKL/KKN',
    ];

    protected $table = 'mata_kuliahs';

    protected $fillable = ['kode_matkul', 'nama_matkul', 'sks', 'semester', 'jenis', 'jenis_penilaian', 'tugas_akhir', 'prodi_id'];

    protected $attributes = ['jenis_penilaian' => self::REGULER];

    protected static function booted(): void
    {
        // Kode lama (dan tes) mengisi tugas_akhir; kode baru mengisi jenis_penilaian. Keduanya dijaga selaras.
        static::saving(function (self $mataKuliah): void {
            if ($mataKuliah->isDirty('jenis_penilaian')) {
                $mataKuliah->tugas_akhir = $mataKuliah->jenis_penilaian === self::TUGAS_AKHIR;
            } elseif ($mataKuliah->isDirty('tugas_akhir')) {
                $mataKuliah->jenis_penilaian = $mataKuliah->tugas_akhir ? self::TUGAS_AKHIR
                    : ($mataKuliah->jenis_penilaian === self::TUGAS_AKHIR ? self::REGULER : $mataKuliah->jenis_penilaian);
            }
        });
    }

    /**
     * Nilai satu angka akhir (tanpa komponen) di Nilai Semester: PPL, dan TA/Skripsi bila pendadaran dimatikan.
     */
    public function nilaiLangsung(): bool
    {
        return $this->jenis_penilaian === self::PPL
            || ($this->jenis_penilaian === self::TUGAS_AKHIR && ! Feature::aktif('pendadaran'));
    }

    public function kkm(): bool
    {
        return $this->jenis_penilaian === self::KKM;
    }

    protected function casts(): array
    {
        return [
            'sks' => 'integer',
            'semester' => 'integer',
            'tugas_akhir' => 'boolean',
        ];
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'prodi_id');
    }

    public function kelasKuliah(): HasMany
    {
        return $this->hasMany(KelasKuliah::class, 'matkul_id');
    }

    /**
     * Mata kuliah yang harus lulus sebelum mata kuliah ini bisa diambil.
     */
    public function prasyarat(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'mata_kuliah_prasyarat', 'mata_kuliah_id', 'prasyarat_id');
    }

    /**
     * Mata kuliah yang mensyaratkan mata kuliah ini.
     */
    public function menjadiPrasyarat(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'mata_kuliah_prasyarat', 'prasyarat_id', 'mata_kuliah_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public static function saringProdi(Builder $query, int $prodiId): void
    {
        $query->where($query->qualifyColumn('prodi_id'), $prodiId);
    }

    public function milikProdi(int $prodiId): bool
    {
        return (int) $this->prodi_id === $prodiId;
    }
}
