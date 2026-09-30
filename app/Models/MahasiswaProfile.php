<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class MahasiswaProfile extends Model
{
    use SerializesDatesInAppTimezone;

    /**
     * Status yang tetap boleh masuk. Lulus dan Cuti hanya melihat data (tanpa tagihan dan KRS, lihat
     * Krs::STATUS_MAHASISWA_BOLEH_KRS); status lain ditolak saat masuk.
     */
    public const STATUS_BOLEH_MASUK = ['Aktif', 'Lulus', 'Cuti'];

    protected $fillable = ['user_id', 'nim', 'angkatan', 'status', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama', 'no_telepon', 'alamat', 'kewarganegaraan', 'dosen_wali_id', 'prodi_id', 'sekolah_asal', 'nisn', 'email_alternatif', 'nama_ayah_kandung', 'nama_ibu_kandung', 'tanggal_lahir_ayah', 'tanggal_lahir_ibu', 'pendidikan_terakhir_ayah', 'pendidikan_terakhir_ibu', 'pekerjaan_ayah', 'pekerjaan_ibu', 'penghasilan_ayah', 'penghasilan_ibu', 'no_telepon_ayah', 'no_telepon_ibu', 'email_ayah', 'email_ibu', 'alamat_ayah', 'alamat_ibu'];

    protected function casts(): array
    {
        return ['tanggal_lahir' => 'date', 'tanggal_lahir_ayah' => 'date', 'tanggal_lahir_ibu' => 'date'];
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
        $tahunAwal = $tahunAkademik?->tahunAwal();

        if ($tahunAwal === null || $this->angkatan === null) {
            return null;
        }

        $semester = ($tahunAwal - (int) $this->angkatan) * 2 + ($tahunAkademik->semester === 'Genap' ? 2 : 1);

        return $semester >= 1 ? $semester : null;
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
                ->with('kelasKuliah.tahunAkademik', 'kelasKuliah.mataKuliah:id,sks,tugas_akhir')
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
        if ($semesterTerakhir->contains(fn (Krs $item): bool => SkalaNilai::bobot($item->nilai) === null)) {
            return null;
        }

        $dihitung = $semesterTerakhir->filter(fn (Krs $item): bool => ($item->kelasKuliah->mataKuliah?->sks ?? 0) > 0);
        $sks = $dihitung->sum(fn (Krs $item): int => $item->kelasKuliah->mataKuliah->sks);

        if ($sks === 0) {
            return null;
        }

        $mutu = $dihitung->sum(fn (Krs $item): float => $item->kelasKuliah->mataKuliah->sks * SkalaNilai::bobot($item->nilai));

        return ['tahun_akademik' => $semesterTerakhir->first()->kelasKuliah->tahunAkademik, 'ips' => round($mutu / $sks, 2)];
    }
}
