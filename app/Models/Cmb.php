<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Pendaftar PMB (calon mahasiswa baru).
class Cmb extends Model
{
    use SerializesDatesInAppTimezone;

    public const STATUS_LULUS = 'lulus';

    public const STATUS_DITOLAK = 'ditolak';

    protected $table = 'cmb';

    protected $guarded = ['id', 'pengaturan_pmb_id', 'nomor_pendaftaran', 'nilai', 'status_pendaftaran'];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date:Y-m-d',
            'penerima_kps' => 'boolean',
            'alat_transportasi' => 'integer',
            'jenis_tinggal' => 'integer',
            'jenis_masuk' => 'integer',
            'jenis_pembiayaan' => 'integer',
            'jumlah_pembiayaan' => 'integer',
            'sks_diakui' => 'integer',
            'nilai' => 'float',
        ];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PengaturanPmb::class, 'pengaturan_pmb_id');
    }

    public function agama(): BelongsTo
    {
        return $this->belongsTo(Agama::class);
    }

    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(WilayahKecamatan::class, 'wilayah_kecamatan_id');
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class);
    }
}
