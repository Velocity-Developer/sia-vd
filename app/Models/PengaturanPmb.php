<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Periode PMB (penerimaan calon mahasiswa baru).
class PengaturanPmb extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'pengaturan_pmb';

    protected $fillable = [
        'kode',
        'tahun_angkatan',
        'tanggal_buka',
        'tanggal_tutup',
        'tanggal_usm_mulai',
        'tanggal_usm_selesai',
        'tanggal_her',
        'nilai_minimal',
        'kapasitas',
        'biaya_pendaftaran',
        'tanggal_pembayaran_mulai',
        'tanggal_pembayaran_selesai',
        'is_open',
    ];

    protected function casts(): array
    {
        return [
            'tahun_angkatan' => 'integer',
            'tanggal_buka' => 'date:Y-m-d',
            'tanggal_tutup' => 'date:Y-m-d',
            'tanggal_usm_mulai' => 'date:Y-m-d',
            'tanggal_usm_selesai' => 'date:Y-m-d',
            'tanggal_her' => 'date:Y-m-d',
            'nilai_minimal' => 'float',
            'kapasitas' => 'integer',
            'biaya_pendaftaran' => 'integer',
            'tanggal_pembayaran_mulai' => 'date:Y-m-d',
            'tanggal_pembayaran_selesai' => 'date:Y-m-d',
            'is_open' => 'boolean',
        ];
    }

    /**
     * Periode yang menerima pendaftaran hari ini: dibuka admin dan tanggalnya di antara buka–tutup.
     */
    public function scopeMenerimaPendaftaran(Builder $query): void
    {
        $hariIni = today()->toDateString();
        $query->where('is_open', true)->whereDate('tanggal_buka', '<=', $hariIni)->whereDate('tanggal_tutup', '>=', $hariIni);
    }

    /** Periode yang sedang menerima pendaftaran (paling banyak satu, lihat validasi Atur Periode PMB). */
    public static function aktif(): ?self
    {
        return static::query()->menerimaPendaftaran()->orderBy('tanggal_buka')->first();
    }

    public function pendaftar(): HasMany
    {
        return $this->hasMany(Cmb::class, 'pengaturan_pmb_id');
    }
}
