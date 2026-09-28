<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu perubahan jadwal (tanggal, jam, ruang, atau dosen) sebuah pertemuan beserta alasannya.
 */
class RiwayatJadwalPertemuan extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'riwayat_jadwal_pertemuan';

    protected $fillable = [
        'pertemuan_id', 'tanggal_lama', 'jam_mulai_lama', 'jam_akhir_lama', 'ruang_lama_id', 'dosen_lama_id',
        'tanggal_baru', 'jam_mulai_baru', 'jam_akhir_baru', 'ruang_baru_id', 'dosen_baru_id', 'alasan', 'diubah_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lama' => 'date:Y-m-d',
            'tanggal_baru' => 'date:Y-m-d',
        ];
    }

    public function pertemuan(): BelongsTo
    {
        return $this->belongsTo(Pertemuan::class);
    }

    public function ruangLama(): BelongsTo
    {
        return $this->belongsTo(Ruang::class, 'ruang_lama_id');
    }

    public function ruangBaru(): BelongsTo
    {
        return $this->belongsTo(Ruang::class, 'ruang_baru_id');
    }

    public function dosenLama(): BelongsTo
    {
        return $this->belongsTo(DosenProfile::class, 'dosen_lama_id');
    }

    public function dosenBaru(): BelongsTo
    {
        return $this->belongsTo(DosenProfile::class, 'dosen_baru_id');
    }

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }

    /**
     * Bentuk ringkas untuk frontend: dari → ke, alasan, oleh siapa, kapan.
     *
     * @return array<string, mixed>
     */
    public function ringkas(): array
    {
        return [
            'id' => $this->id,
            'tanggal_lama' => $this->tanggal_lama->toDateString(),
            'jam_mulai_lama' => substr($this->jam_mulai_lama, 0, 5),
            'jam_akhir_lama' => substr($this->jam_akhir_lama, 0, 5),
            'ruang_lama' => $this->ruangLama?->kode_ruang,
            'dosen_lama' => $this->dosenLama?->user?->name,
            'tanggal_baru' => $this->tanggal_baru->toDateString(),
            'jam_mulai_baru' => substr($this->jam_mulai_baru, 0, 5),
            'jam_akhir_baru' => substr($this->jam_akhir_baru, 0, 5),
            'ruang_baru' => $this->ruangBaru?->kode_ruang,
            'dosen_baru' => $this->dosenBaru?->user?->name,
            'alasan' => $this->alasan,
            'oleh' => $this->pengubah?->name,
            'waktu' => $this->created_at?->toIso8601String(),
        ];
    }
}
