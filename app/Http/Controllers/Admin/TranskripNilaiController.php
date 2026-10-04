<?php

namespace App\Http\Controllers\Admin;

use App\HasilStudi;
use App\Http\Controllers\Concerns\NilaiMahasiswa;
use App\Http\Controllers\Controller;
use App\Models\MahasiswaProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hasil Studi → Transkrip Nilai: daftar mahasiswa yang pernah ber-KRS, lalu transkrip tiap mahasiswa
 * (sama dengan yang dilihat mahasiswa) beserta unduhan PDF-nya.
 */
class TranskripNilaiController extends Controller
{
    use NilaiMahasiswa;

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/TranskripNilai', $this->daftarSemuaMahasiswaNilai($request));
    }

    public function show(MahasiswaProfile $mahasiswa): Response
    {
        $mahasiswa->load(['user:id,name', 'prodi:id,nama_prodi,jenjang']);

        return Inertia::render('Admin/TranskripNilaiMahasiswa', [
            'mahasiswa' => $this->identitasMahasiswa($mahasiswa),
            ...HasilStudi::transkrip($mahasiswa),
        ]);
    }

    public function download(MahasiswaProfile $mahasiswa): HttpResponse
    {
        return HasilStudi::unduhTranskrip($mahasiswa);
    }
}
