# Standar Tampilan (UI) SIA VD

Semua halaman memakai kelas standar di `resources/css/app.css` (bagian `@layer components`) dan
varian komponen `Button`/`Input`, bukan menulis ulang warna, ukuran, atau radius di tiap halaman.
Utilitas Tailwind tetap boleh ditambahkan untuk tata letak (grid, lebar kolom, perataan), tetapi
**jangan menimpa** tinggi, radius, warna, atau ukuran huruf komponen standar.

## Kerangka halaman

```vue
<AppLayout :breadcrumbs="…">
    <div class="halaman">
        <div class="konten">            <!-- daftar, detail, dasbor: lebar maks 1200px -->
        <div class="konten-form">       <!-- form tambah/edit satu entitas: isi maks 900px, rata kiri sejajar halaman daftar -->
            <div class="kepala-halaman">
                <div>
                    <h1 class="judul-halaman">Judul</h1>
                    <p class="deskripsi-halaman">Satu kalimat keterangan.</p>
                </div>
                <!-- tombol aksi utama di kanan -->
            </div>
            …
```

- Jarak antarbagian halaman = `gap-6` (sudah di `.konten`/`.konten-form`), jangan pakai `mb-*`/`mt-*` untuk memisahkan bagian utama.
- Halaman bertab (Pengaturan Sistem, Pengaturan Profil) memakai lebar dari layout-nya.

## Tipografi

| Unsur | Kelas | Ukuran |
|---|---|---|
| Judul halaman (h1) | `judul-halaman` | 24px bold |
| Deskripsi halaman | `deskripsi-halaman` | 14px abu |
| Judul bagian/kartu (h2) | `judul-bagian` | 16px semibold |
| Label field | `label-isian` | 14px medium |
| Teks isi, sel tabel, field | `text-sm` | 14px |
| Teks bantu/keterangan kecil | `teks-bantu` | 12px abu |
| Kepala tabel | otomatis dari `.tabel` | 12px kapital |

Tidak memakai `text-[15px]`, `text-[26px]`, `text-[22px]`.

## Kartu & pemberitahuan

- Kartu: `class="kartu p-6"` (padding kartu form/detail `p-6`; di HP boleh `p-4 sm:p-6`).
- Flash/pesan: `alert-sukses`, `alert-gagal`, `alert-info` (tanpa bayangan khusus).

## Field form & filter

- Input: komponen `<Input>` tanpa kelas gaya tambahan (sudah `isian`: tinggi 40px, radius 8px, 14px).
- Select bawaan: `<select class="isian isian-pilih">`; di bilah filter pakai `<SelectFilter>`.
- Textarea: `<textarea class="isian isian-area">`.
- Dropdown dengan pencarian: `<SearchSelect>`; tanggal: `<DatePicker>`/`<DateTimePicker>`; jam: `<TimePicker>`.
- Label: `<Label class="label-isian">`; satu field = `<div class="grid gap-2">` label + field + `InputError`.
- Grid form 2 kolom: `grid items-start gap-4 sm:grid-cols-2`.
- Bilah filter di atas tabel:

```vue
<div class="bilah-filter">
    <div class="kolom-cari">
        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
        <Input v-model="search" placeholder="Cari …" class="pl-9" />
    </div>
    <SelectFilter …/>            <!-- filter lain -->
    <p class="info-jumlah sm:ml-auto"><span class="font-medium text-black">{{ total }}</span> data</p>
</div>
```

## Tombol

Pakai komponen `<Button>` dengan varian, **tanpa** kelas warna/radius/padding tambahan:

| Keperluan | Kode |
|---|---|
| Aksi utama (Simpan, Tambah) | `<Button>` |
| Aksi kedua (Kembali, Batal, Unduh) | `<Button variant="outline">` |
| Hapus/tolak (aksi merusak) | `<Button variant="destructive">` |
| Tombol kecil di kartu/baris tabel (tinggi 32px, sejajar tombol ikon) | `size="sm"` |
| Ikon aksi di tabel | `<Button variant="outline" size="icon-sm">` + warna ikon (`text-[#0075de]` detail, `text-[#2a9d99]` edit, `text-[#dd5b00]` hapus) |

- Tautan berbentuk tombol: `<Button as-child><Link :href="…">Teks</Link></Button>` (jangan `<Link><Button>` atau `<button><Button>` bersarang).
- Tombol simpan form: kanan bawah kartu, `<div class="flex justify-end gap-2">` (Batal outline di kiri Simpan).

## Tabel

```vue
<div class="tabel-wadah">
    <div class="tabel-gulir">
        <table class="tabel min-w-[720px]">
            <thead>
                <tr>
                    <th class="kolom-no">No</th>
                    <th>Nama</th>
                    <th class="text-right">Jumlah</th>
                    <th class="kolom-aksi">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(item, i) in data" :key="item.id">
                    <td class="kolom-no">{{ (paginasi.from ?? 1) + i }}</td>   <!-- tanpa paginasi: i + 1 -->
                    <td class="font-medium text-black">{{ item.nama }}</td>
                    <td class="text-right tabular-nums">…</td>
                    <td class="kolom-aksi"><div class="aksi-tabel">…</div></td>
                </tr>
                <tr v-if="!data.length" class="baris-kosong">
                    <td :colspan="4" class="tabel-kosong">Belum ada data.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
```

- **Setiap tabel data wajib punya kolom `No`** paling kiri (header teks `No`), berurutan lintas halaman paginasi.
- Padding sel, warna, ukuran huruf, garis, dan hover berasal dari `.tabel`; sel cukup diberi kelas perataan/penekanan (`text-right`, `text-center`, `font-medium text-black`, `tabular-nums`, lebar).
- Kolom nominal/angka rata kanan + `tabular-nums`; kolom aksi `kolom-aksi` (kanan).
- Tabel di dalam kartu lain boleh tanpa `tabel-wadah`, tetapi tetap `tabel`.

## Warna

Biru utama `#0075de` (hover `#005bab`), teks `#31302e`/hitam, teks sekunder `#615d59`, abu terang
`#a39e98`, garis `#e6e6e6`, garis field `#d8d5d2`, latar halaman `#f6f5f4`, sukses `#1aae39`, peringatan/hapus `#dd5b00`.
