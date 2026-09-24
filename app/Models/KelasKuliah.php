<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class KelasKuliah extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'kelas_kuliah';

    protected $fillable = ['kode_kelas', 'tahun_akademik_id', 'kapasitas', 'jumlah_pertemuan', 'dosen_id', 'matkul_id'];

    /**
     * Kelas baru tanpa jumlah pertemuan memakai bawaan di Pengaturan Akademik.
     */
    protected static function booted(): void
    {
        static::creating(function (self $kelas): void {
            $kelas->jumlah_pertemuan ??= PengaturanAkademik::current()->jumlah_pertemuan;
        });
    }

    protected function casts(): array
    {
        return [
            'kapasitas' => 'integer',
            'jumlah_pertemuan' => 'integer',
        ];
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_id');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(DosenProfile::class, 'dosen_id');
    }

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'matkul_id');
    }

    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'kelas_id');
    }

    public function materis(): HasMany
    {
        return $this->hasMany(Materi::class, 'kelas_id');
    }

    public function tugas(): HasMany
    {
        return $this->hasMany(Tugas::class, 'kelas_id');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class, 'kelas_id');
    }

    public function krs(): HasMany
    {
        return $this->hasMany(Krs::class, 'kelas_id');
    }

    public function pertemuans(): HasMany
    {
        return $this->hasMany(Pertemuan::class, 'kelas_id');
    }

    /**
     * Ubah jumlah pertemuan kelas. Pertemuan di atas jumlah baru ikut dihapus, kecuali yang sudah berjalan
     * atau sudah punya presensi.
     */
    public function ubahJumlahPertemuan(int $jumlah, string $field = 'jumlah_pertemuan'): void
    {
        $dibuang = $this->pertemuans()->where('pertemuan_ke', '>', $jumlah);

        $terpakai = (clone $dibuang)
            ->where(fn ($query) => $query->whereIn('status', [Pertemuan::BERLANGSUNG, Pertemuan::SELESAI])->orWhereHas('presensiMahasiswas'))
            ->orderByDesc('pertemuan_ke')
            ->value('pertemuan_ke');

        if ($terpakai !== null) {
            throw ValidationException::withMessages([
                $field => "Pertemuan ke-{$terpakai} sudah berjalan, jadi jumlah pertemuan minimal {$terpakai}.",
            ]);
        }

        $dibuang->delete();
        $this->update(['jumlah_pertemuan' => $jumlah]);
    }

    public function dispensasiUjians(): HasMany
    {
        return $this->hasMany(DispensasiUjian::class, 'kelas_id');
    }
}
