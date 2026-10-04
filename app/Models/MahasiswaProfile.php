<?php

namespace App\Models;

use App\Models\Concerns\DibatasiProdi;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class MahasiswaProfile extends Model
{
    use DibatasiProdi, SerializesDatesInAppTimezone;

    public const STATUS = ['Aktif', 'Pindahan', 'Nonaktif', 'Lulus', 'Dropout', 'Cuti', 'Mengundurkan Diri', 'Meninggal'];

    /** Status yang diperlakukan sebagai mahasiswa aktif (KRS, tagihan, cuti, statistik); Pindahan hanya penanda asal masuk. */
    public const STATUS_AKTIF = ['Aktif', 'Pindahan'];

    /**
     * Status yang tetap boleh masuk. Lulus dan Cuti hanya melihat data (tanpa tagihan dan KRS, lihat
     * Krs::STATUS_MAHASISWA_BOLEH_KRS); status lain ditolak saat masuk.
     */
    public const STATUS_BOLEH_MASUK = ['Aktif', 'Pindahan', 'Lulus', 'Cuti'];

    /** Berkas mahasiswa di disk privat; path tidak dikirim ke browser. */
    public const BERKAS = ['berkas_ijazah' => 'Ijazah', 'berkas_transkrip' => 'Transkrip Nilai'];

    protected $fillable = ['user_id', 'foto', 'nim', 'angkatan', 'status', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama', 'no_telepon', 'alamat', 'kewarganegaraan', 'dosen_wali_id', 'prodi_id', 'sekolah_asal', 'nisn', 'email_alternatif', 'nama_ayah_kandung', 'nama_ibu_kandung', 'tanggal_lahir_ayah', 'tanggal_lahir_ibu', 'pendidikan_terakhir_ayah', 'pendidikan_terakhir_ibu', 'pekerjaan_ayah', 'pekerjaan_ibu', 'penghasilan_ayah', 'penghasilan_ibu', 'no_telepon_ayah', 'no_telepon_ibu', 'email_ayah', 'email_ibu', 'alamat_ayah', 'alamat_ibu',
        'semester_masuk', 'tahun_akademik_masuk_id', 'cmb_id', 'nik', 'npwp', 'status_sipil', 'telepon_wali', 'dusun', 'rt', 'rw', 'kelurahan', 'wilayah_kecamatan_id', 'kode_pos',
        'alat_transportasi', 'jenis_tinggal', 'jenis_masuk', 'penerima_kps', 'nomor_kps', 'jenis_pembiayaan', 'jumlah_pembiayaan',
        'jalur_kelas', 'nilai_un', 'asal_perguruan_tinggi', 'jenjang_asal', 'prodi_asal', 'nim_asal', 'sks_diakui',
        'berkas_ijazah', 'berkas_transkrip'];

    protected $hidden = ['berkas_ijazah', 'berkas_transkrip'];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date', 'tanggal_lahir_ayah' => 'date', 'tanggal_lahir_ibu' => 'date',
            'penerima_kps' => 'boolean', 'alat_transportasi' => 'integer', 'jenis_tinggal' => 'integer', 'jenis_masuk' => 'integer',
            'jenis_pembiayaan' => 'integer', 'jumlah_pembiayaan' => 'integer', 'sks_diakui' => 'integer', 'semester_masuk' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dosenWali(): BelongsTo
    {
        return $this->belongsTo(DosenProfile::class, 'dosen_wali_id');
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'prodi_id');
    }

    /** SKS dari perguruan tinggi asal (pindahan) yang diakui; ikut dihitung di syarat SKS dan SKS lulus. */
    public function sksDiakui(): int
    {
        return (int) ($this->sks_diakui ?? 0);
    }

    /** Syarat SKS pendadaran: SKS Lulus prodi bila diisi, selain itu pengaturan akademik global. */
    public function minSksPendadaran(): int
    {
        return $this->prodi?->sks_lulus ?? PengaturanAkademik::current()->min_sks_pendadaran;
    }

    public function isAktif(): bool
    {
        return in_array($this->status, self::STATUS_AKTIF, true);
    }

    /** @param  Builder<self>  $query */
    public function scopeAktif(Builder $query): void
    {
        $query->whereIn('status', self::STATUS_AKTIF);
    }

    public function tahunAkademikMasuk(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_masuk_id');
    }

    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(WilayahKecamatan::class, 'wilayah_kecamatan_id');
    }

    public function cmb(): BelongsTo
    {
        return $this->belongsTo(Cmb::class);
    }

    public function pengajuanAkademik(): HasMany
    {
        return $this->hasMany(PengajuanAkademik::class, 'mahasiswa_id');
    }

    public function krs(): HasMany
    {
        return $this->hasMany(Krs::class, 'mahasiswa_id');
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class, 'mahasiswa_id');
    }

    /**
     * Semester mahasiswa pada tahun akademik tertentu, dihitung dari angkatan: angkatan 2024 berada di
     * semester 1 pada 2024/2025 Ganjil, semester 2 pada Genap, dan seterusnya. Semester tetap bertambah
     * selama mahasiswa cuti. Null bila tahun akademik tidak ada atau mahasiswa belum mulai kuliah.
     */
    public function semesterPada(?TahunAkademik $tahunAkademik): ?int
    {
        if ($tahunAkademik === null) {
            return null;
        }

        // Semester masuk yang diisi admin (mis. mahasiswa pindahan) berlaku pada tahun akademik saat diisi.
        if ($this->semester_masuk !== null && $this->tahunAkademikMasuk !== null) {
            $selisih = self::urutanSemester($tahunAkademik) - self::urutanSemester($this->tahunAkademikMasuk);
            $semester = $selisih === null ? null : $this->semester_masuk + $selisih;

            return $semester !== null && $semester >= 1 ? $semester : null;
        }

        $tahunAwal = $tahunAkademik->tahunAwal();

        if ($tahunAwal === null || $this->angkatan === null) {
            return null;
        }

        $semester = ($tahunAwal - (int) $this->angkatan) * 2 + ($tahunAkademik->semester === 'Genap' ? 2 : 1);

        return $semester >= 1 ? $semester : null;
    }

    /** Nomor urut semester sepanjang waktu (Ganjil 2026/2027 → 4052, Genap → 4053) untuk menghitung selisih. */
    private static function urutanSemester(TahunAkademik $tahunAkademik): ?int
    {
        $tahunAwal = $tahunAkademik->tahunAwal();

        return $tahunAwal === null ? null : $tahunAwal * 2 + ($tahunAkademik->semester === 'Genap' ? 1 : 0);
    }

    /**
     * Pesan galat bila semester masuk tidak sepadan dengan semester aktif (Ganjil → 1, 3, 5…; Genap → 2, 4, 6…),
     * sehingga mahasiswa bisa langsung kuliah di semester yang sedang berjalan.
     */
    public static function galatSemesterMasuk(int $semester, TahunAkademik $aktif): ?string
    {
        $ganjil = $aktif->semester !== 'Genap';
        if (($semester % 2 === 1) === $ganjil) {
            return null;
        }

        return $semester > 1
            ? "Semester {$semester} tidak sesuai semester {$aktif->semester} yang aktif ({$aktif->tahun}). Turunkan satu semester menjadi semester ".($semester - 1).' agar mahasiswa bisa kuliah di semester aktif.'
            : "Semester 1 hanya bisa dimulai di semester Ganjil; semester aktif {$aktif->tahun} adalah {$aktif->semester}.";
    }

    /**
     * Muat relasi yang dipakai blok tanda tangan dokumen PDF mahasiswa (pdf/partials/pengesahan-mahasiswa):
     * Dosen Pembimbing Akademik dan Ketua Program Studi beserta NIDN-nya.
     */
    public function muatPengesahan(): static
    {
        return $this->loadMissing([
            'user:id,name',
            'dosenWali:id,user_id,nidn',
            'dosenWali.user:id,name',
            'prodi:id,fakultas_id,nama_prodi,jenjang,kaprodi',
            'prodi.fakultas:id,nama_fakultas',
            'prodi.ketuaProgramStudi:id,user_id,nidn',
            'prodi.ketuaProgramStudi.user:id,name',
        ]);
    }

    public function krsSemester(): HasMany
    {
        return $this->hasMany(KrsSemester::class, 'mahasiswa_id');
    }

    public function tagihan(): HasMany
    {
        return $this->hasMany(TagihanSemester::class, 'mahasiswa_id');
    }

    public function pengajuanPindahKelas(): HasMany
    {
        return $this->hasMany(PengajuanPindahKelas::class, 'mahasiswa_id');
    }

    /**
     * IPS pada semester terakhir yang diambil mahasiswa sebelum tahun akademik tertentu.
     *
     * Bernilai null bila mahasiswa belum pernah mengambil kelas, atau bila nilai semester itu belum
     * lengkap — batas SKS lalu memakai angka "tanpa IPS" ketimbang IPS dari sebagian nilai saja.
     * TA/Skripsi yang belum dinilai (berlanjut) tidak dihitung; semester yang isinya hanya TA berlanjut
     * dilewati sehingga yang dipakai IPS semester sebelumnya.
     *
     * @param  Collection<int, Krs>|null  $krsTerpakai  KRS yang sudah dimuat, agar halaman KRS tidak query ulang.
     * @return array{tahun_akademik: TahunAkademik, ips: float}|null
     */
    public function ipsSemesterSebelum(?TahunAkademik $tahunAkademik, ?Collection $krsTerpakai = null): ?array
    {
        $krs = $krsTerpakai !== null
            ? $krsTerpakai->filter(fn (Krs $item): bool => $tahunAkademik?->tanggal_mulai === null
                || ($item->kelasKuliah?->tahunAkademik?->tanggal_mulai !== null
                    && $item->kelasKuliah->tahunAkademik->tanggal_mulai < $tahunAkademik->tanggal_mulai))
            : $this->krs()
                ->whereHas('kelasKuliah.tahunAkademik', fn ($query) => $query
                    ->when($tahunAkademik?->tanggal_mulai, fn ($query, $mulai) => $query->where('tanggal_mulai', '<', $mulai)))
                ->with('kelasKuliah.tahunAkademik', 'kelasKuliah.mataKuliah:id,prodi_id,sks,tugas_akhir')
                ->get();

        $krs = $krs->filter(fn (Krs $item): bool => $item->kelasKuliah?->tahunAkademik !== null && ! $item->taBelumDinilai());

        $semesterTerakhir = $krs
            ->groupBy(fn (Krs $item): int => $item->kelasKuliah->tahun_akademik_id)
            ->sortByDesc(fn ($rows) => $rows->first()->kelasKuliah->tahunAkademik->tanggal_mulai)
            ->first();

        if ($semesterTerakhir === null) {
            return null;
        }

        // Selama masih ada nilai yang belum masuk, IPS semester itu belum bisa dipakai.
        if ($semesterTerakhir->contains(fn (Krs $item): bool => $item->bobotNilai() === null)) {
            return null;
        }

        $dihitung = $semesterTerakhir->filter(fn (Krs $item): bool => ($item->kelasKuliah->mataKuliah?->sks ?? 0) > 0);
        $sks = $dihitung->sum(fn (Krs $item): int => $item->kelasKuliah->mataKuliah->sks);

        if ($sks === 0) {
            return null;
        }

        $mutu = $dihitung->sum(fn (Krs $item): float => $item->kelasKuliah->mataKuliah->sks * $item->bobotNilai());

        return ['tahun_akademik' => $semesterTerakhir->first()->kelasKuliah->tahunAkademik, 'ips' => round($mutu / $sks, 2)];
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
