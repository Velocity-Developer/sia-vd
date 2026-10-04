<?php

namespace App\Models;

use App\Models\Concerns\DibatasiProdi;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramStudi extends Model
{
    use DibatasiProdi, SerializesDatesInAppTimezone;

    /**
     * Status program studi (mengikuti istilah PDDikti).
     */
    public const STATUS_PRODI = ['Aktif', 'Pembinaan', 'Alih Bentuk', 'Alih Kelola', 'Tutup'];

    protected $fillable = [
        'fakultas_id', 'kode_prodi', 'nama_prodi', 'jenjang', 'gelar_akademik', 'singkatan_gelar', 'sks_lulus', 'maks_sks_tanpa_ips', 'status_prodi',
        'status_akreditasi', 'no_sk_akreditasi', 'tanggal_akreditasi_mulai', 'tanggal_akreditasi_akhir', 'kaprodi', 'nomor_kaprodi',
        'operator', 'nomor_operator', 'no_sk_dikti', 'tanggal_sk_dikti', 'tanggal_berakhir_sk_dikti', 'tahun_berdiri', 'alamat',
        'provinsi_id', 'kota_id', 'kode_pos', 'telepon', 'faximili', 'email', 'website',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_akreditasi_mulai' => 'date',
            'tanggal_akreditasi_akhir' => 'date',
            'tanggal_sk_dikti' => 'date',
            'tanggal_berakhir_sk_dikti' => 'date',
            'sks_lulus' => 'integer',
            'maks_sks_tanpa_ips' => 'integer',
        ];
    }

    public function fakultas(): BelongsTo
    {
        return $this->belongsTo(Fakultas::class);
    }

    public function provinsi(): BelongsTo
    {
        return $this->belongsTo(Provinsi::class, 'provinsi_id');
    }

    public function kota(): BelongsTo
    {
        return $this->belongsTo(Kota::class, 'kota_id');
    }

    public function ketuaProgramStudi(): BelongsTo
    {
        return $this->belongsTo(DosenProfile::class, 'kaprodi');
    }

    /**
     * Baris unit di kop dokumen PDF, mis. "Fakultas Teknik · Program Studi S1 Informatika".
     */
    public function unitKop(): string
    {
        return collect([
            $this->fakultas?->nama_fakultas ? 'Fakultas '.preg_replace('/^Fakultas\s+/i', '', $this->fakultas->nama_fakultas) : null,
            'Program Studi '.trim(($this->jenjang ? $this->jenjang.' ' : '').$this->nama_prodi),
        ])->filter()->implode(' · ');
    }

    public function mataKuliah(): HasMany
    {
        return $this->hasMany(MataKuliah::class, 'prodi_id');
    }

    public function batasSks(): HasMany
    {
        return $this->hasMany(BatasSksProdi::class, 'prodi_id');
    }

    public function bobotNilai(): HasMany
    {
        return $this->hasMany(BobotNilai::class, 'prodi_id');
    }

    public function mahasiswa(): HasMany
    {
        return $this->hasMany(MahasiswaProfile::class, 'prodi_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public static function saringProdi(Builder $query, int $prodiId): void
    {
        $query->whereKey($prodiId);
    }

    public function milikProdi(int $prodiId): bool
    {
        return (int) $this->getKey() === $prodiId;
    }
}
