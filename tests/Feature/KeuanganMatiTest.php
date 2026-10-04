<?php

use App\Models\JenisBiaya;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\PengajuanSusulan;
use App\Models\PengaturanAkademik;
use App\Models\Permission;
use App\Models\RemidiPeserta;
use App\Models\Role;
use App\Models\Ruang;
use App\Models\TagihanRemidi;
use App\Models\TagihanSemester;
use App\Models\TahunAkademik;
use App\Models\Ujian;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

beforeEach(function () {
    config(['client.fitur.keuangan.default' => false]);
    Storage::fake('local');
});

/**
 * Kelas final dengan dua mahasiswa bernilai E: [0] peserta remidi (tanpa tagihan), [1] bukan peserta.
 * Batas input nilai 10 Jan 2026, batas input nilai remidi 25 Jan 2026.
 *
 * @return array{0: KelasKuliah, 1: list<User>}
 */
function kelasRemidiTanpaTagihan(): array
{
    $kelas = createMateriKelasKuliah();
    $mhs = [];
    foreach ([0, 1] as $i) {
        $mhs[] = $user = User::factory()->mahasiswa()->create();
        Krs::create(['mahasiswa_id' => $user->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'nilai' => 'E']);
    }
    RemidiPeserta::create(['kelas_id' => $kelas->id, 'mahasiswa_id' => $mhs[0]->mahasiswaProfile->id, 'nilai_awal' => 'E', 'diusulkan' => true]);
    $kelas->update(['nilai_final_at' => '2026-01-05 10:00:00', 'remidi_dikunci_at' => '2026-01-06 10:00:00']);
    $kelas->tahunAkademik->update(['batas_input_nilai' => '2026-01-10', 'batas_bayar_remidi' => null, 'batas_input_nilai_remidi' => '2026-01-25']);

    return [$kelas->fresh(), $mhs];
}

function isianRemidiTanpaTagihan(KelasKuliah $kelas, array $ubah = []): array
{
    return [
        'kelas_id' => $kelas->id, 'jenis' => 'remidi', 'mode' => 'online_berkas', 'tanggal' => '2026-01-15', 'jam_mulai' => '09:00', 'jam_akhir' => '11:00',
        'status' => 'terbit', ...$ubah,
    ];
}

it('hides every billing route, permission, and dashboard block', function () {
    $admin = User::factory()->admin()->create();
    $mhs = User::factory()->mahasiswa()->create();
    $tagihan = TagihanSemester::create(['mahasiswa_id' => $mhs->mahasiswaProfile->id, 'tahun_akademik_id' => createMateriKelasKuliah()->tahun_akademik_id, 'status' => TagihanSemester::BELUM_BAYAR, 'total' => 1_000]);

    expect($admin->permissionKeys())->not->toContain('admin.tagihan')->not->toContain('admin.jenis-biaya')->toContain(Role::SUPER_PERMISSION)
        ->and($mhs->permissionKeys())->not->toContain('mahasiswa.info-biaya');

    $this->actingAs($admin)->get(route('admin.tagihan.index'))->assertNotFound();
    $this->actingAs($admin)->get(route('admin.jenis-biaya.index'))->assertNotFound();
    $this->actingAs($admin)->get(route('admin.tagihan-remidi.index'))->assertNotFound();
    $this->actingAs($admin)->get(route('admin.tagihan-susulan.index'))->assertNotFound();
    $this->actingAs($admin)->post(route('admin.tagihan.lunas', $tagihan))->assertNotFound();
    $this->actingAs($admin)->get(route('berkas.bukti-semester', $tagihan))->assertNotFound();
    $this->actingAs($admin)->put(route('admin.pengaturan-akademik.kunci-krs'), ['kunci_krs_aktif' => true])->assertNotFound();
    $this->actingAs($mhs)->get(route('mahasiswa.info-biaya-kuliah'))->assertNotFound();

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertInertia(fn ($page) => $page
        ->where('fitur.keuangan', false)
        ->where('tagihan', null)
        ->where('buktiTerbaru', null));
    expect($tagihan->fresh()->status)->toBe(TagihanSemester::BELUM_BAYAR);
});

it('keeps hidden billing permissions on a role saved from Kelola Role', function () {
    $admin = User::factory()->admin()->create();
    $role = $admin->role;
    $tagihan = Permission::where('key', 'admin.tagihan')->value('id');

    $this->actingAs($admin)->get(route('admin.roles.edit', $role))->assertInertia(fn ($page) => $page
        ->where('permissionGroups', fn ($groups) => ! collect($groups)->pluck('permissions')->flatten(1)->contains('key', 'admin.tagihan')));

    $terlihat = $role->permissions()->where('key', '!=', 'admin.tagihan')->pluck('permissions.id')->all();
    $this->actingAs($admin)->put(route('admin.roles.update', $role), [
        'name' => $role->name, 'user_type' => $role->user_type->value, 'description' => $role->description, 'permissions' => $terlihat,
    ])->assertSessionHasNoErrors();
    expect($role->permissions()->pluck('permissions.id'))->toContain($tagihan);

    // Tanpa izin itu sebelumnya, mengirim id tersembunyi tidak menambahkannya.
    $lain = Role::create(['name' => 'Staf', 'slug' => 'staf', 'user_type' => $role->user_type, 'is_system' => false]);
    $this->actingAs($admin)->put(route('admin.roles.update', $lain), [
        'name' => 'Staf', 'user_type' => $role->user_type->value, 'permissions' => [$tagihan],
    ])->assertSessionHasNoErrors();
    expect($lain->permissions()->count())->toBe(0);

    config(['client.fitur.keuangan.default' => true]);
    expect($admin->fresh()->permissionKeys())->toContain('admin.tagihan');
});

it('hides Jenis Biaya entirely, including the information categories', function () {
    $admin = User::factory()->admin()->create();
    $cuti = JenisBiaya::create(['kode' => 'CUTI', 'nama' => 'Biaya Cuti', 'cara_hitung' => JenisBiaya::TETAP, 'kategori' => JenisBiaya::CUTI, 'aktif' => true]);

    $this->actingAs($admin)->get(route('admin.jenis-biaya.create'))->assertNotFound();
    $this->actingAs($admin)->get(route('admin.jenis-biaya.edit', $cuti))->assertNotFound();
    $this->actingAs($admin)->post(route('admin.jenis-biaya.store'), [
        'kode' => 'WSD', 'nama' => 'Wisuda', 'cara_hitung' => JenisBiaya::TETAP, 'kategori' => JenisBiaya::WISUDA, 'aktif' => true, 'tarif' => [['nominal' => 500_000]],
    ])->assertNotFound();
    expect(JenisBiaya::query()->count())->toBe(1);

    config(['client.fitur.keuangan.default' => true]);
    $this->actingAs($admin)->get(route('admin.jenis-biaya.index'))->assertOk();
});

it('opens KRS even when the lock switch was left on with an unpaid bill', function () {
    $kelas = createMateriKelasKuliah();
    $kelas->tahunAkademik->update(['tanggal_krs_awal' => now()->subDay(), 'tanggal_krs_akhir' => now()->addDay()]);
    $mhs = User::factory()->mahasiswa()->create();
    $mhs->mahasiswaProfile->update(['prodi_id' => $kelas->mataKuliah->prodi_id, 'angkatan' => angkatanUntuk($kelas), 'status' => 'Aktif']);
    TagihanSemester::create(['mahasiswa_id' => $mhs->mahasiswaProfile->id, 'tahun_akademik_id' => $kelas->tahun_akademik_id, 'status' => TagihanSemester::BELUM_BAYAR, 'total' => 1_000]);
    PengaturanAkademik::current()->update(['kunci_krs_aktif' => true]);

    $this->actingAs($mhs->fresh())->get(route('mahasiswa.krs'))->assertInertia(fn ($page) => $page->component('Mahasiswa/Krs'));
    expect(PengaturanAkademik::current()->kunci_krs_aktif)->toBeTrue();

    config(['client.fitur.keuangan.default' => true]);
    $this->actingAs($mhs->fresh())->get(route('mahasiswa.krs'))->assertInertia(fn ($page) => $page->component('Mahasiswa/KrsTerkunci'));
});

it('runs remidi without bills: scheduled after the grade deadline and open to every locked participant', function () {
    [$kelas, $mhs] = kelasRemidiTanpaTagihan();
    $admin = User::factory()->admin()->create();
    $this->travelTo('2026-01-09 10:00:00');

    $this->actingAs($admin)->get(route('admin.ujian.create', ['tahun_akademik_id' => $kelas->tahun_akademik_id, 'jenis' => 'remidi']))
        ->assertInertia(fn ($page) => $page->where('kelasRemidiOptions.0.id', $kelas->id)->where('batasRemidi.awal', '2026-01-10'));
    $this->actingAs($admin)->post(route('admin.ujian.store'), isianRemidiTanpaTagihan($kelas, ['tanggal' => '2026-01-10']))
        ->assertSessionHasErrors(['tanggal' => 'Tanggal remidi harus sesudah batas input nilai (10 Jan 2026).']);
    $this->actingAs($admin)->post(route('admin.ujian.store'), isianRemidiTanpaTagihan($kelas))->assertSessionHasNoErrors();

    $ujian = Ujian::where('kelas_id', $kelas->id)->where('jenis', 'remidi')->sole();
    expect($ujian->bolehIkut($mhs[0]->mahasiswaProfile->id))->toBeTrue()
        ->and($ujian->alasanTidakBolehIkut($mhs[1]->mahasiswaProfile->id))->toBe('Anda bukan peserta remidi kelas ini.');

    // Daftar peserta di halaman kelas tidak memuat status tagihan lama sekalipun ada.
    TagihanRemidi::create([
        'remidi_peserta_id' => RemidiPeserta::first()->id, 'mahasiswa_id' => $mhs[0]->mahasiswaProfile->id, 'kelas_id' => $kelas->id,
        'rincian' => [], 'total' => 100_000, 'status' => TagihanRemidi::BELUM_BAYAR,
    ]);
    expect($ujian->fresh()->bolehIkut($mhs[0]->mahasiswaProfile->id))->toBeTrue();
    $this->actingAs($admin)->get(route('admin.kelas-kuliah.show', $kelas))
        ->assertInertia(fn ($page) => $page->where('remidi.mahasiswa', fn ($m) => collect($m)->firstWhere('mahasiswa_id', $mhs[0]->mahasiswaProfile->id)['tagihan'] === null));
});

it('lets the admin reopen the remidi list despite old bills, and drops the paid note from the attendance PDF', function () {
    [$kelas, $mhs] = kelasRemidiTanpaTagihan();
    $admin = User::factory()->admin()->create();
    TagihanRemidi::create([
        'remidi_peserta_id' => RemidiPeserta::first()->id, 'mahasiswa_id' => $mhs[0]->mahasiswaProfile->id, 'kelas_id' => $kelas->id,
        'rincian' => [], 'total' => 100_000, 'status' => TagihanRemidi::BELUM_BAYAR,
    ]);

    $this->travelTo('2026-01-09 10:00:00');
    $this->actingAs($admin)->post(route('admin.ujian.store'), isianRemidiTanpaTagihan($kelas))->assertSessionHasNoErrors();
    $ujian = Ujian::where('kelas_id', $kelas->id)->where('jenis', 'remidi')->sole();

    $data = null;
    View::composer('pdf.peserta-ujian', function ($view) use (&$data) {
        $data = $view->getData();
    });
    $this->actingAs($admin)->get(route('admin.ujian.daftar-hadir', $ujian))->assertOk();
    expect(view('pdf.peserta-ujian', $data)->render())->toContain('Peserta remidi.')->not->toContain('lunas');

    config(['client.fitur.keuangan.default' => true]);
    $this->actingAs($admin)->get(route('admin.ujian.daftar-hadir', $ujian))->assertOk();
    expect(view('pdf.peserta-ujian', $data)->render())->toContain('Peserta remidi yang tagihan remidinya sudah lunas.');
    $this->actingAs($admin)->post(route('admin.kelas-kuliah.remidi.buka', $kelas))->assertSessionHas('error');
    expect($kelas->fresh()->remidi_dikunci_at)->not->toBeNull();

    config(['client.fitur.keuangan.default' => false]);
    $this->actingAs($admin)->post(route('admin.kelas-kuliah.remidi.buka', $kelas))->assertSessionHas('success');
    expect($kelas->fresh()->remidi_dikunci_at)->toBeNull();
});

it('only needs the remidi grade deadline in Tahun Akademik and keeps the old payment deadline', function () {
    [$kelas] = kelasRemidiTanpaTagihan();
    $admin = User::factory()->admin()->create();
    $ta = $kelas->tahunAkademik;
    $akhir = $ta->tanggal_akhir->copy();
    $ta->update(['batas_input_nilai' => $akhir->copy()->addDays(5), 'batas_bayar_remidi' => $akhir->copy()->addDays(7), 'batas_input_nilai_remidi' => null]);

    $this->actingAs($admin)->post(route('admin.ujian.store'), isianRemidiTanpaTagihan($kelas))
        ->assertSessionHasErrors(['kelas_id' => 'Isi dulu Batas Input Nilai Remidi di menu Tahun Akademik.']);

    $isian = [
        ...$ta->only(['tahun', 'semester', 'tanggal_krs_awal', 'tanggal_krs_akhir']),
        'tanggal_mulai' => $ta->tanggal_mulai->toDateString(), 'tanggal_akhir' => $ta->tanggal_akhir->toDateString(),
        'batas_input_nilai' => $akhir->copy()->addDays(5)->toDateString(), 'batas_bayar_remidi' => '2020-01-01', 'status' => true,
    ];
    // Tanpa batas bayar, batas input nilai remidi cukup sesudah batas input nilai.
    $this->actingAs($admin)->put(route('admin.tahun-akademik.update', $ta), [...$isian, 'batas_input_nilai_remidi' => $akhir->copy()->addDays(5)->toDateString()])
        ->assertSessionHasErrors('batas_input_nilai_remidi');
    $this->actingAs($admin)->put(route('admin.tahun-akademik.update', $ta), [...$isian, 'batas_input_nilai_remidi' => $akhir->copy()->addDays(6)->toDateString()])
        ->assertSessionHasNoErrors();

    expect($ta->fresh()->batas_bayar_remidi->toDateString())->toBe($akhir->copy()->addDays(7)->toDateString())
        ->and($ta->fresh()->batas_input_nilai_remidi->toDateString())->toBe($akhir->copy()->addDays(6)->toDateString());
});

it('runs susulan without bills once the application is approved', function () {
    $kelas = createMateriKelasKuliah();
    $mhs = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mhs->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);
    $ruang = Ruang::firstOrCreate(['kode_ruang' => 'R-KM'], ['nama_ruang' => 'Ruang', 'kapasitas' => 40]);
    $uts = Ujian::create([
        'kelas_id' => $kelas->id, 'jenis' => 'uts', 'mode' => 'tatap_muka', 'tanggal' => '2025-10-06', 'jam_mulai' => '13:00', 'jam_akhir' => '15:00',
        'ruang_id' => $ruang->id, 'status' => 'terbit',
    ]);
    $admin = User::factory()->admin()->create();

    $this->travelTo('2025-10-06 16:00:00');
    $this->actingAs($mhs)->post(route('mahasiswa.ujian.susulan', $uts), ['alasan' => 'Sakit', 'lampiran' => [UploadedFile::fake()->create('s.pdf', 10, 'application/pdf')]])
        ->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.ujian-susulan.setujui', PengajuanSusulan::sole()))
        ->assertSessionHas('success', 'Pengajuan ujian susulan disetujui. Jadwalkan susulannya di menu Jadwal Ujian.');

    $this->travelTo('2025-10-07 09:00:00');
    $this->actingAs($admin)->get(route('admin.ujian.index', ['tahun_akademik_id' => $kelas->tahun_akademik_id]))
        ->assertInertia(fn ($page) => $page->where('susulanSiap.uts', 1));
    $this->actingAs($admin)->post(route('admin.ujian.store'), [
        'kelas_id' => $kelas->id, 'jenis' => 'uts_susulan', 'mode' => 'online_berkas', 'tanggal' => '2025-10-13', 'jam_mulai' => '09:00', 'jam_akhir' => '11:00', 'status' => 'terbit',
    ])->assertSessionHasNoErrors();

    $susulan = Ujian::where('jenis', 'uts_susulan')->sole();
    expect($susulan->bolehIkut($mhs->mahasiswaProfile->id))->toBeTrue();
    $this->actingAs($mhs)->get(route('mahasiswa.ujian.show', $susulan))->assertInertia(fn ($page) => $page->where('bolehIkut', true));
});

it('saves the susulan settings without the payment deadline and keeps its old value', function () {
    $admin = User::factory()->admin()->create();
    PengaturanAkademik::current()->update(['batas_bayar_susulan_hari' => 7]);

    $this->actingAs($admin)->put(route('admin.pengaturan-akademik.susulan'), ['batas_pengajuan_susulan_hari' => 5, 'batas_bayar_susulan_hari' => 99])
        ->assertSessionHasNoErrors();

    expect(PengaturanAkademik::current()->batas_pengajuan_susulan_hari)->toBe(5)
        ->and(PengaturanAkademik::current()->batas_bayar_susulan_hari)->toBe(7);
});

it('applies for leave without fee information or payment proof', function () {
    $tahun = tahunCutiKeuanganMati();
    $mhs = User::factory()->mahasiswa()->create();
    JenisBiaya::create(['kode' => 'CUTI', 'nama' => 'Biaya Cuti', 'cara_hitung' => JenisBiaya::TETAP, 'kategori' => JenisBiaya::CUTI, 'aktif' => true])
        ->tarif()->create(['nominal' => 250_000]);

    // Biaya lama yang masih tersimpan tidak ditampilkan karena menu Jenis Biaya ikut mati.
    $this->actingAs($mhs)->get(route('mahasiswa.pengajuan-cuti'))->assertInertia(fn ($page) => $page->where('biaya', []));
    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-cuti.ajukan'), ['tahun_akademik_id' => $tahun->id, 'alasan' => 'Bekerja di luar kota.'])
        ->assertSessionHasNoErrors();
});

function tahunCutiKeuanganMati(): TahunAkademik
{
    return TahunAkademik::create([
        'tahun' => '2026/2027', 'semester' => 'Ganjil',
        'tanggal_mulai' => now()->subMonth()->toDateString(), 'tanggal_akhir' => now()->addMonths(4)->toDateString(),
        'tanggal_krs_awal' => now()->subMonth()->toDateString(), 'tanggal_krs_akhir' => now()->subWeeks(2)->toDateString(),
        'tanggal_cuti_awal' => now()->subDay()->toDateString(), 'tanggal_cuti_akhir' => now()->addWeek()->toDateString(),
        'status' => true,
    ]);
}
