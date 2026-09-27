<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\Models\Concerns\TagihanBerbukti;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tagihan ujian susulan satu pengajuan yang disetujui. Batas bayar per tagihan (tanggal terbit + N hari).
 * Bila mahasiswanya ternyata mengikuti ujian utama, tagihan yang belum lunas dianggap dibatalkan dan yang
 * sudah lunas ditandai untuk pengembalian dana di luar sistem (status tampilan, tidak disimpan).
 */
class TagihanSusulan extends Model
{
    use SerializesDatesInAppTimezone;
    use TagihanBerbukti;

    /** Status tampilan saja: mahasiswa mengikuti ujian utama sebelum tagihan lunas. */
    public const DIBATALKAN = 'dibatalkan';

    protected $table = 'tagihan_susulan';

    protected $fillable = [
        'pengajuan_susulan_id', 'mahasiswa_id', 'ujian_id', 'kelas_id', 'rincian', 'total', 'status', 'batas_bayar',
        'bukti', 'bukti_diunggah_at', 'alasan_tolak', 'diverifikasi_oleh', 'diverifikasi_at', 'diterbitkan_oleh',
    ];

    protected function casts(): array
    {
        return [
            'rincian' => 'array',
            'total' => 'integer',
            'batas_bayar' => 'date:Y-m-d',
            'bukti_diunggah_at' => 'datetime',
            'diverifikasi_at' => 'datetime',
        ];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanSusulan::class, 'pengajuan_susulan_id');
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }

    public function ujian(): BelongsTo
    {
        return $this->belongsTo(Ujian::class);
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'kelas_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function batasBayar(): ?Carbon
    {
        return $this->batas_bayar;
    }

    public function statusSusulan(bool $ikutUjianUtama): string
    {
        return $ikutUjianUtama && $this->status !== self::LUNAS ? self::DIBATALKAN : $this->statusTampil();
    }
}
