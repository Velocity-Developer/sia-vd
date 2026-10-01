<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PengaturanMaintenance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceController extends Controller
{
    /**
     * Halaman pengaturan mode maintenance.
     */
    public function edit(): Response
    {
        $pengaturan = PengaturanMaintenance::current();

        return Inertia::render('PengaturanSistem/Maintenance', [
            'pengaturan' => [
                'aktif' => $pengaturan->aktif,
                'untuk_dosen' => $pengaturan->untuk_dosen,
                'untuk_mahasiswa' => $pengaturan->untuk_mahasiswa,
                'pesan' => $pengaturan->pesan,
                // Format isian datetime-local.
                'perkiraan_selesai' => $pengaturan->perkiraan_selesai?->format('Y-m-d\TH:i'),
            ],
            'pesanBawaan' => PengaturanMaintenance::PESAN_BAWAAN,
        ]);
    }

    /**
     * Simpan pengaturan mode maintenance.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'aktif' => ['required', 'boolean'],
            'untuk_dosen' => ['required', 'boolean'],
            'untuk_mahasiswa' => ['required', 'boolean'],
            'pesan' => ['nullable', 'string', 'max:500'],
            'perkiraan_selesai' => ['nullable', 'date'],
        ], attributes: [
            'aktif' => 'Status maintenance',
            'untuk_dosen' => 'Dosen',
            'untuk_mahasiswa' => 'Mahasiswa',
            'pesan' => 'Pesan',
            'perkiraan_selesai' => 'Perkiraan selesai',
        ]);

        if ($data['aktif'] && ! $data['untuk_dosen'] && ! $data['untuk_mahasiswa']) {
            return back()->withErrors(['untuk' => 'Pilih minimal satu jenis pengguna yang terkena maintenance.']);
        }

        $data['pesan'] = filled($data['pesan'] ?? null) ? trim($data['pesan']) : null;
        $data['updated_by'] = $request->user()->id;
        PengaturanMaintenance::current()->update($data);

        return back()->with('success', $data['aktif']
            ? 'Mode maintenance aktif. Admin tetap bisa masuk seperti biasa.'
            : 'Pengaturan maintenance disimpan. Mode maintenance tidak aktif.');
    }
}
