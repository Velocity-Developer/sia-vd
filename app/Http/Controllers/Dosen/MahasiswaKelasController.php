<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\Krs;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MahasiswaKelasController extends Controller
{
    public function index(Request $request): Response
    {
        $dosenProfileId = $request->user()?->dosenProfile?->id;

        abort_if($dosenProfileId === null, 403);

        $search = $request->string('search')->trim()->toString();
        $krs = Krs::with(['mahasiswa:id,user_id,nim,prodi_id', 'mahasiswa.user:id,name,email', 'mahasiswa.prodi:id,nama_prodi', 'kelasKuliah.mataKuliah'])
            ->whereHas('kelasKuliah', fn ($query) => $query->where('dosen_id', $dosenProfileId))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->whereHas('mahasiswa', fn ($query) => $query->where('nim', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($query) => $query->where('name', 'like', "%{$search}%")))
                    ->orWhereHas('kelasKuliah', fn ($query) => $query->where('kode_kelas', 'like', "%{$search}%")
                        ->orWhereHas('mataKuliah', fn ($query) => $query->where('kode_matkul', 'like', "%{$search}%")
                            ->orWhere('nama_matkul', 'like', "%{$search}%")));
            }))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Dosen/MahasiswaKelas', [
            'krs' => $krs,
            'search' => $search,
        ]);
    }
}
