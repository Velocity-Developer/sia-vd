<?php

namespace Database\Factories;

use App\Models\DosenProfile;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\User;
use App\UserType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            $type = $user->type();
            $base = [
                'tempat_lahir' => fake()->city(),
                'tanggal_lahir' => fake()->date(),
                'jenis_kelamin' => fake()->randomElement(['Laki-laki', 'Perempuan']),
                'agama' => 'Islam',
                'no_telepon' => fake()->phoneNumber(),
                'alamat' => fake()->address(),
                'kewarganegaraan' => 'Indonesia',
            ];
            if ($type === UserType::Mahasiswa) {
                $base += [
                    'nim' => fake()->unique()->numerify('########'),
                    'angkatan' => 2024, 'semester' => 2, 'status' => 'Aktif',
                    'dosen_wali_id' => DosenProfile::query()->inRandomOrder()->value('id'),
                    'prodi_id' => ProgramStudi::query()->inRandomOrder()->value('id'),
                    'sekolah_asal' => fake()->company(),
                    'nisn' => fake()->unique()->numerify('##########'),
                    'email_alternatif' => fake()->unique()->safeEmail(),
                    'nama_ayah_kandung' => fake()->name('male'), 'tanggal_lahir_ayah' => fake()->date(), 'pendidikan_terakhir_ayah' => 'S1', 'pekerjaan_ayah' => fake()->randomElement(['Tidak Bekerja', 'Karyawan Swasta', 'Pegawai Negeri Sipil (PNS)', 'TNI / Polri', 'Wiraswasta / Pengusaha', 'Profesional', 'Guru / Dosen', 'Tenaga Kesehatan', 'Petani', 'Peternak', 'Nelayan', 'Pedagang', 'Ibu Rumah Tangga', 'Freelancer', 'Pensiunan', 'Sudah Meninggal', 'Lainnya']), 'penghasilan_ayah' => fake()->randomElement(['Kurang dari Rp1.000.000', 'Rp1.000.000 – Rp2.999.999', 'Rp3.000.000 – Rp4.999.999', 'Rp5.000.000 – Rp7.499.999', 'Rp7.500.000 – Rp9.999.999', 'Rp10.000.000 – Rp14.999.999', 'Rp15.000.000 atau lebih', 'Tidak Berpenghasilan']), 'no_telepon_ayah' => fake()->phoneNumber(), 'email_ayah' => fake()->unique()->safeEmail(), 'alamat_ayah' => fake()->address(),
                    'nama_ibu_kandung' => fake()->name('female'), 'tanggal_lahir_ibu' => fake()->date(), 'pendidikan_terakhir_ibu' => 'S1', 'pekerjaan_ibu' => fake()->randomElement(['Tidak Bekerja', 'Karyawan Swasta', 'Pegawai Negeri Sipil (PNS)', 'TNI / Polri', 'Wiraswasta / Pengusaha', 'Profesional', 'Guru / Dosen', 'Tenaga Kesehatan', 'Petani', 'Peternak', 'Nelayan', 'Pedagang', 'Ibu Rumah Tangga', 'Freelancer', 'Pensiunan', 'Sudah Meninggal', 'Lainnya']), 'penghasilan_ibu' => fake()->randomElement(['Kurang dari Rp1.000.000', 'Rp1.000.000 – Rp2.999.999', 'Rp3.000.000 – Rp4.999.999', 'Rp5.000.000 – Rp7.499.999', 'Rp7.500.000 – Rp9.999.999', 'Rp10.000.000 – Rp14.999.999', 'Rp15.000.000 atau lebih', 'Tidak Berpenghasilan']), 'no_telepon_ibu' => fake()->phoneNumber(), 'email_ibu' => fake()->unique()->safeEmail(), 'alamat_ibu' => fake()->address(),
                ];
            } elseif ($type === UserType::Dosen) {
                $base += ['nidn' => fake()->unique()->numerify('########')];
            }

            $user->profile()->create($base);
        });
    }

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => fn (): int => Role::system(UserType::Mahasiswa)->id,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->ofType(UserType::Admin);
    }

    public function dosen(): static
    {
        return $this->ofType(UserType::Dosen);
    }

    public function mahasiswa(): static
    {
        return $this->ofType(UserType::Mahasiswa);
    }

    /**
     * Gunakan role bawaan sistem untuk jenis pengguna tertentu.
     */
    public function ofType(UserType $type): static
    {
        return $this->state(fn (): array => ['role_id' => Role::system($type)->id]);
    }

    public function withRole(Role $role): static
    {
        return $this->state(fn (): array => ['role_id' => $role->id]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
