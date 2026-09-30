# SIA VD — Sistem Informasi Akademik

Aplikasi akademik kampus: KRS, perkuliahan (jadwal, materi, tugas, quiz), presensi, ujian, nilai & remidi,
ujian susulan, tugas akhir & wisuda, cuti, dan keuangan. Dibangun dengan Laravel 12 + Inertia + Vue 3.

Satu kode dipakai banyak kampus (klien). Perbedaan antarklien diatur lewat **feature flag** dan pengaturan di
aplikasi, bukan lewat cabang kode.

Dokumentasi lain:

- [docs/system-flow.md](docs/system-flow.md) — alur proses bisnis per fitur.
- [docs/standar-ui.md](docs/standar-ui.md) — standar tampilan yang wajib diikuti halaman baru.
- [CHANGELOG.md](CHANGELOG.md) — riwayat perubahan.

## Kebutuhan

- PHP 8.2+ dengan ekstensi `pdo_mysql`, `mbstring`, `dom`, `gd`, `intl`, `fileinfo`.
- MySQL 8 / MariaDB 10.6+ (beberapa migrasi memakai `ALTER ... MODIFY` khusus MySQL; SQLite hanya untuk tes).
- Node.js 20+ untuk build aset.
- Composer 2.

## Feature flag per klien

Fitur yang bisa berbeda antarklien terdaftar di [config/client.php](config/client.php) dan dibaca lewat
`App\Feature::aktif('nama')`.

| Flag | Env | Bawaan | Nyala | Mati |
|---|---|---|---|---|
| `kelola_role` | `FEATURE_KELOLA_ROLE`, `LOCK_KELOLA_ROLE` | mati | Admin punya menu **Kelola Role**. | Menu dan rute Kelola Role admin 404. Role disusun developer di panel `/dev/roles`; admin tetap bisa memberi role itu ke user. |
| `keuangan` | `FEATURE_KEUANGAN`, `LOCK_KEUANGAN` | mati | Jenis biaya, tagihan semester/remidi/susulan, bukti bayar, Info Biaya Kuliah, kunci KRS sampai lunas. | Menu tagihan & info biaya disembunyikan (404), KRS tidak dikunci tagihan, remidi dan ujian susulan **gratis**. Bukti bayar cuti/pendadaran/wisuda tetap wajib. |

Fitur lain (presensi, pindah kelas, remidi, ujian susulan) **bukan** flag: selalu ada di semua klien.

### Urutan penentuan status

1. Nama tidak terdaftar di `config/client.php` → **mati**.
2. `LOCK_*=true` → ikut `FEATURE_*` di `.env`. Override database diabaikan dan tidak bisa disimpan.
3. Ada override di tabel `pengaturan_fitur` (diatur developer di `/dev/fitur`) → ikut override.
4. Selain itu → ikut `FEATURE_*` di `.env`.

Fitur yang punya `butuh` ikut mati bila salah satu dependensinya mati. Override dibaca dengan satu query dan
di-cache (kunci `fitur.override`); cache dibuang otomatis setiap kali override diubah.

Pakai `LOCK_*=true` untuk fitur yang sudah dipastikan di kontrak klien, supaya tidak bisa diubah dari panel.

### Menambah flag baru

1. Daftarkan di `config/client.php` (`label`, `keterangan`, `default`, `locked`, `butuh`).
2. Tambahkan `FEATURE_<NAMA>=false` dan `LOCK_<NAMA>=false` ke `.env.example` (dicek oleh `FeatureFlagTest`).
3. Pasang di kode: middleware rute `fitur:<nama>`, kunci `fitur` di menu `AppSidebar.vue`,
   `useFitur().aktif('<nama>')` di Vue, `Feature::aktif('<nama>')` di PHP. Izin menu milik fitur didaftarkan di
   `PermissionCatalog::FITUR` agar disembunyikan dari role selama fitur mati.
4. Tambahkan nama ke tipe `fitur` di `resources/js/types/index.ts`.
5. Tulis tes keadaan mati (lihat `KeuanganMatiTest`, `KelolaRoleMatiTest`). `tests/TestCase.php` menyalakan
   fitur lama untuk tes yang sudah ada.

## Panel developer

Panel `/dev` hanya terdaftar bila `DEV_PANEL=true`, dan hanya bisa dibuka akun ber-role **developer**
(disertai konfirmasi kata sandi).

- **Fitur Klien** (`/dev/fitur`): toggle tiap flag, tombol *Ikuti bawaan* untuk menghapus override, dan
  riwayat perubahan (tabel `log_pengaturan_fitur`). Flag terkunci tampil disabled dan ditolak 403. Perubahan
  yang melanggar dependensi ditolak.
- **Kelola Role** (`/dev/roles`): menyusun role dan hak aksesnya. Selalu tersedia untuk developer, terlepas
  dari flag `kelola_role`.

Role developer tidak pernah muncul di aplikasi dan hanya diberikan lewat perintah:

```bash
php artisan sia:developer <username>          # jadikan akun admin/karyawan sebagai developer
php artisan sia:developer <username> --cabut  # kembalikan ke role Admin
```

Rute `/dev` dibaca saat aplikasi boot. Setelah mengubah `DEV_PANEL` di server yang memakai cache, jalankan
`php artisan config:cache && php artisan route:cache` (atau `optimize:clear`).

## Contoh `.env` dua klien

Hanya bagian yang berbeda antarklien; sisanya ikut `.env.example`.

**Klien A — kampus swasta dengan pembayaran lewat sistem.** Keuangan dipastikan nyala dan dikunci; role
diatur developer.

```dotenv
APP_NAME="SIA Kampus A"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://sia.kampus-a.ac.id
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sia_kampus_a
DB_USERNAME=sia_kampus_a
DB_PASSWORD=ganti-dengan-sandi-kuat

FEATURE_KELOLA_ROLE=false
LOCK_KELOLA_ROLE=true
FEATURE_KEUANGAN=true
LOCK_KEUANGAN=true

DEV_PANEL=false
```

**Klien B — kampus yang pembayarannya masih di luar sistem.** Keuangan mati tetapi tidak dikunci, supaya
developer bisa menyalakannya dari panel setelah klien siap; panel developer dibuka.

```dotenv
APP_NAME="SIA Kampus B"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://akademik.kampus-b.ac.id
APP_TIMEZONE=Asia/Makassar

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sia_kampus_b
DB_USERNAME=sia_kampus_b
DB_PASSWORD=ganti-dengan-sandi-kuat

FEATURE_KELOLA_ROLE=false
LOCK_KELOLA_ROLE=false
FEATURE_KEUANGAN=false
LOCK_KEUANGAN=false

DEV_PANEL=true
```

Zona waktu yang tampil ke pengguna diatur lagi per institusi di **Pengaturan Sistem → Institusi**.

## Onboarding klien baru

1. **Ambil kode dan pasang dependensi**

   ```bash
   git clone https://github.com/Velocity-Developer/sia-vd.git sia-klien && cd sia-klien
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   ```

2. **Siapkan `.env`**: salin `.env.example`, isi database, `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false`,
   lalu tentukan flag sesuai kontrak klien (lihat contoh di atas).

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Database dan data dasar**

   ```bash
   php artisan migrate --force
   php artisan db:seed --force   # di production hanya menyinkronkan izin + role bawaan (Admin, Dosen, Mahasiswa)
   php artisan storage:link      # logo institusi dan gambar tampilan
   ```

   Jangan jalankan `DemoSeeder` di server klien: seeder itu menghapus data akademik untuk diganti data demo.

4. **Akun admin pertama** (production tidak punya akun bawaan):

   ```bash
   php artisan tinker
   ```

   ```php
   $u = App\Models\User::create([
       'name' => 'Admin Kampus', 'username' => 'admin', 'email' => 'admin@kampus.ac.id',
       'password' => 'ganti-sandi-awal', 'role_id' => App\Models\Role::system(App\UserType::Admin)->id,
   ]);
   $u->forceFill(['email_verified_at' => now()])->save();
   $u->adminProfile()->create(['nomor_induk' => 'ADM-001']);
   ```

5. **Akun developer** (bila panel dipakai): buat akun admin/karyawan dengan cara yang sama, lalu
   `php artisan sia:developer <username>` dan pastikan `DEV_PANEL=true`.

6. **Layanan dan cache**
   - Jalankan worker antrean (`QUEUE_CONNECTION=database`), misalnya sebagai layanan systemd:
     `php artisan queue:work --tries=3`.
   - Naikkan batas unggah PHP (`upload_max_filesize`, `post_max_size`) sesuai kebutuhan berkas kuliah.
   - Sesudah `.env` final: `php artisan config:cache && php artisan route:cache && php artisan view:cache`.

7. **Pengaturan di aplikasi** (masuk sebagai admin → **Pengaturan Sistem**)
   - **Institusi**: nama, singkatan, logo, alamat, zona waktu.
   - **Email**: SMTP untuk verifikasi email dan atur ulang kata sandi.
   - **Akademik**: skala nilai, batas SKS, jumlah pertemuan, syarat ujian, remidi, susulan, TA, cuti.
   - **Tampilan**: nama aplikasi, favicon, halaman masuk.

8. **Role dan fitur** (masuk sebagai developer → `/dev`)
   - Susun role tambahan (misalnya Staf Keuangan, Staf Akademik) di **Kelola Role**.
   - Periksa status tiap flag di **Fitur Klien**, lalu kunci (`LOCK_*=true`) flag yang sudah final.
   - Setelah selesai, pertimbangkan `DEV_PANEL=false` lalu `config:cache && route:cache`.

9. **Data awal**: admin mengisi tahun akademik, fakultas, program studi, ruang, mata kuliah, akun dosen dan
   mahasiswa, lalu kelas kuliah.

## Pengembangan

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed    # di non-production ikut mengisi data demo
composer run dev              # server, antrean, dan Vite sekaligus
```

Akun demo (dari `DemoSeeder`): admin `admin` / `11111`, dosen `22222` / `22222`, mahasiswa `33333` / `33333`.

Pemeriksaan sebelum commit:

```bash
php artisan test
vendor/bin/pint --test
npx eslint resources/js
npx vue-tsc --noEmit
```
