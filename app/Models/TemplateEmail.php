<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Isi surel yang dikirim sistem, bisa diubah admin di Pengaturan Sistem > Email.
 *
 * Isi ditulis sebagai paragraf yang dipisah baris kosong. Variabel ditulis {nama} dan diganti saat surel
 * dikirim. Baris berisi {tombol} saja menandai letak tombol tautan; bila tidak ada, tombol diletakkan
 * sesudah paragraf terakhir.
 */
class TemplateEmail extends Model
{
    use SerializesDatesInAppTimezone;

    public const PENANDA_TOMBOL = '{tombol}';

    /**
     * Jenis surel beserta variabel yang tersedia dan isi bawaannya.
     */
    public const JENIS = [
        'atur_ulang_kata_sandi' => [
            'judul' => 'Atur Ulang Kata Sandi',
            'keterangan' => 'Dikirim saat pengguna meminta tautan dari halaman "Lupa kata sandi".',
            'tombol' => true,
            'variabel' => [
                'nama' => 'Nama pengguna',
                'username' => 'Username / NIM / NIDN',
                'email' => 'Alamat email pengguna',
                'institusi' => 'Nama institusi',
                'menit' => 'Masa berlaku tautan (menit)',
                'tautan' => 'Alamat tautan atur ulang',
            ],
            'bawaan' => [
                'subjek' => 'Atur Ulang Kata Sandi — {institusi}',
                'sapaan' => 'Halo {nama},',
                'isi' => "Kami menerima permintaan untuk mengatur ulang kata sandi akun {username} di {institusi}.\n\n{tombol}\n\nTautan ini berlaku {menit} menit.\n\nJika Anda tidak merasa meminta, abaikan surel ini; kata sandi Anda tidak berubah.",
                'tombol' => 'Atur Ulang Kata Sandi',
                'penutup' => "Salam,\n{institusi}",
            ],
        ],
        'verifikasi_email' => [
            'judul' => 'Verifikasi Email',
            'keterangan' => 'Dikirim saat alamat email pengguna ditambahkan atau diubah, dan saat pengguna meminta tautan baru.',
            'tombol' => true,
            'variabel' => [
                'nama' => 'Nama pengguna',
                'username' => 'Username / NIM / NIDN',
                'email' => 'Alamat email pengguna',
                'institusi' => 'Nama institusi',
                'menit' => 'Masa berlaku tautan (menit)',
                'tautan' => 'Alamat tautan verifikasi',
            ],
            'bawaan' => [
                'subjek' => 'Verifikasi Alamat Email — {institusi}',
                'sapaan' => 'Halo {nama},',
                'isi' => "Alamat email ini didaftarkan untuk akun {username} di {institusi}.\n\nKlik tombol di bawah untuk memverifikasi alamat email Anda.\n\n{tombol}\n\nTautan ini berlaku {menit} menit. Bila sudah kedaluwarsa, masuk ke aplikasi lalu minta tautan baru.\n\nJika Anda tidak merasa memiliki akun ini, abaikan surel ini.",
                'tombol' => 'Verifikasi Email',
                'penutup' => "Salam,\n{institusi}",
            ],
        ],
        'uji' => [
            'judul' => 'Surel Uji',
            'keterangan' => 'Dikirim dari tombol "Kirim Surel Uji" untuk memastikan pengaturan pengiriman benar.',
            'tombol' => false,
            'variabel' => [
                'institusi' => 'Nama institusi',
                'email' => 'Alamat email tujuan',
            ],
            'bawaan' => [
                'subjek' => 'Uji Pengiriman Email — {institusi}',
                'sapaan' => 'Halo,',
                'isi' => "Surel ini dikirim dari halaman Pengaturan Email {institusi} untuk menguji pengiriman.\n\nJika Anda menerimanya, pengaturan pengiriman surel sudah benar dan tautan atur ulang kata sandi akan sampai ke pengguna.",
                'tombol' => null,
                'penutup' => "Salam,\n{institusi}",
            ],
        ],
    ];

    /** Contoh nilai variabel untuk pratinjau di halaman pengaturan. */
    public const CONTOH = [
        'nama' => 'Budi Santoso',
        'username' => '2401010001',
        'email' => 'budi@contoh.ac.id',
        'menit' => '60',
    ];

    protected $table = 'template_email';

    protected $primaryKey = 'jenis';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['jenis', 'subjek', 'sapaan', 'isi', 'tombol', 'penutup', 'updated_by'];

    /**
     * Isi yang berlaku untuk satu jenis surel: dari basis data bila sudah diubah, selain itu bawaan.
     *
     * @return array{subjek: string, sapaan: ?string, isi: string, tombol: ?string, penutup: ?string}
     */
    public static function isiBerlaku(string $jenis): array
    {
        $bawaan = self::JENIS[$jenis]['bawaan'];

        try {
            $tersimpan = static::query()->find($jenis);
        } catch (Throwable) {
            // Tabel belum ada (migrasi belum jalan) — pakai isi bawaan.
            $tersimpan = null;
        }

        return $tersimpan ? $tersimpan->only(array_keys($bawaan)) : $bawaan;
    }

    /**
     * Susun surel dari isi template dan nilai variabelnya.
     *
     * @param  array{subjek: string, sapaan: ?string, isi: string, tombol: ?string, penutup: ?string}  $isi
     * @param  array<string, string|int|null>  $nilai
     */
    public static function susun(string $jenis, array $isi, array $nilai, ?string $tautan = null): MailMessage
    {
        $ganti = fn (?string $teks): string => strtr((string) $teks, collect($nilai)
            ->mapWithKeys(fn ($v, $k) => ['{'.$k.'}' => (string) $v])
            ->all());

        $pakaiTombol = self::JENIS[$jenis]['tombol'] && filled($tautan);
        $sebelum = [];
        $sesudah = [];
        $sudahTombol = false;

        foreach (preg_split('/\R\s*\R/', trim($ganti($isi['isi']))) as $paragraf) {
            $paragraf = trim($paragraf);
            if ($paragraf === self::PENANDA_TOMBOL) {
                $sudahTombol = true;

                continue;
            }
            if ($paragraf === '') {
                continue;
            }
            // Penanda tombol di tengah kalimat tidak bermakna, dibuang saja.
            $paragraf = trim(str_replace(self::PENANDA_TOMBOL, '', $paragraf));
            $sudahTombol ? $sesudah[] = $paragraf : $sebelum[] = $paragraf;
        }

        // Tanpa penanda, tombol diletakkan sesudah paragraf terakhir.
        if (! $sudahTombol) {
            [$sebelum, $sesudah] = [[...$sebelum, ...$sesudah], []];
        }

        $institusi = (string) ($nilai['institusi'] ?? PengaturanInstitusi::shared()['nama_pt']);

        return (new MailMessage)
            ->subject(Str::squish($ganti($isi['subjek'])))
            ->markdown('emails.template', [
                'institusi' => $institusi,
                'sapaan' => trim($ganti($isi['sapaan'])),
                'sebelum' => $sebelum,
                'tombol' => $pakaiTombol ? trim($ganti($isi['tombol'] ?? '')) ?: 'Buka Tautan' : null,
                'tautan' => $pakaiTombol ? $tautan : null,
                'sesudah' => $sesudah,
                'penutup' => trim($ganti($isi['penutup'])),
            ]);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
