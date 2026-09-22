<?php

namespace App\Http\Controllers\Kelas;

use App\AllowedUpload;
use App\Http\Controllers\Concerns\KontenKelas;
use App\Http\Controllers\Controller;
use App\Models\KelasKuliah;
use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TugasController extends Controller
{
    use KontenKelas;

    public function create(KelasKuliah $kelasKuliah): Response
    {
        $this->pastikanAksesKelas($kelasKuliah);
        $kelasKuliah->load(['mataKuliah', 'dosen.user']);

        return Inertia::render('Kelas/TugasForm', [
            'peran' => $this->peran(),
            'kelasKuliah' => $kelasKuliah,
            'tugas' => null,
        ]);
    }

    public function store(Request $request, KelasKuliah $kelasKuliah): RedirectResponse
    {
        $this->pastikanAksesKelas($kelasKuliah);
        $data = $request->validate($this->rules(), $this->messages(), $this->attributes());

        $data['file'] = $this->storeFiles($request);
        $data['kelas_id'] = $kelasKuliah->id;
        $data['uploaded_by'] = $request->user()->id;
        Tugas::create($data);

        return $this->keKelas($kelasKuliah)->with('tugas_success', 'Tugas berhasil ditambahkan.');
    }

    public function show(KelasKuliah $kelasKuliah, Tugas $tugas): Response
    {
        $this->ensureScoped($kelasKuliah, $tugas);
        $kelasKuliah->load('mataKuliah');
        $tugas->load(['uploader:id,name', 'pengumpulanTugas.mahasiswa:id,user_id,nim,prodi_id', 'pengumpulanTugas.mahasiswa.user:id,name', 'pengumpulanTugas.mahasiswa.prodi:id,nama_prodi']);

        return Inertia::render('Kelas/TugasShow', [
            'peran' => $this->peran(),
            'kelasKuliah' => $kelasKuliah,
            'tugas' => $tugas,
        ]);
    }

    public function updateSubmissionGrade(Request $request, KelasKuliah $kelasKuliah, Tugas $tugas, PengumpulanTugas $pengumpulanTugas): RedirectResponse
    {
        $this->ensureScoped($kelasKuliah, $tugas);
        abort_unless($pengumpulanTugas->tugas_id === $tugas->id, 404);
        $pengumpulanTugas->update($request->validate(['nilai' => ['nullable', 'numeric', 'min:0', 'max:100']]));

        return back()->with('success', 'Nilai berhasil diperbarui.');
    }

    public function edit(KelasKuliah $kelasKuliah, Tugas $tugas): Response
    {
        $this->ensureScoped($kelasKuliah, $tugas);
        $kelasKuliah->load(['mataKuliah', 'dosen.user']);

        return Inertia::render('Kelas/TugasForm', [
            'peran' => $this->peran(),
            'kelasKuliah' => $kelasKuliah,
            'tugas' => $tugas,
        ]);
    }

    public function update(Request $request, KelasKuliah $kelasKuliah, Tugas $tugas): RedirectResponse
    {
        $this->ensureScoped($kelasKuliah, $tugas);
        $data = $request->validate($this->rules(), $this->messages(), $this->attributes());

        $newFiles = $this->storeFiles($request);

        $kept = $request->input('kept_files', []);
        $kept = is_array($kept) ? array_values(array_filter($kept, fn ($v): bool => is_string($v) && $v !== '')) : [];
        $kept = array_values(array_intersect($kept, $this->fileList($tugas)));

        if ($request->hasFile('file') || $request->has('kept_files')) {
            $this->deleteFiles(array_diff($this->fileList($tugas), $kept));
            $data['file'] = array_values([...$kept, ...$newFiles]);
        } else {
            unset($data['file']);
        }

        $tugas->update($data);

        return $this->keKelas($kelasKuliah)->with('tugas_success', 'Tugas berhasil diperbarui.');
    }

    public function duplicate(Request $request, KelasKuliah $kelasKuliah, Tugas $tugas): RedirectResponse
    {
        $this->ensureScoped($kelasKuliah, $tugas);
        $targets = $this->kelasTujuanDuplikasi($request, $kelasKuliah);
        foreach ($targets as $target) {
            $copy = $tugas->replicate();
            $copy->kelas_id = $target->id;
            $copy->file = AllowedUpload::salinBerkas($this->fileList($tugas));
            $copy->uploaded_by = $request->user()->id;
            $copy->save();
        }

        return $this->keKelas($kelasKuliah)->with('tugas_success', 'Tugas berhasil diduplikasi ke: '.$targets->map(fn (KelasKuliah $target): string => $target->kode_kelas)->join(', ').'.');
    }

    public function destroy(KelasKuliah $kelasKuliah, Tugas $tugas): RedirectResponse
    {
        $this->ensureScoped($kelasKuliah, $tugas);

        try {
            $this->deleteFiles($this->fileList($tugas));
            $tugas->delete();
        } catch (Throwable) {
            return $this->keKelas($kelasKuliah)->with('tugas_error', 'Tugas gagal dihapus.');
        }

        return $this->keKelas($kelasKuliah)->with('tugas_success', 'Tugas berhasil dihapus.');
    }

    private function ensureScoped(KelasKuliah $kelasKuliah, Tugas $tugas): void
    {
        $this->pastikanAksesKelas($kelasKuliah);
        abort_unless($tugas->kelas_id === $kelasKuliah->id, 404);
    }

    /**
     * @return array<int, string>
     */
    private function storeFiles(Request $request): array
    {
        if (! $request->hasFile('file')) {
            return [];
        }

        $paths = [];
        foreach ((array) $request->file('file') as $uploaded) {
            if (! $uploaded instanceof UploadedFile) {
                continue;
            }

            if ($uploaded->isValid()) {
                $paths[] = $this->storeUploadedFile($uploaded);
            }
        }

        return $paths;
    }

    private function storeUploadedFile(UploadedFile $uploaded): string
    {
        $extension = strtolower($uploaded->getClientOriginalExtension());
        $base = pathinfo($uploaded->getClientOriginalName(), PATHINFO_FILENAME);
        $sanitized = Str::slug($base, '_');
        $sanitized = substr($sanitized !== '' ? $sanitized : 'file', 0, 100);
        $suffix = Str::lower(Str::random(6));
        $filename = $sanitized.'-'.$suffix.($extension !== '' ? '.'.$extension : '');

        while (Storage::disk(AllowedUpload::DISK)->exists('tugas/'.$filename)) {
            $suffix = Str::lower(Str::random(6));
            $filename = $sanitized.'-'.$suffix.($extension !== '' ? '.'.$extension : '');
        }

        return $uploaded->storeAs('tugas', $filename, AllowedUpload::DISK);
    }

    /**
     * @return array<int, string>
     */
    private function fileList(Tugas $tugas): array
    {
        $files = $tugas->file;

        if (is_string($files)) {
            $decoded = json_decode($files, true);
            $files = is_array($decoded) ? $decoded : [$files];
        }

        if (! is_array($files)) {
            return [];
        }

        return array_values(array_filter($files, fn ($v): bool => is_string($v) && $v !== ''));
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function deleteFiles(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk(AllowedUpload::DISK)->delete(array_values($paths));
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'judul_tugas' => ['required', 'string', 'max:255'],
            'tenggat_waktu' => ['nullable', 'date'],
            'file' => ['nullable', 'array', 'max:5'],
            'file.*' => ['file', 'max:10240', AllowedUpload::rule()],
            'kept_files' => ['nullable', 'array'],
            'kept_files.*' => ['string'],
            'catatan' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'date' => ':attribute harus berupa tanggal yang valid.',
            'file' => ':attribute harus berupa berkas.',
            'extensions' => AllowedUpload::message(),
            'max.string' => ':attribute maksimal :max karakter.',
            'max.file' => ':attribute maksimal :max kilobita.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'judul_tugas' => 'Judul Tugas',
            'tenggat_waktu' => 'Tenggat Waktu',
            'file' => 'File',
            'catatan' => 'Catatan',
        ];
    }
}
