<?php

namespace App\Http\Controllers\Admin;

use App\AllowedUpload;
use App\Http\Controllers\Controller;
use App\Models\InfoKuliah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InfoKuliahController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/InfoKuliah', ['infoKuliahs' => InfoKuliah::with('uploader:id,name')->latest()->paginate(10)]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/InfoKuliahForm', ['infoKuliah' => null]);
    }

    public function edit(InfoKuliah $infoKuliah): Response
    {
        return Inertia::render('Admin/InfoKuliahForm', ['infoKuliah' => $infoKuliah]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['file'] = $this->storeFile($request->file('file'));
        $data['uploaded_by'] = $request->user()->id;
        InfoKuliah::create($data);

        return to_route('admin.info-kuliah.index')->with('success', 'Informasi kuliah berhasil ditambahkan.');
    }

    public function update(Request $request, InfoKuliah $infoKuliah): RedirectResponse
    {
        $data = $request->validate([
            'information' => ['required', 'string'],
            'file' => ['nullable', 'file', 'max:10240', ...AllowedUpload::rules()],
        ], ['extensions' => AllowedUpload::message(), 'mimes' => AllowedUpload::messageIsi()], ['information' => 'informasi', 'file' => 'file']);
        if ($request->hasFile('file')) {
            Storage::disk(AllowedUpload::DISK)->delete($infoKuliah->file);
            $data['file'] = $this->storeFile($request->file('file'));
        } else {
            unset($data['file']);
        }
        $infoKuliah->update($data);

        return to_route('admin.info-kuliah.index')->with('success', 'Informasi kuliah berhasil diperbarui.');
    }

    public function destroy(InfoKuliah $infoKuliah): RedirectResponse
    {
        Storage::disk(AllowedUpload::DISK)->delete($infoKuliah->file);
        $infoKuliah->delete();

        return to_route('admin.info-kuliah.index')->with('success', 'Informasi kuliah berhasil dihapus.');
    }

    private function storeFile(UploadedFile $file): string
    {
        return $file->storeAs('info-kuliahs', Str::random(40).'.'.strtolower($file->getClientOriginalExtension()), AllowedUpload::DISK);
    }

    private function validated(Request $request, bool $fileRequired = true): array
    {
        return $request->validate([
            'information' => ['required', 'string'],
            'file' => [$fileRequired ? 'required' : 'nullable', 'file', 'max:10240', ...AllowedUpload::rules()],
        ], ['extensions' => AllowedUpload::message(), 'mimes' => AllowedUpload::messageIsi()], ['information' => 'informasi', 'file' => 'file']);
    }
}
