<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class PengajuanIzin extends Model
{
    use SerializesDatesInAppTimezone;

    public const MENUNGGU = 'menunggu';

    public const DISETUJUI = 'disetujui';

    public const DITOLAK = 'ditolak';

    /** Jenis pengajuan sama dengan status presensi yang akan dipasang bila disetujui. */
    public const JENIS = [PresensiMahasiswa::IZIN, PresensiMahasiswa::SAKIT];

    /** Lampiran bukti (mis. surat dokter): dokumen atau foto saja. */
    public const EKSTENSI_LAMPIRAN = ['pdf', 'jpg', 'jpeg', 'png'];

    protected $fillable = ['pertemuan_id', 'mahasiswa_id', 'jenis', 'alasan', 'lampiran', 'status', 'diproses_oleh', 'diproses_at', 'catatan_dosen'];

    protected function casts(): array
    {
        return [
            'lampiran' => 'array',
            'diproses_at' => 'datetime',
        ];
    }

    public function pertemuan(): BelongsTo
    {
        return $this->belongsTo(Pertemuan::class);
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }

    public function pemroses(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    /**
     * Pengajuan diterima sampai akhir hari ke-N sesudah tanggal pertemuan (N dari Pengaturan Akademik).
     */
    public static function batasWaktu(Pertemuan $pertemuan): Carbon
    {
        return $pertemuan->tanggal->copy()->addDays(PengaturanAkademik::current()->batas_pengajuan_izin_hari)->endOfDay();
    }

    public static function masihBisaDiajukan(Pertemuan $pertemuan): bool
    {
        return $pertemuan->status !== Pertemuan::DIBATALKAN && now()->lte(self::batasWaktu($pertemuan));
    }
}
