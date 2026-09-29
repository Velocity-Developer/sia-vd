<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DosenProfile extends Model
{
    use SerializesDatesInAppTimezone;

    protected $fillable = ['user_id', 'nidn', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama', 'no_telepon', 'alamat', 'kewarganegaraan', 'jabatan_fungsional', 'pendidikan_terakhir', 'status_kepegawaian', 'status', 'prodi_id'];

    public const STATUS = ['Aktif', 'Nonaktif'];

    protected function casts(): array
    {
        return ['tanggal_lahir' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prodi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class, 'prodi_id');
    }

    public function mahasiswaWali(): HasMany
    {
        return $this->hasMany(MahasiswaProfile::class, 'dosen_wali_id');
    }

    public function kelasKuliah(): HasMany
    {
        return $this->hasMany(KelasKuliah::class, 'dosen_id');
    }

    /**
     * Program studi yang dipimpin dosen ini sebagai kaprodi.
     */
    public function prodiDipimpin(): HasMany
    {
        return $this->hasMany(ProgramStudi::class, 'kaprodi');
    }

    /**
     * Pilihan dosen untuk isian form (pembimbing, penguji), urut nama.
     *
     * @return list<array{id: int, name: string}>
     */
    public static function opsi(): array
    {
        return static::query()->with('user:id,name')->get(['id', 'user_id'])
            ->map(fn (self $dosen): array => ['id' => $dosen->id, 'name' => (string) $dosen->user?->name])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }
}
