<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MahasiswaProfile extends Model
{
    use SerializesDatesInAppTimezone;

    protected $fillable = ['user_id', 'nim', 'angkatan', 'semester', 'status', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama', 'no_telepon', 'alamat', 'kewarganegaraan', 'dosen_wali_id', 'prodi_id', 'sekolah_asal', 'nisn', 'email_alternatif', 'nama_ayah_kandung', 'nama_ibu_kandung', 'tanggal_lahir_ayah', 'tanggal_lahir_ibu', 'pendidikan_terakhir_ayah', 'pendidikan_terakhir_ibu', 'pekerjaan_ayah', 'pekerjaan_ibu', 'penghasilan_ayah', 'penghasilan_ibu', 'no_telepon_ayah', 'no_telepon_ibu', 'email_ayah', 'email_ibu', 'alamat_ayah', 'alamat_ibu'];

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

    public function pengajuanPindahKelas(): HasMany
    {
        return $this->hasMany(PengajuanPindahKelas::class, 'mahasiswa_id');
    }

    /**
     * IPS pada semester terakhir yang sudah bernilai sebelum tahun akademik tertentu.
     *
     * @return array{tahun_akademik: TahunAkademik, ips: float}|null
     */
    public function ipsSemesterSebelum(?TahunAkademik $tahunAkademik): ?array
    {
        $krsDinilai = $this->krs()
            ->whereNotNull('nilai')
            ->whereHas('kelasKuliah.tahunAkademik', fn ($query) => $query
                ->when($tahunAkademik?->tanggal_mulai, fn ($query, $mulai) => $query->where('tanggal_mulai', '<', $mulai)))
            ->with('kelasKuliah.tahunAkademik', 'kelasKuliah.mataKuliah:id,sks')
            ->get()
            ->filter(fn (Krs $krs): bool => SkalaNilai::bobot($krs->nilai) !== null && ($krs->kelasKuliah->mataKuliah?->sks ?? 0) > 0);

        $terakhir = $krsDinilai
            ->groupBy(fn (Krs $krs): int => $krs->kelasKuliah->tahun_akademik_id)
            ->sortByDesc(fn ($krs) => $krs->first()->kelasKuliah->tahunAkademik->tanggal_mulai)
            ->first();

        if ($terakhir === null) {
            return null;
        }

        $sks = $terakhir->sum(fn (Krs $krs): int => $krs->kelasKuliah->mataKuliah->sks);
        $mutu = $terakhir->sum(fn (Krs $krs): float => $krs->kelasKuliah->mataKuliah->sks * SkalaNilai::bobot($krs->nilai));

        return ['tahun_akademik' => $terakhir->first()->kelasKuliah->tahunAkademik, 'ips' => round($mutu / $sks, 2)];
    }
}
