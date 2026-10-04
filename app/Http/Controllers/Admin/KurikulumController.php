<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kurikulum;
use App\Models\MataKuliah;
use App\Models\ProgramStudi;
use App\Models\TahunAkademik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kurikulum per program studi beserta daftar mata kuliahnya. Akun Prodi hanya melihat dan mengubah kurikulum prodinya
 * (Kurikulum dan MataKuliah dibatasi DibatasiProdi).
 */
class KurikulumController extends Controller
{
    public function index(Request $request): Response
    {
        $prodiId = $request->integer('prodi_id') ?: null;

        $kurikulum = Kurikulum::query()
            ->with(['prodi:id,nama_prodi,jenjang', 'tahunAkademik:id,tahun,semester'])
            ->withCount('mataKuliahs')
            ->withSum('mataKuliahs as total_sks', 'sks')
            ->when($prodiId, fn ($q, int $id) => $q->where('prodi_id', $id))
            ->orderByDesc('aktif')
            ->orderBy('prodi_id')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Kurikulum $item): array => [
                'id' => $item->id,
                'nama' => $item->nama,
                'prodi' => $this->namaProdi($item->prodi),
                'mulai_berlaku' => $item->tahunAkademik?->label(),
                'aktif' => $item->aktif,
                'jumlah_mk' => $item->mata_kuliahs_count,
                'total_sks' => (int) $item->total_sks,
            ]);

        return Inertia::render('Admin/Kurikulum', [
            'kurikulum' => $kurikulum,
            'prodiOptions' => $this->prodiOptions(),
            'prodiId' => $prodiId,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/KurikulumForm', ['kurikulum' => null, ...$this->opsiForm()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $kurikulum = Kurikulum::create($this->validasi($request, new Kurikulum));

        return to_route('admin.kurikulum.show', $kurikulum)->with('success', 'Kurikulum ditambahkan. Lanjutkan dengan menambahkan mata kuliahnya.');
    }

    public function show(Kurikulum $kurikulum): Response
    {
        $kurikulum->load(['prodi:id,nama_prodi,jenjang', 'tahunAkademik:id,tahun,semester']);
        $mataKuliah = $kurikulum->mataKuliahs()
            ->orderBy('kurikulum_mata_kuliah.semester')
            ->orderBy('kode_matkul')
            ->get(['mata_kuliahs.id', 'kode_matkul', 'nama_matkul', 'sks', 'jenis_penilaian']);
        $sudah = $mataKuliah->pluck('id');

        return Inertia::render('Admin/KurikulumShow', [
            'kurikulum' => [
                'id' => $kurikulum->id,
                'nama' => $kurikulum->nama,
                'prodi' => $this->namaProdi($kurikulum->prodi),
                'mulai_berlaku' => $kurikulum->tahunAkademik?->label(),
                'aktif' => $kurikulum->aktif,
                'keterangan' => $kurikulum->keterangan,
            ],
            'mataKuliah' => $mataKuliah->map(fn (MataKuliah $mk): array => [
                'id' => $mk->id,
                'kode_matkul' => $mk->kode_matkul,
                'nama_matkul' => $mk->nama_matkul,
                'sks' => $mk->sks,
                'jenis_penilaian' => $mk->jenis_penilaian,
                'semester' => $mk->pivot->semester,
                'jenis' => $mk->pivot->jenis,
            ]),
            // Mata kuliah prodi yang belum masuk kurikulum ini, untuk ditambahkan.
            'pilihanMataKuliah' => MataKuliah::query()
                ->where('prodi_id', $kurikulum->prodi_id)
                ->whereNotIn('id', $sudah)
                ->orderBy('semester')
                ->orderBy('kode_matkul')
                ->get(['id', 'kode_matkul', 'nama_matkul', 'sks', 'semester', 'jenis', 'jenis_penilaian']),
        ]);
    }

    public function edit(Kurikulum $kurikulum): Response
    {
        return Inertia::render('Admin/KurikulumForm', [
            'kurikulum' => $kurikulum->only(['id', 'prodi_id', 'nama', 'tahun_akademik_id', 'aktif', 'keterangan']),
            ...$this->opsiForm(),
        ]);
    }

    public function update(Request $request, Kurikulum $kurikulum): RedirectResponse
    {
        $data = $this->validasi($request, $kurikulum);
        if ((int) $data['prodi_id'] !== (int) $kurikulum->prodi_id && $kurikulum->mataKuliahs()->exists()) {
            throw ValidationException::withMessages(['prodi_id' => 'Program studi kurikulum yang sudah berisi mata kuliah tidak bisa diganti.']);
        }
        $kurikulum->update($data);

        return to_route('admin.kurikulum.show', $kurikulum)->with('success', 'Kurikulum diperbarui.');
    }

    public function destroy(Kurikulum $kurikulum): RedirectResponse
    {
        $kurikulum->delete();

        return to_route('admin.kurikulum.index')->with('success', "Kurikulum {$kurikulum->nama} dihapus.");
    }

    public function tambahMataKuliah(Request $request, Kurikulum $kurikulum): RedirectResponse
    {
        $data = $request->validate([
            'mata_kuliah_ids' => ['required', 'array', 'min:1'],
            'mata_kuliah_ids.*' => ['integer', 'distinct', Rule::exists('mata_kuliahs', 'id')->where('prodi_id', $kurikulum->prodi_id)],
        ], ['mata_kuliah_ids.required' => 'Pilih minimal satu mata kuliah.', 'mata_kuliah_ids.*.exists' => 'Mata kuliah harus milik program studi kurikulum ini.']);

        $sudah = $kurikulum->mataKuliahs()->pluck('mata_kuliahs.id');
        $baru = MataKuliah::query()->whereIn('id', $data['mata_kuliah_ids'])->whereNotIn('id', $sudah)->get(['id', 'semester', 'jenis']);
        // Semester dan sifat awal mengikuti data mata kuliah; bisa diubah per kurikulum.
        $kurikulum->mataKuliahs()->attach($baru->mapWithKeys(fn (MataKuliah $mk): array => [
            $mk->id => ['semester' => $mk->semester, 'jenis' => in_array($mk->jenis, Kurikulum::JENIS, true) ? $mk->jenis : 'Wajib'],
        ])->all());

        return back()->with('success', $baru->count().' mata kuliah ditambahkan ke kurikulum.');
    }

    public function ubahMataKuliah(Request $request, Kurikulum $kurikulum, MataKuliah $mataKuliah): RedirectResponse
    {
        $data = $request->validate([
            'semester' => ['required', 'integer', 'min:1', 'max:14'],
            'jenis' => ['required', Rule::in(Kurikulum::JENIS)],
        ], attributes: ['semester' => 'Semester', 'jenis' => 'Jenis']);

        abort_unless($kurikulum->mataKuliahs()->whereKey($mataKuliah->id)->exists(), 404);
        $kurikulum->mataKuliahs()->updateExistingPivot($mataKuliah->id, $data);

        return back()->with('success', "{$mataKuliah->nama_matkul} diperbarui.");
    }

    public function hapusMataKuliah(Kurikulum $kurikulum, MataKuliah $mataKuliah): RedirectResponse
    {
        $kurikulum->mataKuliahs()->detach($mataKuliah->id);

        return back()->with('success', "{$mataKuliah->nama_matkul} dikeluarkan dari kurikulum.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request, Kurikulum $model): array
    {
        $data = $request->validate([
            'prodi_id' => ['required', 'integer', Rule::exists('program_studis', 'id')],
            'nama' => ['required', 'string', 'max:100', Rule::unique('kurikulums', 'nama')->where('prodi_id', $request->integer('prodi_id'))->ignore($model)],
            'tahun_akademik_id' => ['nullable', 'integer', Rule::exists('tahun_akademik', 'id')],
            'aktif' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ], ['nama.unique' => 'Nama kurikulum sudah dipakai di program studi ini.'], [
            'prodi_id' => 'Program studi',
            'nama' => 'Nama kurikulum',
            'tahun_akademik_id' => 'Mulai berlaku',
            'keterangan' => 'Keterangan',
        ]);

        // Program studi di luar lingkup akun Prodi tidak terlihat oleh global scope.
        abort_unless(ProgramStudi::query()->whereKey($data['prodi_id'])->exists(), 403);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function opsiForm(): array
    {
        return [
            'prodiOptions' => $this->prodiOptions(),
            'tahunAkademikOptions' => TahunAkademik::query()->orderByDesc('tanggal_mulai')->get(['id', 'tahun', 'semester'])
                ->map(fn (TahunAkademik $ta): array => ['id' => $ta->id, 'name' => $ta->label()]),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function prodiOptions(): array
    {
        return ProgramStudi::query()->orderBy('nama_prodi')->get(['id', 'nama_prodi', 'jenjang'])
            ->map(fn (ProgramStudi $p): array => ['id' => $p->id, 'name' => $this->namaProdi($p)])->all();
    }

    private function namaProdi(?ProgramStudi $prodi): ?string
    {
        return $prodi === null ? null : trim($prodi->jenjang.' '.$prodi->nama_prodi);
    }
}
