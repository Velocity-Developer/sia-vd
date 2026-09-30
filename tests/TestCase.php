<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tes lama menguji alur lengkap dengan fitur keuangan (tagihan, kunci KRS, remidi & susulan berbayar),
     * jadi fitur itu dinyalakan di sini. Tes yang menguji keadaan fitur mati mematikannya sendiri.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['client.fitur.keuangan.default' => true]);
    }

    /**
     * Pengguna yang login lewat tes dianggap baru saja mengonfirmasi kata sandi, agar tes menu
     * Kelola User/Role tidak tertahan middleware password.confirm. Tes konfirmasi sandi
     * mengosongkan kembali `auth.password_confirmed_at` lewat withSession().
     */
    public function be(Authenticatable $user, $guard = null)
    {
        parent::be($user, $guard);

        return $this->withSession(['auth.password_confirmed_at' => time()]);
    }
}
