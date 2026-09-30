<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fitur per klien (feature flag)
    |--------------------------------------------------------------------------
    |
    | Fitur yang bisa dinyalakan atau dimatikan per instalasi lewat .env, atau di-override
    | lewat tabel pengaturan_fitur (App\Models\PengaturanFitur::atur). Baca statusnya lewat
    | App\Feature::aktif($nama); nama yang tidak terdaftar di sini dianggap mati.
    |
    | - default : status fitur (FEATURE_*) bila tidak ada override database, bawaan mati.
    | - locked  : status dikunci developer (LOCK_*): selalu ikut `default`, override
    |             database diabaikan dan tidak bisa disimpan.
    | - butuh   : fitur lain yang harus aktif; bila salah satunya mati, fitur ini ikut mati.
    | - label, keterangan : teks untuk panel developer /dev/fitur.
    |
    */

    'fitur' => [
        // Menu Kelola Role. Mati = role & hak aksesnya diatur developer lewat kode.
        'kelola_role' => [
            'label' => 'Kelola Role',
            'keterangan' => 'Menu Kelola Role untuk admin. Mati = role dan hak aksesnya diatur developer.',
            'default' => env('FEATURE_KELOLA_ROLE', false),
            'locked' => env('LOCK_KELOLA_ROLE', false),
            'butuh' => [],
        ],

        // Jenis biaya, tagihan semester/remidi/susulan, info biaya kuliah, dan kunci KRS.
        // Mati = remidi dan ujian susulan tanpa syarat bayar.
        'keuangan' => [
            'label' => 'Keuangan',
            'keterangan' => 'Jenis biaya, tagihan semester/remidi/susulan, info biaya kuliah, kunci KRS. Mati = remidi dan susulan gratis.',
            'default' => env('FEATURE_KEUANGAN', false),
            'locked' => env('LOCK_KEUANGAN', false),
            'butuh' => [],
        ],
    ],
];
