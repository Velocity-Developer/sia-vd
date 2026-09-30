<?php

namespace App\Http\Controllers\Admin;

use App\Feature;
use App\Http\Controllers\Controller;
use App\Models\Pertemuan;
use App\Models\TahunAkademik;
use Closure;
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
        $tahunAkademiks = TahunAkademik::query()->when($search !== '', fn ($query) => $query->where('tahun', 'like', "%{$search}%")->orWhere('semester', 'like', "%{$search}%"))->orderByDesc('tanggal_mulai')->paginate(10)->withQueryString();

        return Inertia::render('Admin/TahunAkademik', ['tahunAkademiks' => $tahunAkademiks, 'search' => $search]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/TahunAkademikForm', ['tahunAkademik' => null]);
    }

    public function edit(TahunAkademik $tahunAkademik): Response
    {
        return Inertia::render('Admin/TahunAkademikForm', [
            'tahunAkademik' => $tahunAkademik,
            'kelasBerpertemuan' => $tahunAkademik->kelasKuliahs()->whereHas('pertemuans')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new TahunAkademik);

        return to_route('admin.tahun-akademik.index')->with('success', 'Tahun Akademik berhasil ditambahkan.');
    }

    public function update(Request $request, TahunAkademik $tahunAkademik): RedirectResponse
    {
        // Tanggal pertemuan dihitung dari tanggal mulai saat pertemuan dibuat; setelah itu tanggal mulai dikunci
        // agar tanggal pertemuan tidak bergeser diam-diam.
        $mulaiBaru = $request->date('tanggal_mulai')?->toDateString();
        if ($mulaiBaru !== null && $mulaiBaru !== $tahunAkademik->tanggal_mulai?->toDateString()) {
            $kelasBerpertemuan = $tahunAkademik->kelasKuliahs()->whereHas('pertemuans')->count();
            if ($kelasBerpertemuan > 0) {
                throw ValidationException::withMessages(['tanggal_mulai' => "Tanggal mulai tidak bisa diubah karena {$kelasBerpertemuan} kelas sudah punya pertemuan. Ubah jadwal per pertemuan di menu Presensi bila perlu."]);
            }
        }

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
        $keuangan = Feature::aktif('keuangan');
        $validated = $request->validate(
            [
                // Semester mahasiswa dihitung dari tahun pertama dan Ganjil/Genap, jadi formatnya harus pasti.
                'tahun' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/', function (string $attribute, mixed $value, Closure $fail): void {
                    $bagian = array_map('intval', explode('/', (string) $value));
                    if (count($bagian) === 2 && $bagian[1] !== $bagian[0] + 1) {
                        $fail('Tahun kedua harus satu tahun setelah tahun pertama, misalnya 2026/2027.');
                    }
                }, Rule::unique('tahun_akademik', 'tahun')->where('semester', $request->input('semester'))->ignore($model)],
                'semester' => ['required', Rule::in(TahunAkademik::SEMESTER)],
                'tanggal_mulai' => ['required', 'date'],
                'tanggal_akhir' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
                'tanggal_krs_awal' => ['required', 'date'],
                // KRS boleh dibuka sebelum kuliah dimulai, tetapi harus ditutup sebelum semester berakhir.
                'tanggal_krs_akhir' => ['required', 'date', 'after_or_equal:tanggal_krs_awal', 'before_or_equal:tanggal_akhir'],
                // Periode pengajuan cuti untuk semester ini: keduanya diisi atau keduanya kosong (tertutup).
                'tanggal_cuti_awal' => ['nullable', 'date', 'required_with:tanggal_cuti_akhir'],
                'tanggal_cuti_akhir' => ['nullable', 'date', 'required_with:tanggal_cuti_awal', 'after_or_equal:tanggal_cuti_awal', 'before_or_equal:tanggal_akhir'],
                // UAS paling lambat di tanggal akhir, jadi batas input nilai tidak boleh sebelum itu.
                'batas_input_nilai' => ['nullable', 'date', 'after_or_equal:tanggal_akhir'],
                // Tagihan remidi terbit setelah nilai final, jadi batas bayarnya harus sesudah batas input nilai.
                // Selama fitur keuangan mati kolom ini tidak tampil dan nilai lamanya dibiarkan.
                'batas_bayar_remidi' => $keuangan
                    ? ['nullable', 'date', 'after:tanggal_akhir', ...($request->filled('batas_input_nilai') ? ['after:batas_input_nilai'] : [])]
                    : ['exclude'],
                'batas_input_nilai_remidi' => ['nullable', 'date', ...match (true) {
                    $keuangan => $request->filled('batas_bayar_remidi') ? ['after:batas_bayar_remidi'] : [],
                    $request->filled('batas_input_nilai') => ['after:batas_input_nilai'],
                    default => ['after:tanggal_akhir'],
                }],
                'status' => ['boolean'],
            ],
            [
                'after_or_equal' => ':attribute harus sama atau setelah :date.',
                'tanggal_krs_akhir.before_or_equal' => 'Tanggal KRS akhir tidak boleh setelah tanggal akhir semester.',
                'tanggal_cuti_akhir.before_or_equal' => 'Pengajuan cuti harus ditutup paling lambat di tanggal akhir semester.',
                'required_with' => 'Isi tanggal buka dan tutup pengajuan cuti, atau kosongkan keduanya.',
                'batas_input_nilai_remidi.after' => $keuangan
                    ? 'Batas input nilai remidi harus setelah batas bayar remidi.'
                    : 'Batas input nilai remidi harus setelah batas input nilai dan tanggal akhir semester.',
                'batas_bayar_remidi.after' => 'Batas bayar remidi harus setelah tanggal akhir semester dan batas input nilai.',
                'tahun.unique' => 'Tahun akademik dengan tahun dan semester ini sudah ada.',
                'tahun.regex' => 'Format tahun akademik harus seperti 2026/2027.',
                'semester.in' => 'Semester harus Ganjil atau Genap.',
            ],
            [
                'tahun' => 'tahun akademik',
                'semester' => 'semester',
                'tanggal_mulai' => 'tanggal mulai',
                'tanggal_akhir' => 'tanggal akhir',
                'tanggal_krs_awal' => 'tanggal KRS awal',
                'tanggal_krs_akhir' => 'tanggal KRS akhir',
                'tanggal_cuti_awal' => 'tanggal buka pengajuan cuti',
                'tanggal_cuti_akhir' => 'tanggal tutup pengajuan cuti',
                'batas_input_nilai' => 'batas input nilai',
                'batas_bayar_remidi' => 'batas bayar remidi',
                'batas_input_nilai_remidi' => 'batas input nilai remidi',
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
