<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cmb;
use App\Models\PengaturanPmb;
use App\Pmb\OpsiPmb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PendaftarPmbController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $periodeId = $request->integer('periode') ?: null;
        $status = $request->string('status')->toString();

        $pendaftar = Cmb::query()
            ->with(['periode:id,kode', 'programStudi:id,nama_prodi,jenjang'])
            ->when($periodeId, fn ($query) => $query->where('pengaturan_pmb_id', $periodeId))
            ->when($status === 'menunggu', fn ($query) => $query->whereNull('status_pendaftaran'))
            ->when(array_key_exists($status, OpsiPmb::STATUS_PENDAFTARAN), fn ($query) => $query->where('status_pendaftaran', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('nama', 'like', "%{$search}%")
                ->orWhere('nomor_pendaftaran', 'like', "%{$search}%")
                ->orWhere('nik', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(15, ['id', 'pengaturan_pmb_id', 'program_studi_id', 'nomor_pendaftaran', 'nama', 'hp', 'nilai', 'status_pendaftaran', 'created_at'])
            ->withQueryString();

        return Inertia::render('Admin/PendaftarPmb', [
            'pendaftar' => $pendaftar,
            'periode' => PengaturanPmb::query()->orderByDesc('tahun_angkatan')->orderByDesc('tanggal_buka')->get(['id', 'kode', 'tahun_angkatan']),
            'filter' => ['search' => $search, 'periode' => $periodeId, 'status' => $status],
        ]);
    }

    public function show(Cmb $cmb): Response
    {
        $cmb->load(['periode', 'agama:id,nama', 'kecamatan:id,kode,nama', 'programStudi:id,nama_prodi,jenjang']);

        return Inertia::render('Admin/PendaftarPmbShow', [
            'pendaftar' => $cmb,
            'opsi' => OpsiPmb::untukForm(),
        ]);
    }

    public function update(Request $request, Cmb $cmb): RedirectResponse
    {
        $data = $request->validate([
            'nilai' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status_pendaftaran' => ['nullable', Rule::in(array_keys(OpsiPmb::STATUS_PENDAFTARAN))],
        ], [], ['nilai' => 'Nilai', 'status_pendaftaran' => 'Status pendaftaran']);

        $cmb->forceFill($data)->save();

        return back()->with('success', 'Hasil seleksi berhasil disimpan.');
    }

    public function destroy(Cmb $cmb): RedirectResponse
    {
        $cmb->delete();

        return to_route('admin.pendaftar-pmb.index')->with('success', 'Data pendaftar berhasil dihapus.');
    }
}
