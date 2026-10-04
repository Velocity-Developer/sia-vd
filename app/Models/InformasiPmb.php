<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;

/**
 * Isi halaman publik Informasi PMB (/pmb) yang dikelola admin di Mahasiswa Baru → Konfigurasi → Informasi PMB.
 */
class InformasiPmb extends Model
{
    use SerializesDatesInAppTimezone;

    public const SINGLETON_ID = 1;

    /** Bagian isi halaman: kolom => judul bagian. */
    public const BAGIAN = [
        'syarat' => 'Syarat Pendaftaran',
        'jadwal_tes' => 'Jadwal Tes Seleksi',
        'biaya' => 'Biaya',
        'kontak' => 'Kontak Panitia PMB',
    ];

    // Kolom id bukan auto-increment (lihat PengaturanAkademik).
    public $incrementing = false;

    protected $table = 'informasi_pmb';

    protected $fillable = ['judul', 'pengantar', 'syarat', 'jadwal_tes', 'biaya', 'kontak', 'updated_by'];

    protected $attributes = [
        'id' => self::SINGLETON_ID,
        'judul' => 'Penerimaan Mahasiswa Baru',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => self::SINGLETON_ID]);
    }
}
