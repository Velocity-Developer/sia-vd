<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fitur per klien (feature flag)
    |--------------------------------------------------------------------------
    |
    | Fitur yang bisa dinyalakan atau dimatikan per instalasi lewat .env. Baca statusnya
    | lewat App\Feature::aktif($nama); nama yang tidak terdaftar di sini dianggap mati.
    |
    | - default : status fitur (FEATURE_*), bawaan mati.
    | - locked  : status dikunci developer (LOCK_*), nantinya admin tidak bisa mengubahnya
    |             dari aplikasi. Saat ini status hanya dibaca dari config ini.
    | - butuh   : fitur lain yang harus aktif; bila salah satunya mati, fitur ini ikut mati.
    |
    */

    'fitur' => [
        // Menu Kelola Role. Mati = role & hak aksesnya diatur developer lewat kode.
        'kelola_role' => [
            'default' => env('FEATURE_KELOLA_ROLE', false),
            'locked' => env('LOCK_KELOLA_ROLE', false),
            'butuh' => [],
        ],

        // Jenis biaya, tagihan semester/remidi/susulan, info biaya kuliah, dan kunci KRS.
        // Mati = remidi dan ujian susulan tanpa syarat bayar.
        'keuangan' => [
            'default' => env('FEATURE_KEUANGAN', false),
            'locked' => env('LOCK_KEUANGAN', false),
            'butuh' => [],
        ],

        // Pengajuan dan jadwal ujian susulan.
        'ujian_susulan' => [
            'default' => env('FEATURE_UJIAN_SUSULAN', false),
            'locked' => env('LOCK_UJIAN_SUSULAN', false),
            'butuh' => [],
        ],
    ],
];
