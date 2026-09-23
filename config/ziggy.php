<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Kelompok route yang dikirim ke browser
    |--------------------------------------------------------------------------
    |
    | Tanpa pengelompokan, setiap halaman memuat seluruh daftar route (termasuk semua URL admin)
    | walaupun yang membuka mahasiswa. Kelompok dipilih dari izin yang dimiliki pengguna
    | (lihat App\Models\User::grupRute), bukan dari jenis penggunanya, karena role dosen atau
    | mahasiswa bisa saja diberi sebagian izin admin.
    |
    */

    'groups' => [
        'umum' => [
            'home', 'login', 'logout', 'dashboard',
            'password.*', 'profile.*', 'verification.*', 'institusi.*', 'pengaturan-email.*', 'berkas.*',
        ],

        'staf' => [
            'home', 'login', 'logout', 'dashboard',
            'password.*', 'profile.*', 'verification.*', 'institusi.*', 'pengaturan-email.*', 'berkas.*',
            'admin.*', 'dosen.*', 'mahasiswa.*',
        ],

        'mahasiswa' => [
            'home', 'login', 'logout', 'dashboard',
            'password.*', 'profile.*', 'verification.*', 'institusi.*', 'pengaturan-email.*', 'berkas.*',
            'mahasiswa.*',
        ],
    ],
];
