<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

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
     * Dosen yang boleh dipilih di isian form: berstatus Aktif, ditambah dosen yang sudah terpilih
     * pada data yang sedang diubah agar isian lama tetap tampil.
     *
     * @param  Builder<self>  $query
     * @param  int|list<int|null>|null  $termasuk
     */
    public function scopePilihan(Builder $query, int|array|null $termasuk = null): void
    {
        $query->where(fn (Builder $query) => $query->where('status', 'Aktif')->orWhereIn('id', array_filter((array) $termasuk)));
    }

    /**
     * Aturan validasi pasangan scopePilihan: dosen Aktif atau yang sudah terpilih sebelumnya.
     *
     * @param  int|list<int|null>|null  $termasuk
     */
    public static function rulePilihan(int|array|null $termasuk = null): Exists
    {
        return Rule::exists('dosen_profiles', 'id')->where(fn ($query) => $query->where('status', 'Aktif')->orWhereIn('id', array_filter((array) $termasuk)));
    }

    /**
     * Pilihan dosen untuk isian form (pembimbing, penguji), urut nama.
     *
     * @param  int|list<int|null>|null  $termasuk
     * @return list<array{id: int, name: string}>
     */
    public static function opsi(int|array|null $termasuk = null): array
    {
        return static::query()->pilihan($termasuk)->with('user:id,name')->get(['id', 'user_id'])
            ->map(fn (self $dosen): array => ['id' => $dosen->id, 'name' => (string) $dosen->user?->name])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    }
}
