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
 * Hasil Studi → KHS: daftar mahasiswa ber-KRS di satu tahun akademik, lalu Kartu Hasil Studi tiap mahasiswa
 * (sama dengan yang dilihat mahasiswa) beserta unduhan PDF-nya.
 */
class KhsController extends Controller
{
    use NilaiMahasiswa;

    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Khs', $this->daftarMahasiswaNilai($request));
    }

    public function show(Request $request, MahasiswaProfile $mahasiswa): Response
    {
        $mahasiswa->load(['user:id,name', 'prodi:id,nama_prodi,jenjang']);

        return Inertia::render('Admin/KhsMahasiswa', [
            'mahasiswa' => $this->identitasMahasiswa($mahasiswa),
            ...HasilStudi::propsKhs($mahasiswa, $request->integer('tahun_akademik_id')),
        ]);
    }

    public function download(Request $request, MahasiswaProfile $mahasiswa): HttpResponse
    {
        return HasilStudi::unduhKhs($mahasiswa, $request->integer('tahun_akademik_id'));
    }
}
