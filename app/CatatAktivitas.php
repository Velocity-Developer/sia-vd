<?php

namespace App;

use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mencatat log aktivitas: setiap model Eloquent yang dibuat, diubah, atau dihapus oleh pengguna yang sedang login, serta
 * kejadian masuk/keluar. Perubahan lewat query massal (Model::query()->update()) tidak memicu event model sehingga
 * tidak tercatat; proses tanpa pengguna (antrean, konsol) juga tidak dicatat.
 */
class CatatAktivitas
{
    /** Model yang tidak dicatat (log itu sendiri dan data teknis). */
    private const DILEWATI = [LogAktivitas::class];

    /** Kolom yang nilainya tidak pernah disimpan ke log, selain $hidden milik model. */
    private const RAHASIA = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'secret_key'];

    /** Kolom yang perubahannya saja tidak dianggap aktivitas. */
    private const DIABAIKAN = ['updated_at', 'created_at', 'remember_token', 'last_login_at', 'last_seen_at'];

    private static bool $nonaktif = false;

    public static function model(string $aksi, Model $model): void
    {
        if (self::$nonaktif || in_array($model::class, self::DILEWATI, true) || ! ($user = auth()->user()) instanceof User) {
            return;
        }

        $perubahan = self::perubahan($aksi, $model);
        if ($aksi === LogAktivitas::DIUBAH && $perubahan === []) {
            return;
        }

        self::simpan($user, $aksi, [
            'objek_tipe' => class_basename($model),
            'objek_id' => is_numeric($model->getKey()) ? (int) $model->getKey() : null,
            'label' => self::label($model),
            'perubahan' => $perubahan ?: null,
        ]);
    }

    public static function sesi(string $aksi, ?User $user): void
    {
        if (! self::$nonaktif && $user instanceof User) {
            self::simpan($user, $aksi, ['objek_tipe' => 'User', 'objek_id' => $user->id, 'label' => $user->name]);
        }
    }

    /**
     * Jalankan tanpa mencatat log (mis. impor besar yang sudah dicatat sebagai satu baris).
     *
     * @template T
     *
     * @param  callable(): T  $proses
     * @return T
     */
    public static function tanpaCatatan(callable $proses): mixed
    {
        $sebelumnya = self::$nonaktif;
        self::$nonaktif = true;

        try {
            return $proses();
        } finally {
            self::$nonaktif = $sebelumnya;
        }
    }

    /**
     * @param  array<string, mixed>  $isi
     */
    public static function simpan(User $user, string $aksi, array $isi): void
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        try {
            LogAktivitas::query()->create([
                ...$isi,
                'user_id' => $user->id,
                'aksi' => $aksi,
                'ip' => $request?->ip(),
                'rute' => $request?->route()?->getName(),
            ]);
        } catch (Throwable $e) {
            // Log aktivitas tidak boleh menggagalkan proses utama.
            report($e);
        }
    }

    /**
     * @return array<string, array{lama?: mixed, baru?: mixed}>
     */
    private static function perubahan(string $aksi, Model $model): array
    {
        $rahasia = [...self::RAHASIA, ...$model->getHidden()];
        $nilai = fn (string $kolom, mixed $v): mixed => in_array($kolom, $rahasia, true)
            ? ($v === null ? null : '••••••')
            : (is_string($v) ? Str::limit($v, 300) : $v);

        $hasil = [];
        if ($aksi === LogAktivitas::DIUBAH) {
            foreach ($model->getChanges() as $kolom => $baru) {
                if (! in_array($kolom, self::DIABAIKAN, true)) {
                    $hasil[$kolom] = ['lama' => $nilai($kolom, $model->getRawOriginal($kolom)), 'baru' => $nilai($kolom, $baru)];
                }
            }
        } else {
            $kunci = $aksi === LogAktivitas::DIBUAT ? 'baru' : 'lama';
            foreach ($model->getAttributes() as $kolom => $v) {
                if (! in_array($kolom, self::DIABAIKAN, true) && $kolom !== $model->getKeyName() && $v !== null) {
                    $hasil[$kolom] = [$kunci => $nilai($kolom, $v)];
                }
            }
        }

        return $hasil;
    }

    private static function label(Model $model): ?string
    {
        $atribut = $model->getAttributes();
        // Kolom nama/judul lebih dulu, lalu kolom berawalan nama_ atau kode_ (nama_ruang, kode_kelas, …).
        $calon = [
            'name', 'nama', 'judul', 'title',
            ...array_filter(array_keys($atribut), fn (string $k): bool => str_starts_with($k, 'nama_')),
            ...array_filter(array_keys($atribut), fn (string $k): bool => str_starts_with($k, 'kode_')),
            'kode', 'nim', 'username', 'huruf',
        ];
        foreach ($calon as $kolom) {
            $v = $atribut[$kolom] ?? null;
            if (is_scalar($v) && $v !== '' && ! in_array($kolom, $model->getHidden(), true)) {
                return Str::limit((string) $v, 250);
            }
        }

        return null;
    }
}
