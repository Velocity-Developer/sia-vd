<?php

use App\Excel;
use App\Impor\Impor;
use App\Models\DosenProfile;
use App\Models\LogAktivitas;
use App\Models\MahasiswaProfile;
use App\Models\MataKuliah;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Berkas .xlsx uji: baris pertama judul kolom template, lalu baris data.
 *
 * @param  list<list<string>>  $baris
 */
function berkasImpor(string $jenis, array $baris): UploadedFile
{
    $buku = new Spreadsheet;
    $judul = array_map(fn (array $k): string => $k[0], array_values(Impor::untuk($jenis)->kolom()));
    $buku->getActiveSheet()->fromArray([$judul, ...$baris], null, 'A1', true);
    $path = tempnam(sys_get_temp_dir(), 'impor').'.xlsx';
    IOFactory::createWriter($buku, 'Xlsx')->save($path);

    return new UploadedFile($path, "impor-{$jenis}.xlsx", 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('keeps template column keys equal to their headers', function () {
    foreach (array_keys(Impor::JENIS) as $jenis) {
        foreach (Impor::untuk($jenis)->kolom() as $kunci => $k) {
            expect(Excel::kunci($k[0]))->toBe($kunci);
        }
    }
});

it('imports students only when every row is valid', function () {
    $prodi = createMateriKelasKuliah()->mataKuliah->prodi;
    $dosen = User::factory()->dosen()->create()->dosenProfile;
    $dosen->update(['nidn' => '0911223344', 'status' => 'Aktif']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.impor.template', 'mahasiswa'))->assertOk();

    // NIM, Nama, Email, Username, Kata Sandi, JK, Tempat, Tgl, Agama, Telp, Alamat, WN, Prodi, Angkatan, Status, Ibu, NIDN Wali, NIK, NISN, Sekolah
    $baik = ['2501001', 'Siti Aminah', 'Siti@Contoh.test', '', '', 'P', 'Makassar', '21/03/2005', 'islam', '081234', 'Jl. A', '', $prodi->kode_prodi, '2025', '', 'Hasnah', '0911223344', '', '', ''];
    $salah = ['2501001', 'Budi', 'budi@contoh.test', '', '', 'X', 'Gowa', '2005-01-01', 'Islam', '0812', 'Jl. B', '', 'TIDAK-ADA', '2025', '', 'Ani', '', '', '', ''];

    $this->actingAs($admin)->post(route('admin.impor.store', 'mahasiswa'), ['berkas' => berkasImpor('mahasiswa', [$baik, $salah])])
        ->assertSessionHas('error')
        ->assertSessionHas('impor_hasil', fn (array $h): bool => $h['berhasil'] === 0
            && collect($h['galat'])->contains(fn (string $g): bool => str_starts_with($g, 'Baris 3:') && str_contains($g, 'NIM sama dengan baris 2'))
            && collect($h['galat'])->contains('Baris 3: Kode Prodi tidak ditemukan.'));
    expect(MahasiswaProfile::query()->where('nim', '2501001')->exists())->toBeFalse();

    $this->actingAs($admin)->post(route('admin.impor.store', 'mahasiswa'), ['berkas' => berkasImpor('mahasiswa', [$baik])])
        ->assertSessionHas('success');
    $mhs = MahasiswaProfile::query()->where('nim', '2501001')->sole();
    expect($mhs->user->only(['username', 'email']))->toBe(['username' => '2501001', 'email' => 'siti@contoh.test'])
        ->and($mhs->user->hasVerifiedEmail())->toBeTrue()
        ->and($mhs->only(['jenis_kelamin', 'agama', 'status', 'prodi_id', 'dosen_wali_id', 'kewarganegaraan']))
        ->toBe(['jenis_kelamin' => 'Perempuan', 'agama' => 'Islam', 'status' => 'Aktif', 'prodi_id' => $prodi->id, 'dosen_wali_id' => $dosen->id, 'kewarganegaraan' => 'Indonesia'])
        ->and($mhs->tanggal_lahir->toDateString())->toBe('2005-03-21');

    // Satu log untuk seluruh impor.
    expect(LogAktivitas::query()->where('objek_tipe', 'Impor')->count())->toBe(1)
        ->and(LogAktivitas::query()->where('objek_tipe', 'MahasiswaProfile')->count())->toBe(0);
});

it('imports lecturers and courses', function () {
    $prodi = createMateriKelasKuliah()->mataKuliah->prodi;
    $admin = User::factory()->admin()->create();

    $dosen = ['0012345678', 'Dr. Rahmat', 'rahmat@contoh.test', '', 'rahasia123', 'L', 'Makassar', '1980-02-02', 'Islam', '0812', 'Jl. C', '', $prodi->kode_prodi, 'Lektor', 'S3', 'Dosen Tetap', ''];
    $this->actingAs($admin)->post(route('admin.impor.store', 'dosen'), ['berkas' => berkasImpor('dosen', [$dosen])])->assertSessionHas('success');
    $profil = DosenProfile::query()->where('nidn', '0012345678')->sole();
    expect($profil->user->username)->toBe('0012345678')->and(Hash::check('rahasia123', $profil->user->password))->toBeTrue();

    $mk = [['KEP101', 'Anatomi', '3', '1', '', '', $prodi->kode_prodi], ['KEP499', 'Skripsi', '6', '8', 'Wajib', 'TA', $prodi->kode_prodi]];
    $this->actingAs($admin)->post(route('admin.impor.store', 'mata-kuliah'), ['berkas' => berkasImpor('mata-kuliah', $mk)])->assertSessionHas('success');
    expect(MataKuliah::query()->where('kode_matkul', 'KEP499')->value('jenis_penilaian'))->toBe(MataKuliah::TUGAS_AKHIR);

    $this->actingAs(User::factory()->dosen()->create())->get(route('admin.impor.index', 'mahasiswa'))->assertForbidden();
});
