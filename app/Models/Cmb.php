<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

// Pendaftar PMB (calon mahasiswa baru).
class Cmb extends Model
{
    use SerializesDatesInAppTimezone;

    public const STATUS_LULUS = 'lulus';

    public const STATUS_DITOLAK = 'ditolak';

    /**
     * Berkas unggahan pendaftar: kolom => label. Path disimpan server, tidak diisi dari formulir.
     *
     * @var array<string, string>
     */
    public const BERKAS = [
        'foto' => 'Pas Foto',
        'berkas_ijazah' => 'Ijazah',
        'berkas_transkrip' => 'Transkrip Nilai',
    ];

    protected $table = 'cmb';

    protected $guarded = ['id', 'pengaturan_pmb_id', 'nomor_pendaftaran', 'nilai', 'status_pendaftaran', 'foto', 'berkas_ijazah', 'berkas_transkrip'];

    // Path disk tidak dikirim ke browser; tampilannya lewat daftarBerkas().
    protected $hidden = ['foto', 'berkas_ijazah', 'berkas_transkrip'];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date:Y-m-d',
            'penerima_kps' => 'boolean',
            'alat_transportasi' => 'integer',
            'jenis_tinggal' => 'integer',
            'jenis_masuk' => 'integer',
            'jenis_pembiayaan' => 'integer',
            'jumlah_pembiayaan' => 'integer',
            'sks_diakui' => 'integer',
            'nilai' => 'float',
        ];
    }

    /**
     * Berkas yang terunggah beserta URL unduhannya (untuk halaman admin).
     *
     * @return list<array{jenis: string, label: string, url: string, gambar: bool}>
     */
    public function daftarBerkas(): array
    {
        return collect(self::BERKAS)
            ->filter(fn (string $label, string $kolom): bool => filled($this->getAttribute($kolom)))
            ->map(fn (string $label, string $kolom): array => [
                'jenis' => $kolom,
                'label' => $label,
                'url' => route('berkas.pmb', ['cmb' => $this->id, 'jenis' => $kolom]),
                'gambar' => in_array(strtolower(pathinfo($this->getAttribute($kolom), PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true),
            ])->values()->all();
    }

    /** Data mahasiswa hasil salinan pendaftar ini (lihat SalinCalonMaba). */
    public function mahasiswa(): HasOne
    {
        return $this->hasOne(MahasiswaProfile::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(PengaturanPmb::class, 'pengaturan_pmb_id');
    }

    public function agama(): BelongsTo
    {
        return $this->belongsTo(Agama::class);
    }

    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(WilayahKecamatan::class, 'wilayah_kecamatan_id');
    }

    public function programStudi(): BelongsTo
    {
        return $this->belongsTo(ProgramStudi::class);
    }
}
