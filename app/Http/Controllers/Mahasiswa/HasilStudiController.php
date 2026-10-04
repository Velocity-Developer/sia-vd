<?php

namespace App\Http\Controllers\Mahasiswa;

use App\HasilStudi;
use App\Http\Controllers\Controller;
use App\Models\MahasiswaProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class HasilStudiController extends Controller
{
    public function transkrip(Request $request): Response
    {
        return Inertia::render('Mahasiswa/TranskripNilai', HasilStudi::transkrip($this->mahasiswa($request)));
    }

    public function downloadTranskrip(Request $request): HttpResponse
    {
        return HasilStudi::unduhTranskrip($this->mahasiswa($request));
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Mahasiswa/HasilStudi', HasilStudi::propsKhs($this->mahasiswa($request), $request->integer('tahun_akademik_id')));
    }

    public function downloadKhs(Request $request): HttpResponse
    {
        return HasilStudi::unduhKhs($this->mahasiswa($request), $request->integer('tahun_akademik_id'));
    }

    private function mahasiswa(Request $request): MahasiswaProfile
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        return $mahasiswa;
    }
}
