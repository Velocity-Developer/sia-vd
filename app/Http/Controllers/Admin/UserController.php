<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DosenProfile;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\User;
use App\UserType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class UserController extends Controller
{
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
            ->through(fn (User $user): array => $user->only(['id', 'name', 'username', 'email']) + ['role_name' => $user->role?->name, 'profile' => $user->profile?->toArray()]);

        $angkatans = $role === UserType::Mahasiswa
            ? User::query()->ofType(UserType::Mahasiswa)->whereHas('mahasiswaProfile')->with('mahasiswaProfile')->get()->pluck('mahasiswaProfile.angkatan')->filter()->unique()->sortDesc()->values()->all()
            : [];

        return Inertia::render('Admin/Users', ['title' => 'Manage User - '.ucfirst($type), 'type' => $type, 'users' => $users, 'search' => $search->toString(), 'angkatan' => $angkatan, 'angkatans' => $angkatans]);
    }

    public function create(string $type): Response
    {
        $role = $this->role($type);

        return Inertia::render('Admin/UserForm', [
            'title' => 'Tambah User - '.ucfirst($type), 'type' => $type, 'user' => null,
            'roles' => $this->roleOptions($role), 'defaultRoleId' => Role::system($role)->id,
            'dosenWali' => $type === 'mahasiswa' ? $this->dosenOptions() : [],
            'programStudi' => $type !== 'karyawan' ? $this->programStudiOptions() : [],
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
        }
        if ($role === UserType::Dosen && $user->dosenProfile) {
            $extra['prodi_name'] = $user->dosenProfile->prodi?->nama_prodi;
            $extra['prodi_jenjang'] = $user->dosenProfile->prodi?->jenjang;
            $extra['prodi_kode'] = $user->dosenProfile->prodi?->kode_prodi;
            $extra['fakultas_name'] = $user->dosenProfile->prodi?->fakultas?->nama_fakultas;
            $extra['fakultas_kode'] = $user->dosenProfile->prodi?->fakultas?->kode_fakultas;
        }

        return Inertia::render('Admin/UserShow', [
            'title' => 'Detail '.ucfirst($type).' - '.$user->name, 'type' => $type,
            'user' => $user->only(['id', 'name', 'username', 'email']) + ['role_name' => $user->role?->name] + ($profile ?? []) + $extra,
        ]);
    }

    public function edit(string $type, User $user): Response
    {
        $role = $this->role($type);
        abort_unless($user->type() === $role, 404);
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
            'user' => $user->only(['id', 'name', 'username', 'email', 'role_id']) + ($profile ?? []),
            'roles' => $this->roleOptions($role), 'defaultRoleId' => $user->role_id,
            'dosenWali' => $type === 'mahasiswa' ? $this->dosenOptions() : [],
            'programStudi' => $type !== 'karyawan' ? $this->programStudiOptions() : [],
        ]);
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function roleOptions(UserType $type): array
    {
        return Role::query()->ofType($type)->orderByDesc('is_system')->orderBy('name')->get(['id', 'name'])
            ->map(fn (Role $role): array => ['id' => $role->id, 'name' => $role->name])->all();
    }

    private function dosenOptions(): array
    {
        return DosenProfile::with('user:id,name')->get(['id', 'user_id'])->map(fn (DosenProfile $profile): array => ['id' => $profile->id, 'name' => $profile->user->name])->all();
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
        $label = $this->roleLabel($type);
        try {
            $user = User::create($this->userData($data));
            $user->profile()->create($this->profileData($data, $role));
        } catch (Throwable) {
            return to_route('admin.users.'.$type)->with('error', $label.' gagal ditambahkan.');
        }

        return to_route('admin.users.'.$type)->with('success', $label.' berhasil ditambahkan.');
    }

    public function update(Request $request, string $type, User $user): RedirectResponse
    {
        $role = $this->role($type);
        abort_unless($user->type() === $role, 404);
        if (! $request->filled('password')) {
            $request->merge([
                'password' => null,
                'password_confirmation' => null,
            ]);
        }
        $data = $request->validate($this->rules($user, $role), $this->messages(), $this->attributes());
        if (blank($data['password'] ?? null)) {
            unset($data['password'], $data['password_confirmation']);
        }
        $losesRoleManagement = $request->user()->is($user)
            && $user->hasPermission(Role::SUPER_PERMISSION)
            && ! Role::query()->findOrFail($data['role_id'])->hasPermission(Role::SUPER_PERMISSION);
        if ($losesRoleManagement) {
            return back()->withErrors(['role_id' => 'Anda tidak dapat memindahkan akun sendiri ke role tanpa akses Kelola Role.'])->withInput();
        }
        $label = $this->roleLabel($type);
        try {
            DB::transaction(function () use ($user, $data, $role): void {
                $user->update($this->userData($data));
                $user->profile()->updateOrCreate([], $this->profileData($data, $role));
            });
        } catch (Throwable) {
            return to_route('admin.users.'.$type)->with('error', $label.' gagal diperbarui.');
        }

        return to_route('admin.users.'.$type)->with('success', $label.' berhasil diperbarui.');
    }

    public function destroy(string $type, User $user): RedirectResponse
    {
        $role = $this->role($type);
        abort_unless($user->type() === $role, 404);
        $label = $this->roleLabel($type);

        try {
            DB::transaction(fn (): ?bool => $user->delete());
        } catch (Throwable) {
            return to_route('admin.users.'.$type)->with('error', $label.' gagal dihapus.');
        }

        return to_route('admin.users.'.$type)->with('success', $label.' berhasil dihapus.');
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
        if ($role === UserType::Dosen) {
            $fields = [...$fields, 'jabatan_fungsional', 'pendidikan_terakhir', 'status_kepegawaian', 'prodi_id'];
        }
        if ($role === UserType::Mahasiswa) {
            $fields = [...$fields, 'angkatan', 'semester', 'status', 'dosen_wali_id', 'prodi_id', 'sekolah_asal', 'nisn', 'email_alternatif', 'nama_ayah_kandung', 'nama_ibu_kandung', 'tanggal_lahir_ayah', 'tanggal_lahir_ibu', 'pendidikan_terakhir_ayah', 'pendidikan_terakhir_ibu', 'pekerjaan_ayah', 'pekerjaan_ibu', 'penghasilan_ayah', 'penghasilan_ibu', 'no_telepon_ayah', 'no_telepon_ibu', 'email_ayah', 'email_ibu', 'alamat_ayah', 'alamat_ibu'];
        }

        return array_intersect_key($data, array_flip(array_filter($fields)));
    }

    private function rules(?User $user, UserType $role): array
    {
        $rules = ['role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('user_type', $role->value)], 'name' => ['required', 'string', 'max:255'], 'username' => ['required', 'string', 'max:255', Rule::unique('users')->ignore($user)], 'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)], 'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed']];
        foreach (self::COMMON_PROFILE_FIELDS as $field) {
            $rules[$field] = ['required', 'string', 'max:1000'];
        }
        $rules['jenis_kelamin'] = ['required', 'in:Laki-laki,Perempuan'];
        $rules['agama'] = ['required', 'in:Islam,Kristen Protestan,Kristen Katolik,Hindu,Buddha,Konghucu'];
        // Karyawan: nomor_induk wajib agar detail tidak tampil "-".
        if ($role === UserType::Admin) {
            $rules['nomor_induk'] = ['required', 'string', 'max:50', Rule::unique('admin_profiles')->ignore($user?->adminProfile?->id)];
        }
        if ($role === UserType::Dosen) {
            $rules += ['nidn' => ['required', 'string', 'max:50', Rule::unique('dosen_profiles')->ignore($user?->dosenProfile?->id)], 'jabatan_fungsional' => ['required', 'string', 'max:100'], 'pendidikan_terakhir' => ['required', 'string', 'max:100'], 'status_kepegawaian' => ['required', 'string', 'max:100'], 'prodi_id' => ['required', 'exists:program_studis,id']];
        }
        if ($role === UserType::Mahasiswa) {
            $rules += ['nim' => ['nullable', 'string', 'max:50', Rule::unique('mahasiswa_profiles')->ignore($user?->mahasiswaProfile?->id)], 'angkatan' => ['required', 'integer'], 'semester' => ['required', 'integer'], 'status' => ['required', 'in:Aktif,Nonaktif,Lulus,Dropout,Cuti,Mengundurkan Diri,Meninggal,Transfer Masuk'], 'dosen_wali_id' => ['required', 'exists:dosen_profiles,id'], 'prodi_id' => ['required', 'exists:program_studis,id'], 'sekolah_asal' => ['required', 'string', 'max:255'], 'nisn' => ['required', 'string', 'digits:10', Rule::unique('mahasiswa_profiles', 'nisn')->ignore($user?->mahasiswaProfile?->id)], 'email_alternatif' => ['required', 'email', 'max:255', 'different:email', Rule::unique('mahasiswa_profiles', 'email_alternatif')->ignore($user?->mahasiswaProfile?->id)], 'nama_ayah_kandung' => ['required', 'string', 'max:255'], 'nama_ibu_kandung' => ['required', 'string', 'max:255'], 'tanggal_lahir_ayah' => ['required', 'date'], 'tanggal_lahir_ibu' => ['required', 'date'], 'pendidikan_terakhir_ayah' => ['required', 'string', 'max:100'], 'pendidikan_terakhir_ibu' => ['required', 'string', 'max:100'], 'pekerjaan_ayah' => ['required', 'in:'.implode(',', self::PEKERJAAN_OPTIONS)], 'pekerjaan_ibu' => ['required', 'in:'.implode(',', self::PEKERJAAN_OPTIONS)], 'penghasilan_ayah' => ['required', 'in:'.implode(',', self::PENGHASILAN_OPTIONS)], 'penghasilan_ibu' => ['required', 'in:'.implode(',', self::PENGHASILAN_OPTIONS)], 'no_telepon_ayah' => ['required', 'string', 'max:50'], 'no_telepon_ibu' => ['required', 'string', 'max:50'], 'email_ayah' => ['required', 'email', 'max:255'], 'email_ibu' => ['required', 'email', 'max:255'], 'alamat_ayah' => ['required', 'string', 'max:1000'], 'alamat_ibu' => ['required', 'string', 'max:1000']];
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
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'email' => 'Format :attribute tidak valid.',
            'unique' => ':attribute sudah digunakan.',
            'confirmed' => 'Konfirmasi :attribute tidak cocok.',
            'integer' => ':attribute harus berupa angka.',
            'date' => 'Format :attribute tidak valid.',
            'in' => 'Pilihan :attribute tidak valid.',
            'exists' => ':attribute tidak ditemukan.',
            'different' => ':attribute tidak boleh sama dengan :other.',
            'email_alternatif.different' => 'Email Alternatif tidak boleh sama dengan Email utama.',
            'max.string' => ':attribute maksimal :max karakter.',
            'min.string' => ':attribute minimal :min karakter.',
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
            'semester' => 'Semester',
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
        ];
    }
}
