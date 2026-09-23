<?php

namespace App\Providers;

use App\Models\PengaturanEmail;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
    }
}
