<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\ProgramStudi;
use App\Models\TahunAkademik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Penetapan ketua kelas: satu mahasiswa peserta (ber-KRS) di tiap kelas kuliah. Rombel (REG 5, REG 6, …) mengikuti
 * kode kelas kuliah, jadi tidak ada master rombel terpisah.
 */
class KetuaKelasController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = [
            'tahun_akademik_id' => $request->has('tahun_akademik_id')
                ? ($request->integer('tahun_akademik_id') ?: null)
                : TahunAkademik::query()->where('status', true)->value('id'),
            'prodi_id' => $request->integer('prodi_id') ?: null,
            'search' => $request->string('search')->trim()->toString(),
        ];

        $kelas = KelasKuliah::query()
            ->when($filter['tahun_akademik_id'], fn ($q, int $id) => $q->where('tahun_akademik_id', $id))
            ->when($filter['prodi_id'], fn ($q, int $id) => $q->whereHas('mataKuliah', fn ($m) => $m->where('prodi_id', $id)))
            ->when($filter['search'] !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('kode_kelas', 'like', "%{$filter['search']}%")
                ->orWhereHas('mataKuliah', fn ($m) => $m->where('nama_matkul', 'like', "%{$filter['search']}%")->orWhere('kode_matkul', 'like', "%{$filter['search']}%"))))
            ->with([
                'mataKuliah:id,kode_matkul,nama_matkul',
                'dosen:id,user_id', 'dosen.user:id,name',
                'krs' => fn ($q) => $q->select(['id', 'kelas_id', 'mahasiswa_id'])->with(['mahasiswa:id,user_id,nim', 'mahasiswa.user:id,name']),
            ])
            ->orderBy('kode_kelas')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (KelasKuliah $item): array => [
                'id' => $item->id,
                'kode_kelas' => $item->kode_kelas,
                'mata_kuliah' => trim(($item->mataKuliah?->kode_matkul ?? '').' '.($item->mataKuliah?->nama_matkul ?? '')),
                'dosen' => $item->dosen?->user?->name,
                'ketua_kelas_id' => $item->ketua_kelas_id,
                'peserta' => $item->krs
                    ->filter(fn (Krs $krs): bool => $krs->mahasiswa !== null)
                    ->map(fn (Krs $krs): array => ['id' => $krs->mahasiswa->id, 'name' => trim(($krs->mahasiswa->nim ?? '-').' · '.$krs->mahasiswa->user?->name)])
                    ->sortBy('name', SORT_NATURAL)
                    ->values(),
            ]);

        return Inertia::render('Admin/KetuaKelas', [
            'kelas' => $kelas,
            'filter' => $filter,
            'tahunAkademikOptions' => TahunAkademik::query()->orderByDesc('tanggal_mulai')->get(['id', 'tahun', 'semester'])
                ->map(fn (TahunAkademik $ta): array => ['id' => $ta->id, 'name' => $ta->label()]),
            'prodiOptions' => ProgramStudi::query()->orderBy('nama_prodi')->get(['id', 'nama_prodi', 'jenjang'])
                ->map(fn (ProgramStudi $p): array => ['id' => $p->id, 'name' => trim($p->jenjang.' '.$p->nama_prodi)]),
        ]);
    }

    public function update(Request $request, KelasKuliah $kelasKuliah): RedirectResponse
    {
        $data = $request->validate([
            'ketua_kelas_id' => ['nullable', 'integer', Rule::exists('krs', 'mahasiswa_id')->where('kelas_id', $kelasKuliah->id)],
        ], ['ketua_kelas_id.exists' => 'Ketua kelas harus mahasiswa peserta kelas ini.']);

        $kelasKuliah->update(['ketua_kelas_id' => $data['ketua_kelas_id'] ?? null]);

        return back()->with('success', $kelasKuliah->ketua_kelas_id === null
            ? "Ketua kelas {$kelasKuliah->kode_kelas} dikosongkan."
            : "Ketua kelas {$kelasKuliah->kode_kelas} disimpan.");
    }
}
