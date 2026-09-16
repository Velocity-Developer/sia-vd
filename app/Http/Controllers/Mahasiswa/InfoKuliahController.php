<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\InfoKuliah;
use Inertia\Inertia;
use Inertia\Response;

class InfoKuliahController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Mahasiswa/InfoKuliah', ['infoKuliahs' => InfoKuliah::with('uploader')->latest()->paginate(10)]);
    }
}
