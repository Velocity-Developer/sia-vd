<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\KelasKuliah;
use App\Models\Krs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KelasKuliahController extends Controller
{
    public function index(Request $request): Response
    {
        $dosenProfileId = $request->user()?->dosenProfile?->id;

        abort_if($dosenProfileId === null, 403);

        $search = $request->string('search')->trim()->toString();

        $kelasKuliahs = KelasKuliah::with(['mataKuliah.prodi', 'tahunAkademik', 'jadwals.ruang'])
            ->where('dosen_id', $dosenProfileId)
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('kode_kelas', 'like', "%{$search}%")->orWhereHas('tahunAkademik', fn ($q) => $q->where('tahun', 'like', "%{$search}%")->orWhere('semester', 'like', "%{$search}%"))->orWhereHas('mataKuliah', fn ($q) => $q->where('kode_matkul', 'like', "%{$search}%")->orWhere('nama_matkul', 'like', "%{$search}%"))))
            ->orderBy('kode_kelas')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Dosen/KelasKuliah', [
            'kelasKuliahs' => $kelasKuliahs,
            'search' => $search,
        ]);
    }

    public function updateGrade(Request $request, KelasKuliah $kelasKuliah, Krs $krs): RedirectResponse
    {
        abort_if($kelasKuliah->dosen_id !== $request->user()?->dosenProfile?->id || $krs->kelas_id !== $kelasKuliah->id, 403);
        $krs->update($request->validate(['nilai' => ['required', Rule::in(['A', 'B', 'C', 'D', 'E'])]]));

        return back()->with('success', 'Nilai berhasil diperbarui.');
    }

    public function show(Request $request, KelasKuliah $kelasKuliah): Response
    {
        $dosenProfileId = $request->user()?->dosenProfile?->id;

        abort_if($dosenProfileId === null || $kelasKuliah->dosen_id !== $dosenProfileId, 403);

        $kelasKuliah->load([
            'mataKuliah.prodi.fakultas',
            'tahunAkademik',
            'jadwals.ruang',
            'materis.uploader:id,name',
            'tugas.uploader:id,name',
            'quizzes.uploader:id,name',
            'krs.mahasiswa.user',
            'krs.mahasiswa.prodi',
        ]);

        return Inertia::render('Dosen/KelasKuliahShow', [
            'kelasKuliah' => $kelasKuliah,
            'otherClasses' => KelasKuliah::with('mataKuliah')->where('dosen_id', $dosenProfileId)->whereKeyNot($kelasKuliah->id)->orderBy('kode_kelas')->get(),
        ]);
    }
}
