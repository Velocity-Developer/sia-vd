<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramStudi extends Model
{
    use SerializesDatesInAppTimezone;

    protected $fillable = ['fakultas_id', 'kode_prodi', 'nama_prodi', 'jenjang', 'status_akreditasi', 'no_sk_akreditasi', 'tanggal_akreditasi_mulai', 'tanggal_akreditasi_akhir', 'kaprodi', 'tahun_berdiri'];

    protected function casts(): array
    {
        return ['tanggal_akreditasi_mulai' => 'date', 'tanggal_akreditasi_akhir' => 'date'];
    }

    public function fakultas(): BelongsTo
    {
        return $this->belongsTo(Fakultas::class);
    }

    public function ketuaProgramStudi(): BelongsTo
    {
        return $this->belongsTo(DosenProfile::class, 'kaprodi');
    }

    public function mataKuliah(): HasMany
    {
        return $this->hasMany(MataKuliah::class, 'prodi_id');
    }
}
