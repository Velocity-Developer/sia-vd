<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\Transkrip;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Peserta wisuda (pendaftaran yang disetujui admin) beserta surat keterangan lulus (SKL).
 */
class Wisuda extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'wisuda';

    protected $fillable = ['pengajuan_id', 'periode_wisuda_id', 'mahasiswa_id', 'tugas_akhir_id', 'nomor_skl', 'skl_terbit_at', 'tanggal_lulus', 'ipk', 'total_sks', 'predikat', 'skl_oleh'];

    protected function casts(): array
    {
        return ['skl_terbit_at' => 'datetime', 'tanggal_lulus' => 'date', 'ipk' => 'float', 'total_sks' => 'integer'];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanAkademik::class, 'pengajuan_id');
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PeriodeWisuda::class, 'periode_wisuda_id');
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }

    public function tugasAkhir(): BelongsTo
    {
        return $this->belongsTo(TugasAkhir::class);
    }

    /**
     * Predikat kelulusan dari IPK, menurut tabel Predikat (null bila IPK tidak masuk rentang mana pun).
     */
    public static function predikat(float $ipk): ?string
    {
        return Predikat::untuk($ipk);
    }

    /**
     * Terbitkan SKL: bekukan IPK, total SKS, predikat, dan tanggal lulus (tanggal pendadaran),
     * lalu ubah status mahasiswa menjadi Lulus.
     */
    public function terbitkanSkl(int $oleh): void
    {
        DB::transaction(function () use ($oleh): void {
            $terbaik = Transkrip::terbaik(Transkrip::krs($this->mahasiswa_id));
            $sks = $terbaik->sum(fn (Krs $k): int => $k->kelasKuliah->mataKuliah->sks);
            $mutu = $terbaik->sum(fn (Krs $k): float => $k->kelasKuliah->mataKuliah->sks * $k->bobotNilai());
            $ipk = $sks > 0 ? round($mutu / $sks, 2) : 0.0;
            $pendadaran = Pendadaran::query()->where('tugas_akhir_id', $this->tugas_akhir_id)->where('status', Pendadaran::SELESAI)->latest('id')->first();

            $this->update([
                'nomor_skl' => self::nomorSklBaru(now()),
                'skl_terbit_at' => now(),
                'tanggal_lulus' => $pendadaran?->tanggal ?? today(),
                'ipk' => $ipk,
                'total_sks' => $sks,
                'predikat' => self::predikat($ipk),
                'skl_oleh' => $oleh,
            ]);
            $this->mahasiswa->update(['status' => 'Lulus']);
        });
    }

    /**
     * Nomor SKL berurutan per tahun, mis. 012/SKL/X/2026.
     */
    public static function nomorSklBaru(Carbon $tanggal): string
    {
        $romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$tanggal->month - 1];
        $urut = static::query()->where('nomor_skl', 'like', "%/{$tanggal->year}")->count() + 1;

        return sprintf('%03d/SKL/%s/%d', $urut, $romawi, $tanggal->year);
    }
}
