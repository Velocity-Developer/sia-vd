<?php

namespace App\Http\Controllers\Admin;

use App\AllowedUpload;
use App\Http\Controllers\Controller;
use App\Models\KelasKuliah;
use App\Models\Materi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MateriController extends Controller
{
    public function create(KelasKuliah $kelasKuliah): Response
    {
        $kelasKuliah->load(['mataKuliah', 'dosen.user']);

        return Inertia::render('Admin/MateriForm', [
            'kelasKuliah' => $kelasKuliah,
            'materi' => null,
        ]);
    }

    public function store(Request $request, KelasKuliah $kelasKuliah): RedirectResponse
    {
        $data = $request->validate($this->rules(), $this->messages(), $this->attributes());

        $data['file'] = $this->storeFiles($request);
        $data['kelas_id'] = $kelasKuliah->id;
        $data['uploaded_by'] = $request->user()->id;
        Materi::create($data);

        return to_route('admin.kelas-kuliah.show', $kelasKuliah)->with('materi_success', 'Materi berhasil ditambahkan.');
    }

    public function edit(KelasKuliah $kelasKuliah, Materi $materi): Response
    {
        $this->ensureScoped($kelasKuliah, $materi);
        $kelasKuliah->load(['mataKuliah', 'dosen.user']);

        return Inertia::render('Admin/MateriForm', [
            'kelasKuliah' => $kelasKuliah,
            'materi' => $materi,
        ]);
    }

    public function update(Request $request, KelasKuliah $kelasKuliah, Materi $materi): RedirectResponse
    {
        $this->ensureScoped($kelasKuliah, $materi);
        $data = $request->validate($this->rules(), $this->messages(), $this->attributes());

        $newFiles = $this->storeFiles($request);

        $kept = $request->input('kept_files', []);
        $kept = is_array($kept) ? array_values(array_filter($kept, fn ($v): bool => is_string($v) && $v !== '')) : [];
        $kept = array_values(array_intersect($kept, $this->fileList($materi)));

        if ($request->hasFile('file') || $request->has('kept_files')) {
            $this->deleteFiles(array_diff($this->fileList($materi), $kept));
            $data['file'] = array_values([...$kept, ...$newFiles]);
        } else {
            unset($data['file']);
        }

        $materi->update($data);

        return to_route('admin.kelas-kuliah.show', $kelasKuliah)->with('materi_success', 'Materi berhasil diperbarui.');
    }

    public function duplicate(Request $request, KelasKuliah $kelasKuliah, Materi $materi): RedirectResponse
    {
        $this->ensureScoped($kelasKuliah, $materi);
        $validated = $request->validate(['target_ids' => ['required', 'array', 'min:1'], 'target_ids.*' => ['integer']]);
        $ids = $validated['target_ids'];
        $targets = KelasKuliah::whereIn('id', $ids)->whereKeyNot($kelasKuliah->id)->get();
        abort_if($targets->count() !== count(array_unique($ids)), 404);

        foreach ($targets as $target) {
            $copy = $materi->replicate();
            $copy->kelas_id = $target->id;
            $copy->uploaded_by = $request->user()->id;
            $copy->save();
        }

        return to_route('admin.kelas-kuliah.show', $kelasKuliah)->with('materi_success', 'Materi berhasil diduplikasi ke: '.$targets->map(fn (KelasKuliah $target): string => $target->kode_kelas)->join(', ').'.');
    }

    public function destroy(KelasKuliah $kelasKuliah, Materi $materi): RedirectResponse
    {
        $this->ensureScoped($kelasKuliah, $materi);

        try {
            $this->deleteFiles($this->fileList($materi));
            $materi->delete();
        } catch (Throwable) {
            return to_route('admin.kelas-kuliah.show', $kelasKuliah)->with('materi_error', 'Materi gagal dihapus.');
        }

        return to_route('admin.kelas-kuliah.show', $kelasKuliah)->with('materi_success', 'Materi berhasil dihapus.');
    }

    private function ensureScoped(KelasKuliah $kelasKuliah, Materi $materi): void
    {
        abort_unless($materi->kelas_id === $kelasKuliah->id, 404);
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

        while (Storage::disk(AllowedUpload::DISK)->exists('materis/'.$filename)) {
            $suffix = Str::lower(Str::random(6));
            $filename = $sanitized.'-'.$suffix.($extension !== '' ? '.'.$extension : '');
        }

        return $uploaded->storeAs('materis', $filename, AllowedUpload::DISK);
    }

    /**
     * @return array<int, string>
     */
    private function fileList(Materi $materi): array
    {
        $files = $materi->file;

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
            'judul_materi' => ['required', 'string', 'max:255'],
            'pertemuan_ke' => ['required', 'integer', 'min:1', 'max:32'],
            'jenis' => ['required', 'in:Materi,Pengumuman'],
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
            'integer' => ':attribute harus berupa angka.',
            'file' => ':attribute harus berupa berkas.',
            'extensions' => AllowedUpload::message(),
            'max.string' => ':attribute maksimal :max karakter.',
            'max.file' => ':attribute maksimal :max kilobita.',
            'min' => ':attribute minimal :min.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'judul_materi' => 'Judul Materi',
            'pertemuan_ke' => 'Pertemuan Ke',
            'jenis' => 'Jenis',
            'file' => 'File',
            'catatan' => 'Catatan',
        ];
    }
}
