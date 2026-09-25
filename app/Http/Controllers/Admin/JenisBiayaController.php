<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JenisBiaya;
use App\Models\ProgramStudi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class JenisBiayaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/JenisBiaya', [
            'jenisBiaya' => JenisBiaya::query()
                ->withCount('tarif')
                ->orderBy('urutan')
                ->orderBy('nama')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/JenisBiayaForm', [
            'jenis' => null,
            'prodiOptions' => $this->prodiOptions(),
        ]);
    }

    public function edit(JenisBiaya $jenisBiaya): Response
    {
        return Inertia::render('Admin/JenisBiayaForm', [
            'jenis' => $jenisBiaya->load(['tarif' => fn ($query) => $query->orderBy('prodi_id')->orderBy('angkatan')]),
            'prodiOptions' => $this->prodiOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->simpan($request, new JenisBiaya);

        return to_route('admin.jenis-biaya.index')->with('success', 'Jenis biaya berhasil ditambahkan.');
    }

    public function update(Request $request, JenisBiaya $jenisBiaya): RedirectResponse
    {
        $this->simpan($request, $jenisBiaya);

        return to_route('admin.jenis-biaya.index')->with('success', 'Jenis biaya berhasil diperbarui.');
    }

    public function destroy(JenisBiaya $jenisBiaya): RedirectResponse
    {
        // Rincian tagihan yang sudah terbit menyimpan salinan nama dan nominalnya sendiri,
        // jadi menghapus jenis biaya tidak mengubah tagihan lama.
        $jenisBiaya->delete();

        return to_route('admin.jenis-biaya.index')->with('success', 'Jenis biaya berhasil dihapus.');
    }

    private function simpan(Request $request, JenisBiaya $jenis): void
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', Rule::unique('jenis_biaya', 'kode')->ignore($jenis)],
            'nama' => ['required', 'string', 'max:255'],
            'cara_hitung' => ['required', Rule::in([JenisBiaya::TETAP, JenisBiaya::PER_SKS])],
            'kategori' => ['nullable', Rule::in([JenisBiaya::SEMESTER, JenisBiaya::REMIDI])],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'aktif' => ['required', 'boolean'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
            'tarif' => ['array'],
            'tarif.*.prodi_id' => ['nullable', 'integer', Rule::exists('program_studis', 'id')],
            'tarif.*.angkatan' => ['nullable', 'integer', 'min:1900', 'max:2999'],
            'tarif.*.nominal' => ['required', 'integer', 'min:0', 'max:9999999999'],
        ], attributes: [
            'kode' => 'Kode',
            'nama' => 'Nama',
            'cara_hitung' => 'Cara hitung',
            'kategori' => 'Kategori',
            'keterangan' => 'Keterangan',
            'aktif' => 'Status aktif',
            'tarif.*.nominal' => 'Nominal tarif',
            'tarif.*.angkatan' => 'Angkatan',
        ]);

        DB::transaction(function () use ($jenis, $data): void {
            $jenis->fill([
                'kode' => $data['kode'],
                'nama' => $data['nama'],
                'cara_hitung' => $data['cara_hitung'],
                'kategori' => $data['kategori'] ?? JenisBiaya::SEMESTER,
                'keterangan' => $data['keterangan'] ?? null,
                'aktif' => $data['aktif'],
                'urutan' => $data['urutan'] ?? 0,
            ])->save();

            // Tarif ditulis ulang seluruhnya: jumlah barisnya berubah-ubah mengikuti form.
            $jenis->tarif()->delete();

            foreach ($data['tarif'] ?? [] as $tarif) {
                $jenis->tarif()->create([
                    'prodi_id' => $tarif['prodi_id'] ?? null,
                    'angkatan' => $tarif['angkatan'] ?? null,
                    'nominal' => $tarif['nominal'],
                ]);
            }
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function prodiOptions(): array
    {
        return ProgramStudi::query()
            ->orderBy('nama_prodi')
            ->get(['id', 'nama_prodi', 'jenjang'])
            ->map(fn (ProgramStudi $prodi): array => ['id' => $prodi->id, 'name' => $prodi->jenjang.' '.$prodi->nama_prodi])
            ->all();
    }
}
