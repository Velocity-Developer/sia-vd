<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kelompok route yang dikirim ke browser
    |--------------------------------------------------------------------------
    |
    | Tanpa pengelompokan, setiap halaman memuat seluruh daftar route (termasuk semua URL admin)
    | walaupun yang membuka mahasiswa. Kelompok dipilih dari izin yang dimiliki pengguna
    | (lihat App\Models\User::grupRute), bukan dari jenis penggunanya, karena role dosen bisa
    | saja diberi sebagian izin admin (role mahasiswa tidak bisa, lihat Permission::isAvailableFor).
    |
    */

    'groups' => [
        'umum' => [
            'home', 'login', 'logout', 'dashboard',
            'password.*', 'profile.*', 'verification.*', 'institusi.*', 'pengaturan-email.*', 'pengaturan-sistem.*', 'pengaturan-tampilan.*', 'pengaturan-recaptcha.*', 'berkas.*',
        ],

        'staf' => [
            'home', 'login', 'logout', 'dashboard',
            'password.*', 'profile.*', 'verification.*', 'institusi.*', 'pengaturan-email.*', 'pengaturan-sistem.*', 'pengaturan-tampilan.*', 'pengaturan-recaptcha.*', 'berkas.*',
            'admin.*', 'dosen.*', 'mahasiswa.*', 'dev.*',
        ],

        'mahasiswa' => [
            'home', 'login', 'logout', 'dashboard',
            'password.*', 'profile.*', 'verification.*', 'institusi.*', 'pengaturan-email.*', 'pengaturan-sistem.*', 'pengaturan-tampilan.*', 'pengaturan-recaptcha.*', 'berkas.*',
            'mahasiswa.*',
        ],
    ],
];
