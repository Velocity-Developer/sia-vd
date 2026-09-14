<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PengumpulanTugasController extends Controller
{
    public function show(Request $request, Tugas $tugas): Response
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);
        $this->ensureAccess($tugas, $mahasiswa->id);
        $tugas->load(['kelasKuliah.mataKuliah', 'uploader:id,name']);
        $submission = $tugas->pengumpulanTugas()->where('mahasiswa_id', $mahasiswa->id)->first();

        return Inertia::render('Mahasiswa/TugasShow', compact('tugas', 'submission'));
    }

    public function store(Request $request, Tugas $tugas): RedirectResponse
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);
        $this->ensureAccess($tugas, $mahasiswa->id);

        if ($tugas->tenggat_waktu !== null && $tugas->tenggat_waktu->isPast()) {
            return to_route('mahasiswa.tugas.show', $tugas)->with('error', 'Tenggat waktu telah berakhir. Jawaban tidak dapat diunggah lagi.');
        }

        $request->validate([
            'file_jawaban' => ['required', 'array', 'min:1', 'max:5'],
            'file_jawaban.*' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip,jpg,jpeg,png'],
        ]);

        $old = $tugas->pengumpulanTugas()->where('mahasiswa_id', $mahasiswa->id)->first();
        $paths = collect($request->file('file_jawaban'))->filter(fn ($file): bool => $file instanceof UploadedFile && $file->isValid())->map(fn (UploadedFile $file): string => $this->storeFile($file))->values()->all();
        abort_if($paths === [], 422);
        if ($old) {
            Storage::disk('public')->delete($old->file_jawaban ?? []);
        }
        PengumpulanTugas::updateOrCreate(['tugas_id' => $tugas->id, 'mahasiswa_id' => $mahasiswa->id], ['file_jawaban' => $paths]);

        return to_route('mahasiswa.tugas.show', $tugas)->with('success', 'Jawaban berhasil dikumpulkan.');
    }

    private function ensureAccess(Tugas $tugas, int $mahasiswaId): void
    {
        abort_unless($tugas->kelasKuliah()->whereHas('krs', fn ($query) => $query->where('mahasiswa_id', $mahasiswaId))->exists(), 403);
    }

    private function storeFile(UploadedFile $file): string
    {
        $base = substr(Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), '_') ?: 'file', 0, 100);
        $extension = $file->getClientOriginalExtension();
        $filename = $base.'-'.Str::lower(Str::random(6)).($extension ? '.'.$extension : '');

        return $file->storeAs('pengumpulan-tugas', $filename, 'public');
    }
}
