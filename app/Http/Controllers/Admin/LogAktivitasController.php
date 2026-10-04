<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogAktivitas;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar log aktivitas (baca saja): siapa mengubah data apa dan kapan, dengan rincian kolom lama → baru.
 */
class LogAktivitasController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->validate([
            'dari' => ['nullable', 'date'],
            'sampai' => ['nullable', 'date'],
            'aksi' => ['nullable', Rule::in(array_keys(LogAktivitas::AKSI))],
            'objek' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $cari = trim((string) ($filter['search'] ?? ''));

        $log = LogAktivitas::query()
            ->with('user:id,name,username')
            ->when($filter['dari'] ?? null, fn ($q, string $t) => $q->where('created_at', '>=', $t.' 00:00:00'))
            ->when($filter['sampai'] ?? null, fn ($q, string $t) => $q->where('created_at', '<=', $t.' 23:59:59'))
            ->when($filter['aksi'] ?? null, fn ($q, string $a) => $q->where('aksi', $a))
            ->when($filter['objek'] ?? null, fn ($q, string $o) => $q->where('objek_tipe', $o))
            ->when($cari !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('label', 'like', "%{$cari}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$cari}%")->orWhere('username', 'like', "%{$cari}%"))))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (LogAktivitas $item): array => [
                'id' => $item->id,
                'waktu' => $item->created_at?->toIso8601String(),
                'pengguna' => $item->user?->name ?? '(akun dihapus)',
                'username' => $item->user?->username,
                'aksi' => $item->aksi,
                'objek' => $item->objek_tipe ? Str::headline($item->objek_tipe) : null,
                'objek_id' => $item->objek_id,
                'label' => $item->label,
                'perubahan' => $item->perubahan ?? [],
                'ip' => $item->ip,
                'rute' => $item->rute,
            ]);

        return Inertia::render('Admin/LogAktivitas', [
            'log' => $log,
            'filter' => [...array_fill_keys(['dari', 'sampai', 'aksi', 'objek'], null), ...$filter, 'search' => $cari],
            'aksiOptions' => LogAktivitas::AKSI,
            'objekOptions' => LogAktivitas::query()->whereNotNull('objek_tipe')->distinct()->orderBy('objek_tipe')->pluck('objek_tipe')
                ->map(fn (string $tipe): array => ['id' => $tipe, 'name' => Str::headline($tipe)]),
        ]);
    }
}
