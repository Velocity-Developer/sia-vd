<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Krs extends Model
{
    use SerializesDatesInAppTimezone;

    /**
     * Status mahasiswa yang boleh mengisi KRS.
     */
    public const STATUS_MAHASISWA_BOLEH_KRS = ['Aktif', 'Transfer Masuk'];

    protected $table = 'krs';

    protected $fillable = ['mahasiswa_id', 'kelas_id', 'nilai', 'status'];

    protected function casts(): array
    {
        return [];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }

    public function kelasKuliah(): BelongsTo
    {
        return $this->belongsTo(KelasKuliah::class, 'kelas_id');
    }

    /**
     * Hapus KRS beserta pengajuan pindah kelas yang masih menunggu untuk kelas ini.
     */
    public function cancel(): void
    {
        DB::transaction(function (): void {
            PengajuanPindahKelas::query()
                ->where('mahasiswa_id', $this->mahasiswa_id)
                ->where('kelas_asal_id', $this->kelas_id)
                ->where('status', PengajuanPindahKelas::STATUS_PENDING)
                ->delete();

            $this->delete();
        });
    }
}
