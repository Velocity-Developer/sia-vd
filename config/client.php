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
    | - default : status fitur (FEATURE_*) bila tidak ada override database. Fitur tambahan baru
    |             bawaan mati; fitur yang sudah lama ada (materi s.d. ujian susulan) bawaan nyala
    |             agar instalasi lama tidak berubah.
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

        // Konten kelas. Mati = menu, bagian di halaman kelas, dan rutenya hilang untuk admin, dosen, dan mahasiswa.
        'materi' => [
            'label' => 'Materi',
            'keterangan' => 'Materi kuliah per kelas (unggah berkas/tautan) untuk dosen, admin, dan mahasiswa.',
            'default' => env('FEATURE_MATERI', true),
            'locked' => env('LOCK_MATERI', false),
            'butuh' => [],
        ],

        'tugas' => [
            'label' => 'Tugas',
            'keterangan' => 'Tugas per kelas: pengumpulan berkas mahasiswa dan penilaian dosen.',
            'default' => env('FEATURE_TUGAS', true),
            'locked' => env('LOCK_TUGAS', false),
            'butuh' => [],
        ],

        // Quiz kelas. Lembar soal ujian online memakai mesin yang sama tetapi diatur flag ujian_online.
        'quiz' => [
            'label' => 'Quiz',
            'keterangan' => 'Quiz per kelas (pilihan ganda, isian, esai). Lembar soal ujian online diatur fitur Ujian Online.',
            'default' => env('FEATURE_QUIZ', true),
            'locked' => env('LOCK_QUIZ', false),
            'butuh' => [],
        ],

        // Mode ujian online_berkas & online_soal. Mati = semua ujian (UTS/UAS/remidi/susulan) tatap muka.
        'ujian_online' => [
            'label' => 'Ujian Online',
            'keterangan' => 'Mode ujian unggah berkas jawaban dan lembar soal online. Mati = semua ujian tatap muka.',
            'default' => env('FEATURE_UJIAN_ONLINE', true),
            'locked' => env('LOCK_UJIAN_ONLINE', false),
            'butuh' => [],
        ],

        // Presensi mandiri mahasiswa lewat QR/PIN. Mati = presensi hanya dicatat manual.
        'presensi_qr' => [
            'label' => 'Presensi QR/PIN',
            'keterangan' => 'Presensi mandiri mahasiswa dengan memindai QR atau mengetik PIN. Mati = presensi dicatat manual.',
            'default' => env('FEATURE_PRESENSI_QR', true),
            'locked' => env('LOCK_PRESENSI_QR', false),
            'butuh' => [],
        ],

        'pindah_kelas' => [
            'label' => 'Pindah Kelas',
            'keterangan' => 'Pengajuan pindah kelas paralel oleh mahasiswa dan persetujuan admin.',
            'default' => env('FEATURE_PINDAH_KELAS', true),
            'locked' => env('LOCK_PINDAH_KELAS', false),
            'butuh' => [],
        ],

        // Mati = tidak ada pengajuan, jadwal, maupun tagihan ujian susulan; finalisasi nilai tidak menunggu susulan.
        'ujian_susulan' => [
            'label' => 'Ujian Susulan',
            'keterangan' => 'Pengajuan ujian susulan UTS/UAS oleh mahasiswa, persetujuan, jadwal, dan tagihannya.',
            'default' => env('FEATURE_UJIAN_SUSULAN', true),
            'locked' => env('LOCK_UJIAN_SUSULAN', false),
            'butuh' => [],
        ],
    ],
];
