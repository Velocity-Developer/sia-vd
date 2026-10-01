<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DosenProfile;
use App\Models\Fakultas;
use App\Models\Kota;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\Provinsi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProgramStudiController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $fakultasId = $request->integer('fakultas_id') ?: null;
        $programStudis = ProgramStudi::with(['fakultas', 'ketuaProgramStudi.user:id,name'])->when($fakultasId !== null, fn ($query) => $query->where('fakultas_id', $fakultasId))->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('kode_prodi', 'like', "%{$search}%")->orWhere('nama_prodi', 'like', "%{$search}%")))->orderBy('nama_prodi')->paginate(10)->withQueryString();

        return Inertia::render('Admin/ProgramStudi', ['programStudis' => $programStudis, 'search' => $search, 'fakultas' => Fakultas::orderBy('nama_fakultas')->get(['id', 'nama_fakultas']), 'fakultasId' => $fakultasId]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/ProgramStudiForm', ['programStudi' => null, 'fakultas' => Fakultas::orderBy('nama_fakultas')->get(['id', 'nama_fakultas']), 'dosen' => $this->dosen(null), ...$this->pilihanForm()]);
    }

    public function show(ProgramStudi $programStudi): Response
    {
        $programStudi->load(['fakultas.dekan.user:id,name', 'ketuaProgramStudi.user:id,name', 'provinsi:id,nama', 'kota:id,nama']);

        return Inertia::render('Admin/ProgramStudiShow', ['programStudi' => $programStudi]);
    }

    public function edit(ProgramStudi $programStudi): Response
    {
        return Inertia::render('Admin/ProgramStudiForm', ['programStudi' => $programStudi, 'fakultas' => Fakultas::orderBy('nama_fakultas')->get(['id', 'nama_fakultas']), 'dosen' => $this->dosen($programStudi->kaprodi), ...$this->pilihanForm()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new ProgramStudi);

        return to_route('admin.program-studi.index')->with('success', 'Program Studi berhasil ditambahkan.');
    }

    public function update(Request $request, ProgramStudi $programStudi): RedirectResponse
    {
        $this->save($request, $programStudi);

        return to_route('admin.program-studi.index')->with('success', 'Program Studi berhasil diperbarui.');
    }

    public function destroy(ProgramStudi $programStudi): RedirectResponse
    {
        if ($programStudi->mataKuliah()->exists()) {
            return to_route('admin.program-studi.index')->with('error', 'Program Studi tidak dapat dihapus karena masih memiliki mata kuliah.');
        }

        if (MahasiswaProfile::query()->where('prodi_id', $programStudi->id)->exists() || DosenProfile::query()->where('prodi_id', $programStudi->id)->exists()) {
            return to_route('admin.program-studi.index')->with('error', 'Program Studi tidak dapat dihapus karena masih memiliki mahasiswa atau dosen.');
        }

        try {
            $programStudi->delete();
        } catch (Throwable) {
            return to_route('admin.program-studi.index')->with('error', 'Program Studi gagal dihapus.');
        }

        return to_route('admin.program-studi.index')->with('success', 'Program Studi berhasil dihapus.');
    }

    /**
     * @return array{provinsis: mixed, kotas: mixed, statusProdi: list<string>}
     */
    private function pilihanForm(): array
    {
        return [
            'provinsis' => Provinsi::query()->orderBy('nama')->get(['id', 'nama']),
            'kotas' => Kota::query()->orderBy('nama')->get(['id', 'provinsi_id', 'nama']),
            'statusProdi' => ProgramStudi::STATUS_PRODI,
        ];
    }

    private function dosen(?int $terpilih): array
    {
        return DosenProfile::pilihan($terpilih)->with('user:id,name')->get(['id', 'user_id'])->map(fn (DosenProfile $dosen): array => ['id' => $dosen->id, 'name' => $dosen->user->name])->all();
    }

    private function save(Request $request, ProgramStudi $model): void
    {
        foreach (['tanggal_akreditasi_mulai', 'tanggal_akreditasi_akhir', 'tanggal_sk_dikti', 'tanggal_berakhir_sk_dikti'] as $tanggal) {
            if ($request->input($tanggal) === '') {
                $request->merge([$tanggal => null]);
            }
        }

        $data = $request->validate(['fakultas_id' => ['required', 'exists:fakultas,id'], 'kode_prodi' => ['required', 'string', Rule::unique('program_studis')->ignore($model)], 'nama_prodi' => ['required', 'string'], 'jenjang' => ['required', 'string'], 'status_akreditasi' => ['required', 'string'], 'no_sk_akreditasi' => ['nullable', 'string'], 'tanggal_akreditasi_mulai' => ['nullable', 'date'], 'tanggal_akreditasi_akhir' => ['nullable', 'date'], 'kaprodi' => ['required', DosenProfile::rulePilihan($model->kaprodi)], 'tahun_berdiri' => ['required', 'integer'],
            'gelar_akademik' => ['nullable', 'string', 'max:150'],
            'singkatan_gelar' => ['nullable', 'string', 'max:30'],
            'sks_lulus' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'status_prodi' => ['nullable', Rule::in(ProgramStudi::STATUS_PRODI)],
            'nomor_kaprodi' => ['nullable', 'string', 'max:30'],
            'operator' => ['nullable', 'string', 'max:255'],
            'nomor_operator' => ['nullable', 'string', 'max:30'],
            'no_sk_dikti' => ['nullable', 'string', 'max:100'],
            'tanggal_sk_dikti' => ['nullable', 'date'],
            'tanggal_berakhir_sk_dikti' => ['nullable', 'date', 'after_or_equal:tanggal_sk_dikti'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'provinsi_id' => ['nullable', 'integer', 'exists:provinsis,id'],
            // Kota harus berada di provinsi yang dipilih.
            'kota_id' => ['nullable', 'integer', Rule::exists('kotas', 'id')->where('provinsi_id', $request->integer('provinsi_id'))],
            'kode_pos' => ['nullable', 'string', 'max:10'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'faximili' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
        ], $this->messages(), $this->attributes());
        $model->fill($data)->save();
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'date' => 'Format :attribute tidak valid.',
            'exists' => ':attribute tidak ditemukan.',
            'kota_id.exists' => 'Kota/Kabupaten tidak berada di provinsi yang dipilih.',
            'tanggal_berakhir_sk_dikti.after_or_equal' => 'Tanggal Berakhir SK Dikti tidak boleh sebelum Tanggal SK Dikti.',
            'email' => ':attribute harus berupa alamat email yang valid.',
            'url' => ':attribute harus berupa URL yang valid.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'fakultas_id' => 'Fakultas',
            'kode_prodi' => 'Kode Program Studi',
            'nama_prodi' => 'Nama Program Studi',
            'jenjang' => 'Jenjang',
            'status_akreditasi' => 'Status Akreditasi',
            'no_sk_akreditasi' => 'Nomor SK Akreditasi',
            'tanggal_akreditasi_mulai' => 'Tanggal Akreditasi Mulai',
            'tanggal_akreditasi_akhir' => 'Tanggal Akreditasi Akhir',
            'kaprodi' => 'Kaprodi',
            'tahun_berdiri' => 'Tahun Berdiri',
            'gelar_akademik' => 'Gelar Akademik',
            'singkatan_gelar' => 'Singkatan Gelar',
            'sks_lulus' => 'SKS Lulus',
            'status_prodi' => 'Status Prodi',
            'nomor_kaprodi' => 'Nomor Kaprodi',
            'operator' => 'Operator',
            'nomor_operator' => 'Nomor Operator',
            'no_sk_dikti' => 'Nomor SK Dikti',
            'tanggal_sk_dikti' => 'Tanggal SK Dikti',
            'tanggal_berakhir_sk_dikti' => 'Tanggal Berakhir SK Dikti',
            'alamat' => 'Alamat',
            'provinsi_id' => 'Provinsi',
            'kota_id' => 'Kota/Kabupaten',
            'kode_pos' => 'Kode Pos',
            'telepon' => 'Telepon',
            'faximili' => 'Faximili',
            'email' => 'Email',
            'website' => 'Website',
        ];
    }
}
