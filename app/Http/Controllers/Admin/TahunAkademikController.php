<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TahunAkademik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TahunAkademikController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $tahunAkademiks = TahunAkademik::query()->when($search !== '', fn ($query) => $query->where('tahun', 'like', "%{$search}%")->orWhere('semester', 'like', "%{$search}%"))->orderByDesc('tahun')->orderBy('semester')->paginate(10)->withQueryString();

        return Inertia::render('Admin/TahunAkademik', ['tahunAkademiks' => $tahunAkademiks, 'search' => $search]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/TahunAkademikForm', ['tahunAkademik' => null]);
    }

    public function edit(TahunAkademik $tahunAkademik): Response
    {
        return Inertia::render('Admin/TahunAkademikForm', ['tahunAkademik' => $tahunAkademik]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new TahunAkademik);

        return to_route('admin.tahun-akademik.index')->with('success', 'Tahun Akademik berhasil ditambahkan.');
    }

    public function update(Request $request, TahunAkademik $tahunAkademik): RedirectResponse
    {
        $this->save($request, $tahunAkademik);

        return to_route('admin.tahun-akademik.index')->with('success', 'Tahun Akademik berhasil diperbarui.');
    }

    public function destroy(TahunAkademik $tahunAkademik): RedirectResponse
    {
        if ($tahunAkademik->kelasKuliahs()->exists()) {
            return to_route('admin.tahun-akademik.index')->with('error', 'Tahun Akademik tidak dapat dihapus karena sudah memiliki kelas kuliah.');
        }

        try {
            $tahunAkademik->delete();
        } catch (Throwable) {
            return to_route('admin.tahun-akademik.index')->with('error', 'Tahun Akademik gagal dihapus.');
        }

        return to_route('admin.tahun-akademik.index')->with('success', 'Tahun Akademik berhasil dihapus.');
    }

    private function save(Request $request, TahunAkademik $model): void
    {
        $validated = $request->validate(
            [
                'tahun' => ['required', 'string', 'max:20', Rule::unique('tahun_akademik', 'tahun')->where('semester', $request->input('semester'))->ignore($model)],
                'semester' => ['required', 'string', 'max:20'],
                'tanggal_mulai' => ['required', 'date'],
                'tanggal_akhir' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
                'tanggal_krs_awal' => ['required', 'date'],
                'tanggal_krs_akhir' => ['required', 'date', 'after_or_equal:tanggal_krs_awal'],
                'status' => ['boolean'],
            ],
            [
                'required' => ':attribute wajib diisi.',
                'date' => ':attribute harus berupa tanggal yang valid.',
                'after_or_equal' => ':attribute harus sama atau setelah :date.',
                'string' => ':attribute harus berupa teks.',
                'max' => ':attribute maksimal :max karakter.',
                'tahun.unique' => 'Tahun akademik dengan tahun dan semester ini sudah ada.',
            ],
            [
                'tahun' => 'tahun akademik',
                'semester' => 'semester',
                'tanggal_mulai' => 'tanggal mulai',
                'tanggal_akhir' => 'tanggal akhir',
                'tanggal_krs_awal' => 'tanggal KRS awal',
                'tanggal_krs_akhir' => 'tanggal KRS akhir',
                'status' => 'status',
            ],
        );

        if (($validated['status'] ?? false) && TahunAkademik::where('status', true)->when($model->exists, fn ($query) => $query->whereKeyNot($model->getKey()))->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Sudah ada tahun akademik yang aktif.',
            ]);
        }

        $model->fill($validated)->save();
    }
}
