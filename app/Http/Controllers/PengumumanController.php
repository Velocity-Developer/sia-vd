<?php

namespace App\Http\Controllers;

use App\Models\InfoKuliah;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar lengkap Informasi & Pengumuman untuk umum (menu "Pengumuman" di halaman depan).
 * Isinya sama dengan yang dikelola admin di Informasi & Pengumuman.
 */
class PengumumanController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Portal/Pengumuman', [
            'pengumuman' => InfoKuliah::publik()->paginate(10)->withQueryString(),
        ]);
    }
}
