<?php

namespace App\Http\Controllers\Dev;

use App\Feature;
use App\Http\Controllers\Controller;
use App\Models\LogPengaturanFitur;
use App\Models\PengaturanFitur;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel developer: menyalakan/mematikan fitur per klien lewat override database (lihat App\Feature).
 */
class FiturController extends Controller
{
    public function index(): Response
    {
        $override = PengaturanFitur::semua();
        $label = fn (string $nama): string => (string) config("client.fitur.{$nama}.label", $nama);
        $daftar = collect((array) config('client.fitur'));

        return Inertia::render('Dev/Fitur', [
            'fitur' => $daftar->map(fn (array $f, string $nama): array => [
                'nama' => $nama,
                'label' => $label($nama),
                'keterangan' => $f['keterangan'] ?? null,
                'bawaan' => filter_var($f['default'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'terkunci' => Feature::terkunci($nama),
                'override' => $override[$nama] ?? null,
                'aktif' => Feature::aktifDengan($nama, $override),
                'butuh' => collect((array) ($f['butuh'] ?? []))->map($label)->values()->all(),
                'dibutuhkan' => $daftar->filter(fn (array $lain): bool => in_array($nama, (array) ($lain['butuh'] ?? []), true))->keys()->map($label)->values()->all(),
            ])->values()->all(),
            'log' => LogPengaturanFitur::query()->with('pengubah:id,name')->latest('id')->limit(20)->get()
                ->map(fn (LogPengaturanFitur $l): array => [
                    'id' => $l->id,
                    'fitur' => $label($l->nama),
                    'lama' => $l->aktif_lama,
                    'baru' => $l->aktif_baru,
                    'oleh' => $l->pengubah?->name,
                    'waktu' => $l->created_at?->toIso8601String(),
                ])->all(),
        ]);
    }

    /**
     * `aktif` true/false = override, null = kembali ke bawaan config.
     */
    public function update(Request $request, string $nama): RedirectResponse
    {
        abort_unless(is_array(config("client.fitur.{$nama}")), 404);
        abort_if(Feature::terkunci($nama), 403, 'Fitur ini dikunci developer lewat LOCK_* dan tidak bisa diubah dari panel.');

        $data = $request->validate(['aktif' => ['present', 'nullable', 'boolean']]);
        $aktif = $data['aktif'] === null ? null : (bool) $data['aktif'];

        PengaturanFitur::atur($nama, $aktif, $request->user());

        $label = config("client.fitur.{$nama}.label", $nama);

        return back()->with('success', match ($aktif) {
            null => "{$label} kembali mengikuti bawaan config.",
            true => "{$label} dinyalakan.",
            false => "{$label} dimatikan.",
        });
    }
}
