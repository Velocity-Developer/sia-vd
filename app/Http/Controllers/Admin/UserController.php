<?php

namespace App\Http\Controllers\Admin;

use App\AllowedUpload;
use App\Http\Controllers\Controller;
use App\LingkupProdi;
use App\Models\DispensasiUjian;
use App\Models\DosenProfile;
use App\Models\Fakultas;
use App\Models\KrsSemester;
use App\Models\MahasiswaProfile;
use App\Models\PengajuanIzin;
use App\Models\PresensiMahasiswa;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\TahunAkademik;
use App\Models\User;
use App\Pmb\BiodataPddiktiRules;
use App\Pmb\OpsiPmb;
use App\UserType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class UserController extends Controller
{
    public const AGAMA = ['Islam', 'Kristen Protestan', 'Kristen Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Lainnya'];

    private const COMMON_PROFILE_FIELDS = ['tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama', 'no_telepon', 'alamat', 'kewarganegaraan'];

    private const PEKERJAAN_OPTIONS = ['Tidak Bekerja', 'Karyawan Swasta', 'Pegawai Negeri Sipil (PNS)', 'TNI / Polri', 'Wiraswasta / Pengusaha', 'Profesional', 'Guru / Dosen', 'Tenaga Kesehatan', 'Petani', 'Peternak', 'Nelayan', 'Pedagang', 'Ibu Rumah Tangga', 'Freelancer', 'Pensiunan', 'Sudah Meninggal', 'Lainnya'];

    private const PENGHASILAN_OPTIONS = ['Kurang dari Rp1.000.000', 'Rp1.000.000 – Rp2.999.999', 'Rp3.000.000 – Rp4.999.999', 'Rp5.000.000 – Rp7.499.999', 'Rp7.500.000 – Rp9.999.999', 'Rp10.000.000 – Rp14.999.999', 'Rp15.000.000 atau lebih', 'Tidak Berpenghasilan'];

    public function index(Request $request, string $type): Response
    {
        $role = $this->role($type);
        $search = $request->string('search')->trim();
        $angkatan = $request->integer('angkatan') ?: null;
        $users = User::query()->ofType($role)
            ->when($role === UserType::Mahasiswa && $angkatan !== null, fn ($query) => $query->whereHas('mahasiswaProfile', fn ($query) => $query->where('angkatan', $angkatan)))
            ->with([$this->profileRelation($role), 'role:id,name,user_type'])
            ->when($search->isNotEmpty(), fn ($query) => $query->where(function ($query) use ($search, $role): void {
                $query->where('name', 'like', "%{$search}%");
                $idColumn = match ($role) {
                    UserType::Admin => 'nomor_induk',
                    UserType::Dosen => 'nidn',
                    UserType::Mahasiswa => 'nim',
                };
                $table = match ($role) {
                    UserType::Admin => 'admin_profiles',
                    UserType::Dosen => 'dosen_profiles',
                    UserType::Mahasiswa => 'mahasiswa_profiles',
                };
                $query->orWhereHas($this->profileRelation($role), fn ($profile) => $profile->where($table.'.'.$idColumn, 'like', "%{$search}%"));
            }))
            ->select(['id', 'name', 'username', 'email', 'role_id'])->orderBy('name')->paginate(10)->withQueryString()
            // Pakai relasi profil yang sudah di-eager-load (bukan $user->profile yang memicu query per baris),
            // dan kirim hanya nomor induk yang ditampilkan di tabel.
            ->through(fn (User $user): array => $user->only(['id', 'name', 'username', 'email']) + [
                'role_name' => $user->role?->name,
                'profile' => $user->getRelation($this->profileRelation($role))?->only(['nomor_induk', 'nidn', 'nim']),
            ]);

        $angkatans = $role === UserType::Mahasiswa
            ? MahasiswaProfile::query()->whereNotNull('angkatan')->distinct()->orderByDesc('angkatan')->pluck('angkatan')->all()
            : [];

        return Inertia::render('Admin/Users', ['title' => 'Manage User - '.ucfirst($type), 'type' => $type, 'users' => $users, 'search' => $search->toString(), 'angkatan' => $angkatan, 'angkatans' => $angkatans]);
    }

    public function create(string $type): Response
    {
        $role = $this->role($type);
        $roles = $this->roleOptions($role);
        // Create User → Prodi membuka form karyawan dengan role Prodi sudah terpilih (bila boleh diberikan).
        $roleProdi = $type === 'karyawan' && request()->query('role') === LingkupProdi::ROLE
            ? collect($roles)->firstWhere('prodi', true)
            : null;

        return Inertia::render('Admin/UserForm', [
            'title' => $roleProdi ? 'Tambah User - Prodi' : 'Tambah User - '.ucfirst($type), 'type' => $type, 'user' => null,
            'roles' => $roles, 'defaultRoleId' => $roleProdi['id'] ?? Role::system($role)->id,
            'dosenWali' => $type === 'mahasiswa' ? $this->dosenOptions(null) : [],
            'programStudi' => $this->programStudiOptions(),
            'opsi' => $type === 'mahasiswa' ? OpsiPmb::untukForm() : [],
        ]);
    }

    public function show(string $type, User $user): Response
    {
        $role = $this->role($type);
        abort_unless($user->type() === $role, 404);
        $user->load(array_merge([$this->profileRelation($role)], $role === UserType::Mahasiswa ? ['mahasiswaProfile.dosenWali.user', 'mahasiswaProfile.prodi.fakultas'] : [], $role === UserType::Dosen ? ['dosenProfile.prodi.fakultas'] : []));

        $profile = $user->profile?->toArray();
        $extra = [];
        if ($role === UserType::Mahasiswa && $user->mahasiswaProfile) {
            $extra['dosen_wali_name'] = $user->mahasiswaProfile->dosenWali?->user?->name;
            $extra['prodi_name'] = $user->mahasiswaProfile->prodi?->nama_prodi;
            $extra['prodi_jenjang'] = $user->mahasiswaProfile->prodi?->jenjang;
            $extra['prodi_kode'] = $user->mahasiswaProfile->prodi?->kode_prodi;
            $extra['fakultas_name'] = $user->mahasiswaProfile->prodi?->fakultas?->nama_fakultas;
            $extra['fakultas_kode'] = $user->mahasiswaProfile->prodi?->fakultas?->kode_fakultas;
            $extra['semester'] = $user->mahasiswaProfile->semesterPada(TahunAkademik::aktif());
            $extra += $this->biodataTambahan($user->mahasiswaProfile);
        }
        $kunciKrs = null;
        if ($role === UserType::Mahasiswa && $user->mahasiswaProfile && ($tahunAktif = TahunAkademik::aktif())) {
            $kunci = KrsSemester::untuk($user->mahasiswaProfile->id, $tahunAktif->id)?->setRelation('tahunAkademik', $tahunAktif);
            $terkunci = ! KrsSemester::bolehDiubah($user->mahasiswaProfile->id, $tahunAktif, $kunci);
            // Kartu tampil bila KRS sudah tersimpan/dikembalikan, atau terkunci karena periode berakhir sebelum disimpan.
            $kunciKrs = $kunci === null && ! $terkunci ? null : [
                'tahun_akademik_id' => $tahunAktif->id,
                'tahun_akademik' => $tahunAktif->tahun.' '.$tahunAktif->semester,
                ...($kunci?->ringkasan() ?? ['status' => null, 'disimpan_pada' => null, 'catatan_revisi' => null, 'batas_revisi' => null]),
                'terkunci' => $terkunci,
                'masa_revisi_berjalan' => KrsSemester::masaRevisiBerjalan($tahunAktif),
                'verifikasi' => KrsSemester::verifikasiAktif(),
            ];
        }
        if ($role === UserType::Dosen && $user->dosenProfile) {
            $extra['prodi_name'] = $user->dosenProfile->prodi?->nama_prodi;
            $extra['prodi_jenjang'] = $user->dosenProfile->prodi?->jenjang;
            $extra['prodi_kode'] = $user->dosenProfile->prodi?->kode_prodi;
            $extra['fakultas_name'] = $user->dosenProfile->prodi?->fakultas?->nama_fakultas;
            $extra['fakultas_kode'] = $user->dosenProfile->prodi?->fakultas?->kode_fakultas;
        }
        if ($role === UserType::Admin && $user->adminProfile?->prodi_id) {
            $prodi = $user->adminProfile->loadMissing('prodi.fakultas')->prodi;
            $extra['prodi_name'] = $prodi?->nama_prodi;
            $extra['prodi_jenjang'] = $prodi?->jenjang;
            $extra['prodi_kode'] = $prodi?->kode_prodi;
            $extra['fakultas_name'] = $prodi?->fakultas?->nama_fakultas;
            $extra['fakultas_kode'] = $prodi?->fakultas?->kode_fakultas;
        }

        return Inertia::render('Admin/UserShow', [
            'title' => 'Detail '.ucfirst($type).' - '.$user->name, 'type' => $type,
            'user' => $user->only(['id', 'name', 'username', 'email', 'email_verified_at']) + ['role_name' => $user->role?->name] + $this->denganFotoUrl($user, $profile) + $extra,
            'bolehKelola' => request()->user()->canManage($user),
            'kunciKrs' => $kunciKrs,
            'opsi' => $role === UserType::Mahasiswa ? OpsiPmb::untukForm() : [],
        ]);
    }

    /**
     * Buka kunci KRS mahasiswa di tahun akademik aktif agar ia bisa memperbaiki pilihan kelasnya sendiri,
     * juga sesudah masa KRS/revisi berakhir bila tanggal batasnya diisi.
     * Tidak bergantung pada fitur keuangan (tombol yang sama di menu Tagihan hanya ada bila keuangan aktif).
     */
    public function bukaKunciKrs(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->type() === UserType::Mahasiswa && $user->mahasiswaProfile, 404);
        abort_unless($request->user()->canManage($user), 403, 'Anda tidak dapat mengubah akun dengan hak akses lebih tinggi.');
        $data = $request->validate(KrsSemester::ATURAN_BUKA_KUNCI, attributes: KrsSemester::ATRIBUT_BUKA_KUNCI);
        $tahunAktif = TahunAkademik::aktif();

        if ($tahunAktif === null) {
            return back()->with('error', 'Belum ada tahun akademik aktif.');
        }

        $galat = KrsSemester::bukaKunci($user->mahasiswaProfile->id, $tahunAktif, $data['catatan'] ?? null, $data['dibuka_sampai'] ?? null, $request->user()->id);

        return $galat !== null
            ? back()->with('error', $galat)
            : back()->with('success', 'Kunci KRS '.$user->name.' dibuka. '.KrsSemester::pesanDibuka($data['dibuka_sampai'] ?? null, $tahunAktif));
    }

    public function edit(string $type, User $user): Response
    {
        $role = $this->role($type);
        abort_unless($user->type() === $role, 404);
        abort_unless(request()->user()->canManage($user), 403, 'Anda tidak dapat mengubah akun dengan hak akses lebih tinggi.');
        $user->load($this->profileRelation($role));

        $profile = $user->profile?->toArray();
        // Normalisasi tanggal ke YYYY-MM-DD untuk DatePicker.
        foreach (['tanggal_lahir', 'tanggal_lahir_ayah', 'tanggal_lahir_ibu'] as $dateField) {
            if (isset($profile[$dateField]) && $profile[$dateField] !== null && $profile[$dateField] !== '') {
                $profile[$dateField] = substr((string) $profile[$dateField], 0, 10);
            }
        }

        return Inertia::render('Admin/UserForm', [
            'title' => 'Edit User - '.ucfirst($type), 'type' => $type,
            'user' => $user->only(['id', 'name', 'username', 'email', 'role_id']) + $this->denganFotoUrl($user, $profile)
                + ($user->mahasiswaProfile ? $this->biodataTambahan($user->mahasiswaProfile) : []),
            'roles' => $this->roleOptions($role), 'defaultRoleId' => $user->role_id,
            'dosenWali' => $type === 'mahasiswa' ? $this->dosenOptions($user->mahasiswaProfile?->dosen_wali_id) : [],
            'programStudi' => $this->programStudiOptions(),
            'opsi' => $type === 'mahasiswa' ? OpsiPmb::untukForm() : [],
        ]);
    }

    /**
     * @return list<array{id: int, name: string, prodi: bool}>
     */
    private function roleOptions(UserType $type): array
    {
        $actor = request()->user();

        return Role::query()->ofType($type)->with('permissions')->orderByDesc('is_system')->orderBy('name')->get()
            ->filter(fn (Role $role): bool => $actor === null || $actor->canAssignRole($role))
            ->map(fn (Role $role): array => ['id' => $role->id, 'name' => $role->name, 'prodi' => $role->slug === LingkupProdi::ROLE])->values()->all();
    }

    /**
     * Cegah pemberian role yang hak aksesnya melebihi hak akses pengguna yang sedang login.
     */
    private function ensureCanAssignRole(Request $request, int $roleId): void
    {
        if (! $request->user()->canAssignRole(Role::query()->with('permissions')->findOrFail($roleId))) {
            throw ValidationException::withMessages([
                'role_id' => 'Anda tidak dapat memberikan role dengan hak akses yang tidak Anda miliki.',
            ]);
        }
    }

    private function dosenOptions(?int $terpilih): array
    {
        return DosenProfile::pilihan($terpilih)->with('user:id,name')->get(['id', 'user_id'])->map(fn (DosenProfile $profile): array => ['id' => $profile->id, 'name' => $profile->user->name])->all();
    }

    private function programStudiOptions(): array
    {
        return ProgramStudi::with('fakultas:id,nama_fakultas')->orderBy('nama_prodi')->get()
            ->map(fn (ProgramStudi $prodi): array => ['id' => $prodi->id, 'nama_prodi' => $prodi->nama_prodi, 'jenjang' => $prodi->jenjang, 'fakultas' => $prodi->fakultas?->nama_fakultas])->all();
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $role = $this->role($type);
        $data = $request->validate($this->rules(null, $role), $this->messages(), $this->attributes());
        $data = $this->semesterMasuk($data, $role, null);
        $this->ensureCanAssignRole($request, (int) $data['role_id']);
        $label = $this->roleLabel($type);
        $foto = $this->simpanFoto($request->file('foto'), $type);
        try {
            $user = DB::transaction(function () use ($data, $role, $foto): User {
                $user = User::create($this->userData($data));
                $user->profile()->create([...$this->profileData($data, $role), 'foto' => $foto]);

                return $user;
            });
        } catch (Throwable) {
            $this->hapusFoto($foto);

            return to_route('admin.users.'.$type)->with('error', $label.' gagal ditambahkan.');
        }

        if (! $user->kirimVerifikasiEmail()) {
            return to_route('admin.users.'.$type)->with('error', $label.' berhasil ditambahkan, tetapi surel verifikasi gagal dikirim. Periksa Pengaturan Email lalu kirim ulang dari halaman detail.');
        }

        return to_route('admin.users.'.$type)->with('success', $label.' berhasil ditambahkan. Tautan verifikasi dikirim ke '.$user->email.'.');
    }

    public function update(Request $request, string $type, User $user): RedirectResponse
    {
        $role = $this->role($type);
        abort_unless($user->type() === $role, 404);
        abort_unless($request->user()->canManage($user), 403, 'Anda tidak dapat mengubah akun dengan hak akses lebih tinggi.');
        if (! $request->filled('password')) {
            $request->merge([
                'password' => null,
                'password_confirmation' => null,
            ]);
        }
        $data = $request->validate($this->rules($user, $role), $this->messages(), $this->attributes());
        $data = $this->semesterMasuk($data, $role, $user->mahasiswaProfile);
        if (blank($data['password'] ?? null)) {
            unset($data['password'], $data['password_confirmation']);
        }
        $this->ensureCanAssignRole($request, (int) $data['role_id']);
        $losesRoleManagement = $user->hasPermission(Role::SUPER_PERMISSION)
            && ! Role::query()->findOrFail($data['role_id'])->hasPermission(Role::SUPER_PERMISSION);
        if ($losesRoleManagement && $request->user()->is($user)) {
            return back()->withErrors(['role_id' => 'Anda tidak dapat memindahkan akun sendiri ke role tanpa akses Kelola Role.'])->withInput();
        }
        if ($losesRoleManagement && $user->isLastRoleManager()) {
            return back()->withErrors(['role_id' => 'Akun ini satu-satunya yang memegang akses Kelola Role, sehingga role-nya tidak dapat diganti.'])->withInput();
        }
        $label = $this->roleLabel($type);
        // Foto baru menggantikan yang lama; centang "hapus foto" mengosongkannya. Berkas lama baru dihapus
        // setelah data tersimpan, agar kegagalan simpan tidak menghilangkan foto yang masih dipakai.
        $fotoLama = $user->profile?->foto;
        $fotoBaru = $this->simpanFoto($request->file('foto'), $type);
        $gantiFoto = $fotoBaru !== null || $request->boolean('hapus_foto');
        try {
            DB::transaction(function () use ($user, $data, $role, $fotoBaru, $gantiFoto): void {
                $user->fill($this->userData($data));
                if ($user->isDirty('email')) {
                    $user->email_verified_at = null;
                }
                $user->save();
                $user->profile()->updateOrCreate([], [...$this->profileData($data, $role), ...($gantiFoto ? ['foto' => $fotoBaru] : [])]);
            });
        } catch (Throwable) {
            $this->hapusFoto($fotoBaru);

            return to_route('admin.users.'.$type)->with('error', $label.' gagal diperbarui.');
        }
        if ($gantiFoto) {
            $this->hapusFoto($fotoLama);
        }

        if ($user->wasChanged('email')) {
            return $user->kirimVerifikasiEmail()
                ? to_route('admin.users.'.$type)->with('success', $label.' berhasil diperbarui. Email berubah, tautan verifikasi dikirim ke '.$user->email.'.')
                : to_route('admin.users.'.$type)->with('error', $label.' berhasil diperbarui, tetapi surel verifikasi ke email baru gagal dikirim. Kirim ulang dari halaman detail.');
        }

        return to_route('admin.users.'.$type)->with('success', $label.' berhasil diperbarui.');
    }

    /**
     * Kirim ulang tautan verifikasi ke email akun yang belum terverifikasi.
     */
    public function kirimVerifikasi(Request $request, string $type, User $user): RedirectResponse
    {
        abort_unless($user->type() === $this->role($type), 404);
        abort_unless($request->user()->canManage($user), 403, 'Anda tidak dapat mengubah akun dengan hak akses lebih tinggi.');
        if ($user->hasVerifiedEmail()) {
            return back()->with('error', 'Email akun ini sudah terverifikasi.');
        }

        return $user->kirimVerifikasiEmail()
            ? back()->with('success', 'Tautan verifikasi dikirim ke '.$user->email.'.')
            : back()->with('error', 'Surel verifikasi gagal dikirim. Periksa Pengaturan Email.');
    }

    /**
     * Tandai email terverifikasi secara manual (mis. pengguna tidak bisa menerima surel).
     */
    public function tandaiTerverifikasi(Request $request, string $type, User $user): RedirectResponse
    {
        abort_unless($user->type() === $this->role($type), 404);
        abort_unless($request->user()->canManage($user), 403, 'Anda tidak dapat mengubah akun dengan hak akses lebih tinggi.');
        $user->markEmailAsVerified();

        return back()->with('success', 'Email '.$user->email.' ditandai terverifikasi.');
    }

    public function destroy(string $type, User $user): RedirectResponse
    {
        $role = $this->role($type);
        abort_unless($user->type() === $role, 404);
        $label = $this->roleLabel($type);

        if (request()->user()->is($user)) {
            return to_route('admin.users.'.$type)->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        if (! request()->user()->canManage($user)) {
            return to_route('admin.users.'.$type)->with('error', $label.' memiliki hak akses lebih tinggi dari Anda sehingga tidak dapat dihapus.');
        }

        if ($user->isLastRoleManager()) {
            return to_route('admin.users.'.$type)->with('error', $label.' adalah satu-satunya pemegang akses Kelola Role sehingga tidak dapat dihapus.');
        }

        if ($user->dosenProfile?->kelasKuliah()->exists()) {
            return to_route('admin.users.'.$type)->with('error', $label.' tidak dapat dihapus karena masih mengampu kelas kuliah.');
        }

        if ($user->dosenProfile !== null && Fakultas::query()->where('dekan_id', $user->dosenProfile->id)->exists()) {
            return to_route('admin.users.'.$type)->with('error', $label.' tidak dapat dihapus karena masih menjabat dekan. Ganti dekan fakultasnya terlebih dahulu.');
        }

        if ($user->dosenProfile !== null && ProgramStudi::query()->where('kaprodi', $user->dosenProfile->id)->exists()) {
            return to_route('admin.users.'.$type)->with('error', $label.' tidak dapat dihapus karena masih menjabat kaprodi. Ganti kaprodi program studinya terlebih dahulu.');
        }

        if ($user->dosenProfile?->mahasiswaWali()->exists()) {
            return to_route('admin.users.'.$type)->with('error', $label.' tidak dapat dihapus karena masih menjadi dosen wali. Pindahkan mahasiswa bimbingannya ke dosen wali lain terlebih dahulu.');
        }

        if ($user->mahasiswaProfile?->krs()->exists()) {
            return to_route('admin.users.'.$type)->with('error', $label.' tidak dapat dihapus karena sudah memiliki KRS.');
        }

        // KRS yang dibatalkan tidak menghapus riwayat presensinya; riwayat itu tetap dijaga.
        $mahasiswaId = $user->mahasiswaProfile?->id;
        if ($mahasiswaId !== null && (PresensiMahasiswa::where('mahasiswa_id', $mahasiswaId)->exists()
            || PengajuanIzin::where('mahasiswa_id', $mahasiswaId)->exists()
            || DispensasiUjian::where('mahasiswa_id', $mahasiswaId)->exists())) {
            return to_route('admin.users.'.$type)->with('error', $label.' tidak dapat dihapus karena sudah memiliki riwayat presensi atau pengajuan izin.');
        }

        $foto = $user->profile?->foto;
        try {
            DB::transaction(fn (): ?bool => $user->delete());
        } catch (Throwable) {
            return to_route('admin.users.'.$type)->with('error', $label.' gagal dihapus.');
        }
        $this->hapusFoto($foto);

        return to_route('admin.users.'.$type)->with('success', $label.' berhasil dihapus.');
    }

    /**
     * Simpan foto profil di disk privat; null bila tidak ada unggahan.
     */
    private function simpanFoto(?UploadedFile $berkas, string $type): ?string
    {
        return $berkas?->store('foto/'.$type, AllowedUpload::DISK) ?: null;
    }

    private function hapusFoto(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(AllowedUpload::DISK)->delete($path);
        }
    }

    /**
     * Ganti path foto di data profil dengan URL tampilnya (path disk tidak dikirim ke browser).
     *
     * @param  array<string, mixed>|null  $profile
     * @return array<string, mixed>
     */
    private function denganFotoUrl(User $user, ?array $profile): array
    {
        $foto = $profile['foto'] ?? null;
        unset($profile['foto']);

        return ($profile ?? []) + ['foto_url' => User::urlFoto($user->id, $foto)];
    }

    /**
     * Semester masuk (mis. mahasiswa pindahan) dicatat pada tahun akademik aktif saat diisi atau diubah, dan
     * paritasnya harus sesuai semester aktif. Nilai yang tidak berubah tetap memakai tahun akademik lamanya.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function semesterMasuk(array $data, UserType $role, ?MahasiswaProfile $lama): array
    {
        if ($role !== UserType::Mahasiswa) {
            return $data;
        }

        $semester = $data['semester_masuk'] ?? null;
        if ($semester === null) {
            return [...$data, 'tahun_akademik_masuk_id' => null];
        }
        if ($lama !== null && $lama->semester_masuk === (int) $semester && $lama->tahun_akademik_masuk_id !== null) {
            return $data;
        }

        $aktif = TahunAkademik::aktif();
        $galat = $aktif === null
            ? 'Belum ada tahun akademik aktif. Aktifkan tahun akademik dulu sebelum mengisi semester masuk.'
            : MahasiswaProfile::galatSemesterMasuk((int) $semester, $aktif);
        if ($galat !== null) {
            throw ValidationException::withMessages(['semester_masuk' => $galat]);
        }

        return [...$data, 'tahun_akademik_masuk_id' => $aktif->id];
    }

    /**
     * Label kecamatan, asal pendaftaran PMB, dan tautan berkas mahasiswa (path berkas tidak dikirim).
     *
     * @return array<string, mixed>
     */
    private function biodataTambahan(MahasiswaProfile $profil): array
    {
        $profil->loadMissing(['kecamatan:id,kode,nama', 'cmb:id,nomor_pendaftaran', 'tahunAkademikMasuk:id,tahun,semester']);

        return [
            'semester_masuk_pada' => $pada = $profil->tahunAkademikMasuk ? $profil->tahunAkademikMasuk->tahun.' '.$profil->tahunAkademikMasuk->semester : null,
            'semester_masuk_label' => $profil->semester_masuk ? "Semester {$profil->semester_masuk}".($pada ? " ({$pada})" : '') : null,
            'kecamatan_label' => $profil->kecamatan?->nama,
            'nomor_pendaftaran' => $profil->cmb?->nomor_pendaftaran,
            'cmb_url' => $profil->cmb && request()->user()->hasPermission('admin.pendaftar-pmb') ? route('admin.pendaftar-pmb.show', $profil->cmb_id) : null,
            'berkas' => collect(MahasiswaProfile::BERKAS)
                ->filter(fn (string $label, string $kolom): bool => filled($profil->getAttribute($kolom)))
                ->map(fn (string $label, string $kolom): array => ['label' => $label, 'url' => route('berkas.mahasiswa', ['user' => $profil->user_id, 'jenis' => $kolom])])
                ->values()->all(),
        ];
    }

    /** Role yang dipilih di form adalah role Prodi. */
    private function rolePilihanProdi(): bool
    {
        return Role::query()->whereKey(request()->integer('role_id'))->value('slug') === LingkupProdi::ROLE;
    }

    private function role(string $type): UserType
    {
        return match ($type) {
            'dosen' => UserType::Dosen,
            'mahasiswa' => UserType::Mahasiswa,
            'karyawan' => UserType::Admin,
            default => abort(404),
        };
    }

    private function profileRelation(UserType $role): string
    {
        return match ($role) {
            UserType::Admin => 'adminProfile', UserType::Dosen => 'dosenProfile', UserType::Mahasiswa => 'mahasiswaProfile'
        };
    }

    private function userData(array $data): array
    {
        return array_intersect_key($data, array_flip(['name', 'username', 'email', 'password', 'role_id']));
    }

    private function profileData(array $data, UserType $role): array
    {
        $fields = self::COMMON_PROFILE_FIELDS;
        $fields[] = match ($role) {
            UserType::Admin => 'nomor_induk', UserType::Dosen => 'nidn', UserType::Mahasiswa => 'nim'
        };
        if ($role === UserType::Admin) {
            $fields[] = 'prodi_id';
            $data['prodi_id'] = $this->rolePilihanProdi() ? ($data['prodi_id'] ?? null) : null;
        }
        if ($role === UserType::Dosen) {
            $fields = [...$fields, 'jabatan_fungsional', 'pendidikan_terakhir', 'status_kepegawaian', 'status', 'prodi_id'];
        }
        if ($role === UserType::Mahasiswa) {
            $fields = [...$fields, 'angkatan', 'status', 'dosen_wali_id', 'prodi_id', 'sekolah_asal', 'nisn', 'email_alternatif', 'nama_ayah_kandung', 'nama_ibu_kandung', 'tanggal_lahir_ayah', 'tanggal_lahir_ibu', 'pendidikan_terakhir_ayah', 'pendidikan_terakhir_ibu', 'pekerjaan_ayah', 'pekerjaan_ibu', 'penghasilan_ayah', 'penghasilan_ibu', 'no_telepon_ayah', 'no_telepon_ibu', 'email_ayah', 'email_ibu', 'alamat_ayah', 'alamat_ibu', 'semester_masuk', 'tahun_akademik_masuk_id', ...BiodataPddiktiRules::FIELDS];
        }

        return array_intersect_key($data, array_flip(array_filter($fields)));
    }

    private function rules(?User $user, UserType $role): array
    {
        $rules = ['role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('user_type', $role->value)], 'name' => ['required', 'string', 'max:255'], 'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user)], 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)], 'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed']];
        foreach (self::COMMON_PROFILE_FIELDS as $field) {
            $rules[$field] = ['required', 'string', 'max:1000'];
        }
        $rules['foto'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'];
        $rules['hapus_foto'] = ['nullable', 'boolean'];
        $rules['jenis_kelamin'] = ['required', 'in:Laki-laki,Perempuan'];
        $rules['agama'] = ['required', Rule::in(self::AGAMA)];
        // Karyawan: nomor_induk wajib agar detail tidak tampil "-".
        if ($role === UserType::Admin) {
            $rules['nomor_induk'] = ['required', 'string', 'max:50', Rule::unique('admin_profiles')->ignore($user?->adminProfile?->id)];
            // Akun Prodi wajib terikat ke satu program studi; role lain tidak memakai isian ini.
            $rules['prodi_id'] = [Rule::requiredIf(fn (): bool => $this->rolePilihanProdi()), 'nullable', 'exists:program_studis,id'];
        }
        if ($role === UserType::Dosen) {
            $rules += ['nidn' => ['required', 'string', 'max:50', Rule::unique('dosen_profiles')->ignore($user?->dosenProfile?->id)], 'jabatan_fungsional' => ['required', 'string', 'max:100'], 'pendidikan_terakhir' => ['required', 'string', 'max:100'], 'status_kepegawaian' => ['required', 'string', 'max:100'], 'status' => ['required', Rule::in(DosenProfile::STATUS)], 'prodi_id' => ['nullable', 'exists:program_studis,id']];
        }
        if ($role === UserType::Mahasiswa) {
            // Mahasiswa hasil salinan calon maba belum punya NIM, dosen wali, dan data orang tua lengkap,
            // jadi isian itu opsional; NIM tetap unik bila diisi.
            $profilId = $user?->mahasiswaProfile?->id;
            $rules += [
                'nim' => ['nullable', 'string', 'max:50', Rule::unique('mahasiswa_profiles')->ignore($profilId)],
                'angkatan' => ['required', 'integer', 'digits:4'],
                'status' => ['required', Rule::in(MahasiswaProfile::STATUS)],
                'dosen_wali_id' => ['nullable', DosenProfile::rulePilihan($user?->mahasiswaProfile?->dosen_wali_id)],
                'prodi_id' => ['required', 'exists:program_studis,id'],
                'semester_masuk' => ['nullable', 'integer', 'min:1', 'max:14'],
                'sekolah_asal' => ['nullable', 'string', 'max:255'],
                'nisn' => ['nullable', 'string', 'digits:10', Rule::unique('mahasiswa_profiles', 'nisn')->ignore($profilId)],
                'email_alternatif' => ['nullable', 'email', 'max:255', 'different:email', Rule::unique('mahasiswa_profiles', 'email_alternatif')->ignore($profilId)],
                'nama_ayah_kandung' => ['nullable', 'string', 'max:255'],
                'nama_ibu_kandung' => ['required', 'string', 'max:255'],
                'tanggal_lahir_ayah' => ['nullable', 'date'],
                'tanggal_lahir_ibu' => ['nullable', 'date'],
                'pendidikan_terakhir_ayah' => ['nullable', 'string', 'max:100'],
                'pendidikan_terakhir_ibu' => ['nullable', 'string', 'max:100'],
                'pekerjaan_ayah' => ['nullable', 'in:'.implode(',', self::PEKERJAAN_OPTIONS)],
                'pekerjaan_ibu' => ['nullable', 'in:'.implode(',', self::PEKERJAAN_OPTIONS)],
                'penghasilan_ayah' => ['nullable', 'in:'.implode(',', self::PENGHASILAN_OPTIONS)],
                'penghasilan_ibu' => ['nullable', 'in:'.implode(',', self::PENGHASILAN_OPTIONS)],
                'no_telepon_ayah' => ['nullable', 'string', 'max:50'],
                'no_telepon_ibu' => ['nullable', 'string', 'max:50'],
                'email_ayah' => ['nullable', 'email', 'max:255'],
                'email_ibu' => ['nullable', 'email', 'max:255'],
                'alamat_ayah' => ['nullable', 'string', 'max:1000'],
                'alamat_ibu' => ['nullable', 'string', 'max:1000'],
                ...BiodataPddiktiRules::rules($profilId),
            ];
        }
        $rules['tanggal_lahir'] = ['required', 'date'];

        return $rules;
    }

    private function roleLabel(string $type): string
    {
        return match ($type) {
            'dosen' => 'Dosen',
            'mahasiswa' => 'Mahasiswa',
            'karyawan' => 'Karyawan',
            default => 'Pengguna',
        };
    }

    /**
     * Pesan validasi Bahasa Indonesia.
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'email' => 'Format :attribute tidak valid.',
            'date' => 'Format :attribute tidak valid.',
            'in' => 'Pilihan :attribute tidak valid.',
            'exists' => ':attribute tidak ditemukan.',
            'different' => ':attribute tidak boleh sama dengan :other.',
            'email_alternatif.different' => 'Email Alternatif tidak boleh sama dengan Email utama.',
            'min.string' => ':attribute minimal :min karakter.',
            'foto.image' => 'Foto harus berupa gambar.',
            'foto.mimes' => 'Foto harus berformat jpg, jpeg, png, atau webp.',
            'foto.max' => 'Ukuran foto maksimal 2 MB.',
        ];
    }

    /**
     * Label atribut Bahasa Indonesia untuk pesan validasi.
     *
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'foto' => 'Foto',
            'role_id' => 'Role',
            'name' => 'Nama',
            'username' => 'Username',
            'email' => 'Email',
            'password' => 'Kata Sandi',
            'tempat_lahir' => 'Tempat Lahir',
            'tanggal_lahir' => 'Tanggal Lahir',
            'jenis_kelamin' => 'Jenis Kelamin',
            'agama' => 'Agama',
            'no_telepon' => 'Nomor Telepon',
            'alamat' => 'Alamat',
            'kewarganegaraan' => 'Kewarganegaraan',
            'nomor_induk' => 'Nomor Induk',
            'nidn' => 'NIDN',
            'jabatan_fungsional' => 'Jabatan Fungsional',
            'pendidikan_terakhir' => 'Pendidikan Terakhir',
            'status_kepegawaian' => 'Status Kepegawaian',
            'nim' => 'NIM',
            'angkatan' => 'Angkatan',
            'status' => 'Status',
            'dosen_wali_id' => 'Dosen Wali',
            'prodi_id' => 'Program Studi',
            'sekolah_asal' => 'Sekolah Asal',
            'nisn' => 'NISN',
            'email_alternatif' => 'Email Alternatif',
            'nama_ayah_kandung' => 'Nama Ayah Kandung',
            'nama_ibu_kandung' => 'Nama Ibu Kandung',
            'tanggal_lahir_ayah' => 'Tanggal Lahir Ayah',
            'tanggal_lahir_ibu' => 'Tanggal Lahir Ibu',
            'pendidikan_terakhir_ayah' => 'Pendidikan Terakhir Ayah',
            'pendidikan_terakhir_ibu' => 'Pendidikan Terakhir Ibu',
            'pekerjaan_ayah' => 'Pekerjaan Ayah',
            'pekerjaan_ibu' => 'Pekerjaan Ibu',
            'penghasilan_ayah' => 'Penghasilan Ayah',
            'penghasilan_ibu' => 'Penghasilan Ibu',
            'no_telepon_ayah' => 'Nomor Telepon Ayah',
            'no_telepon_ibu' => 'Nomor Telepon Ibu',
            'email_ayah' => 'Email Ayah',
            'email_ibu' => 'Email Ibu',
            'alamat_ayah' => 'Alamat Ayah',
            'alamat_ibu' => 'Alamat Ibu',
            'semester_masuk' => 'Semester Masuk',
            ...BiodataPddiktiRules::attributes(),
        ];
    }
}
