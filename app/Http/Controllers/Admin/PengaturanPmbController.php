<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PengaturanPmb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PengaturanPmbController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $periode = PengaturanPmb::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('kode', 'like', "%{$search}%")->orWhere('tahun_angkatan', 'like', "%{$search}%")))
            ->orderByDesc('tahun_angkatan')
            ->orderByDesc('tanggal_buka')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/PengaturanPmb', ['periode' => $periode, 'search' => $search]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/PengaturanPmbForm', ['pengaturanPmb' => null]);
    }

    public function edit(PengaturanPmb $pengaturanPmb): Response
    {
        return Inertia::render('Admin/PengaturanPmbForm', ['pengaturanPmb' => $pengaturanPmb]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new PengaturanPmb);

        return to_route('admin.periode-pmb.index')->with('success', 'Periode PMB berhasil ditambahkan.');
    }

    public function update(Request $request, PengaturanPmb $pengaturanPmb): RedirectResponse
    {
        $this->save($request, $pengaturanPmb);

        return to_route('admin.periode-pmb.index')->with('success', 'Periode PMB berhasil diperbarui.');
    }

    public function destroy(PengaturanPmb $pengaturanPmb): RedirectResponse
    {
        try {
            $pengaturanPmb->delete();
        } catch (Throwable) {
            return to_route('admin.periode-pmb.index')->with('error', 'Periode PMB gagal dihapus.');
        }

        return to_route('admin.periode-pmb.index')->with('success', 'Periode PMB berhasil dihapus.');
    }

    private function save(Request $request, PengaturanPmb $model): void
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('pengaturan_pmb', 'kode')->ignore($model)],
            'tahun_angkatan' => ['required', 'integer', 'digits:4', 'min:2000', 'max:2100'],
            'tanggal_buka' => ['required', 'date'],
            'tanggal_tutup' => ['required', 'date', 'after_or_equal:tanggal_buka'],
            'tanggal_usm_mulai' => ['required', 'date'],
            'tanggal_usm_selesai' => ['required', 'date', 'after_or_equal:tanggal_usm_mulai'],
            'tanggal_her' => ['required', 'date'],
            'nilai_minimal' => ['required', 'numeric', 'min:0', 'max:100'],
            'kapasitas' => ['required', 'integer', 'min:1', 'max:100000'],
            'biaya_pendaftaran' => ['required', 'integer', 'min:0', 'max:9999999999'],
            'tanggal_pembayaran_mulai' => ['required', 'date'],
            'tanggal_pembayaran_selesai' => ['required', 'date', 'after_or_equal:tanggal_pembayaran_mulai'],
            // Pendaftar tidak memilih periode, jadi rentang tanggal periode yang dibuka tidak boleh bertumpuk.
            'is_open' => ['boolean', Rule::prohibitedIf(fn (): bool => $request->boolean('is_open')
                && $request->filled(['tanggal_buka', 'tanggal_tutup'])
                && PengaturanPmb::query()
                    ->where('is_open', true)
                    ->whereKeyNot($model->getKey())
                    ->whereDate('tanggal_buka', '<=', $request->date('tanggal_tutup'))
                    ->whereDate('tanggal_tutup', '>=', $request->date('tanggal_buka'))
                    ->exists())],
        ], [
            'is_open.prohibited' => 'Sudah ada periode lain yang dibuka pada rentang tanggal ini. Formulir PMB hanya memakai satu periode aktif.',
            'tanggal_tutup.after_or_equal' => 'Tanggal tutup harus sama atau setelah tanggal buka.',
            'tanggal_usm_selesai.after_or_equal' => 'Tanggal USM selesai harus sama atau setelah tanggal USM mulai.',
            'tanggal_pembayaran_selesai.after_or_equal' => 'Tanggal pembayaran selesai harus sama atau setelah tanggal pembayaran mulai.',
        ], [
            'kode' => 'Kode',
            'tahun_angkatan' => 'Tahun angkatan',
            'tanggal_buka' => 'Tanggal buka',
            'tanggal_tutup' => 'Tanggal tutup',
            'tanggal_usm_mulai' => 'Tanggal USM mulai',
            'tanggal_usm_selesai' => 'Tanggal USM selesai',
            'tanggal_her' => 'Tanggal her-registrasi',
            'nilai_minimal' => 'Nilai minimal',
            'kapasitas' => 'Kapasitas',
            'biaya_pendaftaran' => 'Biaya pendaftaran',
            'tanggal_pembayaran_mulai' => 'Tanggal pembayaran mulai',
            'tanggal_pembayaran_selesai' => 'Tanggal pembayaran selesai',
        ]);
        $model->fill($data)->save();
    }
}
