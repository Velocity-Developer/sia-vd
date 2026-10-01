<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Status KRS satu mahasiswa di satu semester. Tanpa baris = KRS belum disimpan.
 *
 * Saat verifikasi KRS aktif, KRS yang disimpan berstatus diajukan lalu disetujui admin, atau dikembalikan
 * (perlu_revisi) dan bisa diubah lagi sampai masa revisi berakhir. Tanpa verifikasi, KRS yang disimpan
 * langsung disetujui. Buka kunci oleh admin juga memakai status perlu_revisi, bisa dengan batas tanggal sendiri.
 */
class KrsSemester extends Model
{
    use SerializesDatesInAppTimezone;

    public const DIAJUKAN = 'diajukan';

    public const PERLU_REVISI = 'perlu_revisi';

    public const DISETUJUI = 'disetujui';

    public const STATUS = [self::DIAJUKAN, self::PERLU_REVISI, self::DISETUJUI];

    /** Status yang membuat mahasiswa tidak bisa mengubah KRS-nya sendiri. */
    public const STATUS_TERKUNCI = [self::DIAJUKAN, self::DISETUJUI];

    /** Isian form buka kunci / minta revisi oleh admin. */
    public const ATURAN_BUKA_KUNCI = [
        'catatan' => ['nullable', 'string', 'max:1000'],
        'dibuka_sampai' => ['nullable', 'date', 'after_or_equal:today'],
    ];

    public const ATRIBUT_BUKA_KUNCI = ['catatan' => 'Catatan', 'dibuka_sampai' => 'Dibuka sampai'];

    protected $table = 'krs_semester';

    protected $fillable = ['mahasiswa_id', 'tahun_akademik_id', 'status', 'disimpan_pada', 'catatan_revisi', 'dibuka_sampai', 'diverifikasi_oleh', 'diverifikasi_pada'];

    protected function casts(): array
    {
        return [
            'disimpan_pada' => 'datetime',
            'dibuka_sampai' => 'date:Y-m-d',
            'diverifikasi_pada' => 'datetime',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaProfile::class, 'mahasiswa_id');
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public static function untuk(int $mahasiswaId, int $tahunAkademikId): ?self
    {
        return static::query()->where('mahasiswa_id', $mahasiswaId)->where('tahun_akademik_id', $tahunAkademikId)->first();
    }

    public static function verifikasiAktif(): bool
    {
        return PengaturanAkademik::current()->verifikasi_krs_aktif;
    }

    /**
     * Akhir masa revisi KRS: tanggal revisi semester bila verifikasi aktif dan tanggalnya diisi, selain itu
     * akhir periode KRS. Null bila periode KRS belum diatur.
     */
    public static function batasRevisi(TahunAkademik $tahunAkademik): ?Carbon
    {
        $tanggal = static::verifikasiAktif() && $tahunAkademik->tanggal_revisi_krs_akhir !== null
            ? $tahunAkademik->tanggal_revisi_krs_akhir
            : $tahunAkademik->tanggal_krs_akhir;

        return $tanggal === null ? null : Carbon::parse($tanggal)->endOfDay();
    }

    /**
     * Alasan mahasiswa tidak bisa mengambil/membatalkan kelas atau menyimpan KRS sendiri; null = boleh.
     */
    public static function alasanTidakBolehUbah(int $mahasiswaId, ?TahunAkademik $tahunAkademik, ?self $kunci = null): ?string
    {
        if ($tahunAkademik?->status !== true) {
            return 'Periode pengambilan KRS belum dibuka atau sudah berakhir.';
        }

        $kunci ??= static::untuk($mahasiswaId, $tahunAkademik->id);

        if ($kunci === null) {
            return $tahunAkademik->periodeKrsAktif() ? null : 'Periode pengambilan KRS belum dibuka atau sudah berakhir.';
        }

        if ($kunci->status === self::DIAJUKAN) {
            return 'KRS Anda sudah diajukan dan sedang menunggu verifikasi. Hubungi admin bila perlu mengubahnya.';
        }

        if ($kunci->status === self::DISETUJUI) {
            return 'KRS Anda sudah disimpan dan terkunci. Hubungi admin bila perlu membukanya.';
        }

        $kunci->setRelation('tahunAkademik', $tahunAkademik);

        return $kunci->bisaDirevisi() ? null : 'Masa revisi KRS sudah berakhir. Hubungi admin atau prodi bila masih perlu mengubah KRS.';
    }

    public static function bolehDiubah(int $mahasiswaId, ?TahunAkademik $tahunAkademik, ?self $kunci = null): bool
    {
        return static::alasanTidakBolehUbah($mahasiswaId, $tahunAkademik, $kunci) === null;
    }

    /**
     * KRS berstatus perlu_revisi masih bisa diubah: sampai tanggal buka kunci khusus, atau sejak periode
     * KRS dibuka sampai akhir masa revisi (mana yang lebih lama).
     */
    public function bisaDirevisi(): bool
    {
        if ($this->status !== self::PERLU_REVISI) {
            return false;
        }

        if ($this->dibuka_sampai !== null && Carbon::today()->lte($this->dibuka_sampai)) {
            return true;
        }

        return $this->tahunAkademik !== null && static::masaRevisiBerjalan($this->tahunAkademik);
    }

    /**
     * Sejak periode KRS dibuka sampai akhir masa revisi.
     */
    public static function masaRevisiBerjalan(TahunAkademik $tahunAkademik): bool
    {
        $batas = static::batasRevisi($tahunAkademik);

        return $batas !== null && $tahunAkademik->tanggal_krs_awal !== null
            && now()->between(Carbon::parse($tahunAkademik->tanggal_krs_awal)->startOfDay(), $batas);
    }

    /**
     * Simpan KRS mahasiswa: diajukan bila verifikasi aktif, langsung disetujui bila tidak.
     */
    public static function simpan(int $mahasiswaId, int $tahunAkademikId): self
    {
        return static::query()->updateOrCreate(
            ['mahasiswa_id' => $mahasiswaId, 'tahun_akademik_id' => $tahunAkademikId],
            [
                'status' => static::verifikasiAktif() ? self::DIAJUKAN : self::DISETUJUI,
                'disimpan_pada' => now(),
                'dibuka_sampai' => null,
                'diverifikasi_oleh' => null,
                'diverifikasi_pada' => null,
            ],
        );
    }

    /**
     * Kembalikan KRS ke mahasiswa agar bisa diubah lagi (minta revisi atau buka kunci). Tanpa tanggal, KRS
     * terbuka sampai akhir masa revisi semester. Baris dibuat bila mahasiswa belum pernah menyimpan KRS.
     */
    public static function kembalikan(int $mahasiswaId, int $tahunAkademikId, ?string $catatan, ?string $dibukaSampai, ?int $olehUserId): self
    {
        return static::query()->updateOrCreate(
            ['mahasiswa_id' => $mahasiswaId, 'tahun_akademik_id' => $tahunAkademikId],
            [
                'status' => self::PERLU_REVISI,
                'catatan_revisi' => $catatan,
                'dibuka_sampai' => $dibukaSampai,
                'diverifikasi_oleh' => $olehUserId,
                'diverifikasi_pada' => now(),
            ],
        );
    }

    /**
     * Buka kunci KRS satu mahasiswa oleh admin (dari Detail Mahasiswa, Tagihan, atau Verifikasi KRS).
     * Setelah masa revisi berakhir, tanggal batas buka kunci wajib diisi. Mengembalikan pesan galat, null bila berhasil.
     */
    public static function bukaKunci(int $mahasiswaId, TahunAkademik $tahunAkademik, ?string $catatan, ?string $dibukaSampai, ?int $olehUserId): ?string
    {
        if ($dibukaSampai === null && ! static::masaRevisiBerjalan($tahunAkademik)) {
            return 'Masa KRS dan revisi semester ini tidak sedang berjalan. Isi tanggal "Dibuka sampai" agar mahasiswa bisa mengubah KRS.';
        }

        if ($dibukaSampai === null && static::bolehDiubah($mahasiswaId, $tahunAkademik) && $catatan === null) {
            return 'KRS mahasiswa ini memang belum dikunci.';
        }

        static::kembalikan($mahasiswaId, $tahunAkademik->id, $catatan, $dibukaSampai, $olehUserId);

        return null;
    }

    /**
     * Kalimat batas perubahan untuk pesan sukses buka kunci / minta revisi.
     */
    public static function pesanDibuka(?string $dibukaSampai, TahunAkademik $tahunAkademik): string
    {
        $batas = $dibukaSampai !== null ? Carbon::parse($dibukaSampai) : static::batasRevisi($tahunAkademik);

        return 'Mahasiswa bisa mengubah KRS sampai '.$batas?->translatedFormat('d F Y').'.';
    }

    public function setujui(?int $olehUserId): void
    {
        $this->update(['status' => self::DISETUJUI, 'dibuka_sampai' => null, 'diverifikasi_oleh' => $olehUserId, 'diverifikasi_pada' => now()]);
    }

    /**
     * KRS sudah disimpan dan sedang terkunci (diajukan atau disetujui).
     */
    public static function tersimpan(int $mahasiswaId, int $tahunAkademikId): bool
    {
        return static::query()
            ->where('mahasiswa_id', $mahasiswaId)
            ->where('tahun_akademik_id', $tahunAkademikId)
            ->whereIn('status', self::STATUS_TERKUNCI)
            ->exists();
    }

    public static function disetujui(int $mahasiswaId, int $tahunAkademikId): bool
    {
        return static::query()
            ->where('mahasiswa_id', $mahasiswaId)
            ->where('tahun_akademik_id', $tahunAkademikId)
            ->where('status', self::DISETUJUI)
            ->exists();
    }

    /**
     * Ringkasan status untuk halaman mahasiswa dan admin.
     *
     * @return array<string, mixed>
     */
    public function ringkasan(): array
    {
        $batas = $this->tahunAkademik === null ? null : static::batasRevisi($this->tahunAkademik);
        $dibuka = $this->dibuka_sampai?->copy()->endOfDay();

        return [
            'status' => $this->status,
            'disimpan_pada' => $this->disimpan_pada?->toIso8601String(),
            'catatan_revisi' => $this->catatan_revisi,
            'batas_revisi' => ($dibuka !== null && ($batas === null || $dibuka->gt($batas)) ? $dibuka : $batas)?->toDateString(),
            'bisa_direvisi' => $this->bisaDirevisi(),
            'diverifikasi_oleh' => $this->verifikator?->name,
            'diverifikasi_pada' => $this->diverifikasi_pada?->toIso8601String(),
        ];
    }
}
