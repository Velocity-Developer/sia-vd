<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\UserType;
use Illuminate\Console\Command;

/**
 * Role developer tidak bisa diberikan lewat aplikasi (lihat User::canAssignRole), hanya lewat perintah ini.
 */
class AturDeveloper extends Command
{
    protected $signature = 'sia:developer {username : username akun admin/karyawan} {--cabut : kembalikan ke role Admin bawaan}';

    protected $description = 'Jadikan akun admin/karyawan sebagai developer (panel /dev), atau cabut kembali';

    public function handle(): int
    {
        $user = User::query()->with('role')->where('username', $this->argument('username'))->first();

        if ($user === null) {
            $this->error('Akun tidak ditemukan.');

            return self::FAILURE;
        }

        if ($user->type() !== UserType::Admin) {
            $this->error('Hanya akun admin/karyawan yang bisa dijadikan developer.');

            return self::FAILURE;
        }

        if ($this->option('cabut')) {
            if (! $user->isDeveloper()) {
                $this->info("{$user->username} bukan developer.");

                return self::SUCCESS;
            }

            $user->update(['role_id' => Role::system(UserType::Admin)->id]);
            $this->info("{$user->username} kini ber-role Admin.");

            return self::SUCCESS;
        }

        if ($user->isLastRoleManager()) {
            $this->error('Akun ini satu-satunya pemegang Kelola Role; role-nya tidak bisa diganti.');

            return self::FAILURE;
        }

        $user->update(['role_id' => Role::query()->where('slug', Role::DEVELOPER)->firstOrFail()->id]);
        $this->info("{$user->username} kini ber-role developer.".(config('app.dev_panel') ? '' : ' Panel /dev baru terbuka bila DEV_PANEL=true.'));

        return self::SUCCESS;
    }
}
