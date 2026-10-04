<?php

namespace App\Http\Controllers\Admin;

use App\AllowedUpload;
use App\Http\Controllers\Controller;
use App\Models\Cmb;
use App\Models\PengaturanPmb;
use App\Models\Role;
use App\Pmb\OpsiPmb;
use App\Pmb\SalinCalonMaba;
use App\UserType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PendaftarPmbController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $periodeId = $request->integer('periode') ?: null;
        $status = $request->string('status')->toString();

        $pendaftar = Cmb::query()
            ->with(['periode:id,kode', 'programStudi:id,nama_prodi,jenjang', 'mahasiswa:id,cmb_id,user_id'])
            ->when($periodeId, fn ($query) => $query->where('pengaturan_pmb_id', $periodeId))
            ->when($status === 'menunggu', fn ($query) => $query->whereNull('status_pendaftaran'))
            ->when(array_key_exists($status, OpsiPmb::STATUS_PENDAFTARAN), fn ($query) => $query->where('status_pendaftaran', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('nama', 'like', "%{$search}%")
                ->orWhere('nomor_pendaftaran', 'like', "%{$search}%")
                ->orWhere('nik', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(15, ['id', 'pengaturan_pmb_id', 'program_studi_id', 'nomor_pendaftaran', 'nama', 'hp', 'nilai', 'status_pendaftaran', 'created_at'])
            ->withQueryString()
            ->through(fn (Cmb $cmb): array => [
                ...$cmb->toArray(),
                'mahasiswa_url' => $this->urlMahasiswa($cmb),
            ]);

        return Inertia::render('Admin/PendaftarPmb', [
            'pendaftar' => $pendaftar,
            'bolehSalin' => $this->bolehSalin($request),
            'periode' => PengaturanPmb::query()->orderByDesc('tahun_angkatan')->orderByDesc('tanggal_buka')->get(['id', 'kode', 'tahun_angkatan']),
            'filter' => ['search' => $search, 'periode' => $periodeId, 'status' => $status],
        ]);
    }

    public function show(Cmb $cmb): Response
    {
        $cmb->load(['periode', 'agama:id,nama', 'kecamatan:id,kode,nama', 'programStudi:id,nama_prodi,jenjang', 'mahasiswa:id,cmb_id,user_id']);

        return Inertia::render('Admin/PendaftarPmbShow', [
            'pendaftar' => [...$cmb->toArray(), 'mahasiswa_url' => $this->urlMahasiswa($cmb)],
            'bolehSalin' => $this->bolehSalin(request()),
            'berkas' => $cmb->daftarBerkas(),
            'opsi' => OpsiPmb::untukForm(),
        ]);
    }

    public function update(Request $request, Cmb $cmb): RedirectResponse
    {
        $data = $request->validate([
            'nilai' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status_pendaftaran' => ['nullable', Rule::in(array_keys(OpsiPmb::STATUS_PENDAFTARAN))],
        ], [], ['nilai' => 'Nilai', 'status_pendaftaran' => 'Status pendaftaran']);

        if ($cmb->mahasiswa()->exists() && ($data['status_pendaftaran'] ?? null) !== Cmb::STATUS_DITERIMA) {
            throw ValidationException::withMessages(['status_pendaftaran' => 'Pendaftar sudah disalin ke Data Mahasiswa, status tidak bisa diubah dari Diterima.']);
        }

        $cmb->forceFill($data)->save();

        return back()->with('success', 'Hasil seleksi '.$cmb->nama.' berhasil disimpan.');
    }

    /**
     * Salin calon maba yang diterima ke Data Mahasiswa (akun + profil) dengan NIM yang diisi admin, lalu kirim tautan
     * atur sandi dan verifikasi email. NIM sekaligus menjadi username login mahasiswa.
     */
    public function salin(Request $request, Cmb $cmb): RedirectResponse
    {
        abort_unless($this->bolehSalin($request), 403, 'Anda tidak punya akses menambah Data Mahasiswa.');
        $data = $request->validate(
            ['nim' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9.\-]+$/', Rule::unique('mahasiswa_profiles', 'nim'), Rule::unique('users', 'username')]],
            ['nim.unique' => 'NIM ini sudah dipakai mahasiswa atau akun lain.', 'nim.regex' => 'NIM hanya boleh berisi huruf, angka, titik, dan tanda hubung.'],
            ['nim' => 'NIM'],
        );

        $alasan = SalinCalonMaba::alasanTidakBisa($cmb);
        if ($alasan !== null) {
            return back()->with('error', $alasan);
        }

        try {
            $user = SalinCalonMaba::salin($cmb, $data['nim']);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Data '.$cmb->nama.' gagal disalin ke Data Mahasiswa.');
        }

        $pesan = $cmb->nama.' disalin ke Data Mahasiswa dengan NIM '.$user->username.' (sekaligus username). Atur dosen wali lewat Set Penasehat Akademik.';

        return SalinCalonMaba::kirimAkses($user)
            ? back()->with('success', $pesan.' Tautan atur sandi dan verifikasi dikirim ke '.$user->email.'.')
            : back()->with('error', $pesan.' Namun surel atur sandi/verifikasi gagal dikirim; periksa Pengaturan Email lalu kirim ulang dari detail mahasiswa.');
    }

    private function bolehSalin(Request $request): bool
    {
        return $request->user()->hasPermission('admin.users.mahasiswa')
            && $request->user()->canAssignRole(Role::system(UserType::Mahasiswa)->load('permissions'));
    }

    private function urlMahasiswa(Cmb $cmb): ?string
    {
        return $cmb->mahasiswa ? route('admin.users.mahasiswa.show', $cmb->mahasiswa->user_id) : null;
    }

    public function destroy(Cmb $cmb): RedirectResponse
    {
        if ($cmb->mahasiswa()->exists()) {
            return back()->with('error', 'Pendaftar sudah disalin ke Data Mahasiswa sehingga tidak bisa dihapus.');
        }
        $cmb->delete();
        Storage::disk(AllowedUpload::DISK)->delete(array_values(array_filter($cmb->only(array_keys(Cmb::BERKAS)))));

        return to_route('admin.pendaftar-pmb.index')->with('success', 'Data pendaftar berhasil dihapus.');
    }
}
