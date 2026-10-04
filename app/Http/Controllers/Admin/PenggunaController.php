<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\LingkupProdi;
use App\Models\Role;
use App\Models\User;
use App\UserType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menu Tools: Create User (satu pintu untuk membuat akun Prodi, Dosen, atau Mahasiswa)
 * dan Data Pengguna (semua akun dalam satu daftar). Form dan aksi per akun tetap memakai
 * UserController (rute admin.users.{dosen,mahasiswa,karyawan}.*), jadi aturan simpannya satu.
 */
class PenggunaController extends Controller
{
    /**
     * Jenis akun → [rute per jenis UserController, jenis pengguna, label, keterangan, izin].
     * Prodi adalah akun Admin/Karyawan dengan role Prodi.
     */
    private const JENIS = [
        'prodi' => ['karyawan', UserType::Admin, 'Prodi', 'Akun program studi; data yang dikelolanya dibatasi ke satu prodi.', 'admin.users.karyawan'],
        'dosen' => ['dosen', UserType::Dosen, 'Dosen', 'Akun dosen pengampu dan penasehat akademik, sekaligus data dosennya.', 'admin.users.dosen'],
        'mahasiswa' => ['mahasiswa', UserType::Mahasiswa, 'Mahasiswa', 'Akun mahasiswa beserta biodata, prodi, dan dosen walinya.', 'admin.users.mahasiswa'],
        'karyawan' => ['karyawan', UserType::Admin, 'Karyawan', 'Akun staf/admin; menu yang terbuka mengikuti role yang dipilih.', 'admin.users.karyawan'],
    ];

    public function buat(Request $request): Response
    {
        // Create User mengikuti konsep Yapika: hanya Prodi, Dosen, Mahasiswa (akun Karyawan tetap tampil di Data Pengguna).
        $pilihan = collect($this->jenisDiizinkan($request->user()))
            ->except('karyawan')
            ->map(fn (array $jenis, string $kunci): array => [
                'jenis' => $kunci,
                'label' => $jenis[2],
                'keterangan' => $jenis[3],
                'href' => route('admin.users.'.$jenis[0].'.create', $kunci === 'prodi' ? ['role' => LingkupProdi::ROLE] : []),
            ])->values()->all();

        return Inertia::render('Admin/BuatPengguna', ['pilihan' => $pilihan]);
    }

    public function index(Request $request): Response
    {
        $diizinkan = $this->jenisDiizinkan($request->user());
        $jenis = $request->string('jenis')->toString();
        $jenis = isset($diizinkan[$jenis]) ? $jenis : null;
        $search = $request->string('search')->trim()->toString();
        $tampilDeveloper = $request->user()->isDeveloper();

        $users = User::query()
            ->with(['role:id,name,slug,user_type', 'adminProfile:id,user_id,nomor_induk', 'dosenProfile:id,user_id,nidn', 'mahasiswaProfile:id,user_id,nim'])
            ->whereHas('role', function (Builder $role) use ($diizinkan, $jenis, $tampilDeveloper): void {
                $role->when(! $tampilDeveloper, fn (Builder $q) => $q->tanpaDeveloper());
                if ($jenis !== null) {
                    $this->saringJenis($role, $jenis);

                    return;
                }
                $role->where(function (Builder $q) use ($diizinkan): void {
                    foreach (array_keys($diizinkan) as $kunci) {
                        $q->orWhere(fn (Builder $r) => $this->saringJenis($r, $kunci));
                    }
                });
            })
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhereHas('adminProfile', fn (Builder $p) => $p->where('nomor_induk', 'like', "%{$search}%"))
                ->orWhereHas('dosenProfile', fn (Builder $p) => $p->where('nidn', 'like', "%{$search}%"))
                ->orWhereHas('mahasiswaProfile', fn (Builder $p) => $p->where('nim', 'like', "%{$search}%"))))
            ->select(['id', 'name', 'username', 'email', 'role_id'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(function (User $user): array {
                $kunci = $this->jenisPengguna($user);

                return $user->only(['id', 'name', 'username', 'email']) + [
                    'jenis' => self::JENIS[$kunci][2],
                    'tipe_rute' => self::JENIS[$kunci][0],
                    'nomor_induk' => $user->mahasiswaProfile?->nim ?? $user->dosenProfile?->nidn ?? $user->adminProfile?->nomor_induk,
                    'role_name' => $user->role?->name,
                ];
            });

        return Inertia::render('Admin/DataPengguna', [
            'users' => $users,
            'search' => $search,
            'jenis' => $jenis,
            'jenisOpsi' => collect($diizinkan)->map(fn (array $item, string $kunci): array => ['value' => $kunci, 'label' => $item[2]])->values()->all(),
        ]);
    }

    /**
     * @return array<string, array{0: string, 1: UserType, 2: string, 3: string, 4: string}>
     */
    private function jenisDiizinkan(User $user): array
    {
        $diizinkan = array_filter(self::JENIS, fn (array $jenis): bool => $user->can($jenis[4]));
        abort_if($diizinkan === [], 403);

        return $diizinkan;
    }

    /**
     * @param  Builder<Role>  $role
     */
    private function saringJenis(Builder $role, string $kunci): void
    {
        $role->where('user_type', self::JENIS[$kunci][1]);
        match ($kunci) {
            'prodi' => $role->where('slug', LingkupProdi::ROLE),
            'karyawan' => $role->where('slug', '!=', LingkupProdi::ROLE),
            default => null,
        };
    }

    private function jenisPengguna(User $user): string
    {
        return match ($user->type()) {
            UserType::Dosen => 'dosen',
            UserType::Mahasiswa => 'mahasiswa',
            default => $user->role?->slug === LingkupProdi::ROLE ? 'prodi' : 'karyawan',
        };
    }
}
