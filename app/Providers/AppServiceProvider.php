<?php

namespace App\Providers;

use App\CatatAktivitas;
use App\Models\LogAktivitas;
use App\Models\PengaturanEmail;
use App\Models\PengaturanInstitusi;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Once;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Setiap key permission di database otomatis menjadi ability Gate,
        // sehingga route cukup memakai middleware `can:<key>`.
        Gate::before(fn (User $user, string $ability): ?bool => $user->hasPermission($ability) ? true : null);

        // Pengaturan SMTP dari halaman admin menimpa konfigurasi .env. Dipasang saat mailer
        // pertama kali dibuat agar halaman yang tidak mengirim surel tidak ikut membaca tabelnya.
        $this->app->resolving('mail.manager', fn () => PengaturanEmail::terapkan());

        // Job antrean (mis. surel notifikasi) memakai zona waktu institusi seperti permintaan web.
        Queue::before(fn () => PengaturanInstitusi::terapkanZonaWaktu());
        // Hasil once() (mis. skala nilai per prodi) jangan terbawa ke job berikutnya di worker yang berjalan lama.
        Queue::after(fn () => Once::flush());

        // Log aktivitas: semua data yang dibuat/diubah/dihapus pengguna, serta masuk/keluar (App\CatatAktivitas).
        foreach (['created' => LogAktivitas::DIBUAT, 'updated' => LogAktivitas::DIUBAH, 'deleted' => LogAktivitas::DIHAPUS] as $event => $aksi) {
            Event::listen("eloquent.{$event}: *", function (string $nama, array $data) use ($aksi): void {
                if (($data[0] ?? null) instanceof Model) {
                    CatatAktivitas::model($aksi, $data[0]);
                }
            });
        }
        Event::listen(Login::class, fn (Login $e) => CatatAktivitas::sesi(LogAktivitas::MASUK, $e->user instanceof User ? $e->user : null));
        Event::listen(Logout::class, fn (Logout $e) => CatatAktivitas::sesi(LogAktivitas::KELUAR, $e->user instanceof User ? $e->user : null));
    }
}
