<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { labelStatusPmb } from '@/lib/pmb';
import { rupiah } from '@/lib/tagihanRemidi';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Opsi = { value: string | number; label: string };

const page = usePage<{ flash: { success?: string; error?: string } }>();
const props = defineProps<{ pendaftar: Record<string, any>; opsi: Record<string, Opsi[]> }>();
const p = computed(() => props.pendaftar);

const label = (jenis: string, nilai: unknown) =>
    nilai === null || nilai === undefined || nilai === ''
        ? '—'
        : (props.opsi[jenis]?.find((o) => String(o.value) === String(nilai))?.label ?? String(nilai));
const formatDate = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(`${value.slice(0, 10)}T00:00:00`))
        : '—';
const isi = (nilai: unknown) => (nilai === null || nilai === undefined || nilai === '' ? '—' : String(nilai));

const pindahan = computed(() => p.value.status_masuk === 'P');
const bagian = computed(() => [
    {
        judul: 'Data Diri',
        baris: [
            ['Nama lengkap', p.value.nama],
            ['Tempat, tanggal lahir', `${p.value.tempat_lahir}, ${formatDate(p.value.tanggal_lahir)}`],
            ['Nama ibu', p.value.nama_ibu],
            ['Agama', p.value.agama?.nama],
            ['Jenis kelamin', label('jenis_kelamin', p.value.jenis_kelamin)],
            ['Status perkawinan', label('status_sipil', p.value.status_sipil)],
        ],
    },
    {
        judul: 'Informasi Detail',
        baris: [
            ['NIK', p.value.nik],
            ['Kewarganegaraan', label('kewarganegaraan', p.value.kewarganegaraan)],
            ['NPWP', isi(p.value.npwp)],
            ['Alamat', `${p.value.jalan}, Dusun ${p.value.dusun}, RT ${p.value.rt}/RW ${p.value.rw}, Kel. ${p.value.kelurahan}`],
            ['Kecamatan', p.value.kecamatan ? `${p.value.kecamatan.nama} (${p.value.kecamatan.kode})` : '—'],
            ['Kode pos', p.value.kode_pos],
            ['Alat transportasi', label('alat_transportasi', p.value.alat_transportasi)],
            ['Jenis tinggal', label('jenis_tinggal', p.value.jenis_tinggal)],
            ['Jenis masuk', label('jenis_masuk', p.value.jenis_masuk)],
            ['Email', p.value.email],
            ['No. HP', p.value.hp],
            ['No. HP wali/ortu', isi(p.value.telepon_wali)],
            ['Penerima KPS', p.value.penerima_kps ? `Ya (${p.value.nomor_kps})` : 'Tidak'],
            ['Jenis pembiayaan', label('jenis_pembiayaan', p.value.jenis_pembiayaan)],
            ['Jumlah pembiayaan', p.value.jumlah_pembiayaan === null ? '—' : rupiah(p.value.jumlah_pembiayaan)],
        ],
    },
    {
        judul: 'Pilihan Jurusan',
        baris: [
            ['Kelas', label('kelas', p.value.kelas)],
            ['Program studi', [p.value.program_studi?.jenjang, p.value.program_studi?.nama_prodi].filter(Boolean).join(' ')],
            ['Status calon mahasiswa', label('status_masuk', p.value.status_masuk)],
            ...(pindahan.value
                ? [
                      ['Asal perguruan tinggi', isi(p.value.asal_perguruan_tinggi)],
                      ['Jenjang asal', label('jenjang', p.value.jenjang_asal)],
                      ['Program studi asal', isi(p.value.prodi_asal)],
                      ['NIM asal', isi(p.value.nim_asal)],
                      ['SKS diakui', isi(p.value.sks_diakui)],
                  ]
                : [
                      ['Asal sekolah', isi(p.value.asal_sekolah)],
                      ['NISN', isi(p.value.nisn)],
                      ['Nilai UN', isi(p.value.nilai_un)],
                  ]),
            ['Agen / referensi', isi(p.value.agen)],
            ['Info dari', isi(p.value.info)],
        ],
    },
]);

const form = useForm({ nilai: p.value.nilai ?? '', status_pendaftaran: p.value.status_pendaftaran ?? '' });
const simpan = () =>
    form
        .transform((data) => ({ nilai: data.nilai === '' ? null : data.nilai, status_pendaftaran: data.status_pendaftaran || null }))
        .put(route('admin.pendaftar-pmb.update', p.value.id), { preserveScroll: true });

const dibawahMinimal = computed(() => form.nilai !== '' && p.value.periode && Number(form.nilai) < p.value.periode.nilai_minimal);

const konfirmasiHapus = ref(false);
const hapus = () => router.delete(route('admin.pendaftar-pmb.destroy', p.value.id), { onFinish: () => (konfirmasiHapus.value = false) });
</script>

<template>
    <Head :title="`Pendaftar ${p.nomor_pendaftaran}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Data Pendaftar PMB', href: route('admin.pendaftar-pmb.index') },
            { title: p.nomor_pendaftaran, href: route('admin.pendaftar-pmb.show', p.id) },
        ]"
    >
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ p.nama }}</h1>
                        <p class="deskripsi-halaman">
                            {{ p.nomor_pendaftaran }} · periode {{ p.periode?.kode }} · daftar {{ formatDate(p.created_at) }}
                            <span
                                class="ml-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium"
                                :class="labelStatusPmb(p.status_pendaftaran).kelas"
                                >{{ labelStatusPmb(p.status_pendaftaran).label }}</span
                            >
                        </p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route('admin.pendaftar-pmb.index')">Kembali</Link></Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>

                <form class="kartu p-6" @submit.prevent="simpan">
                    <h2 class="judul-bagian">Hasil Seleksi</h2>
                    <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="nilai" class="label-isian">Nilai</Label>
                            <Input id="nilai" v-model="form.nilai" type="number" min="0" max="100" step="0.01" />
                            <span class="teks-bantu" :class="{ 'text-[#b34700]': dibawahMinimal }">
                                Nilai minimal periode ini: {{ p.periode?.nilai_minimal }}{{ dibawahMinimal ? ' — nilai di bawah minimal' : '' }}
                            </span>
                            <InputError :message="form.errors.nilai" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="status_pendaftaran" class="label-isian">Status Pendaftaran</Label>
                            <select id="status_pendaftaran" v-model="form.status_pendaftaran" class="isian isian-pilih">
                                <option value="">Menunggu</option>
                                <option value="lulus">Lulus</option>
                                <option value="ditolak">Ditolak</option>
                            </select>
                            <InputError :message="form.errors.status_pendaftaran" />
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <Button type="submit" :disabled="form.processing">Simpan Hasil</Button>
                    </div>
                </form>

                <section v-for="b in bagian" :key="b.judul" class="kartu p-6">
                    <h2 class="judul-bagian">{{ b.judul }}</h2>
                    <dl class="mt-4 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-[220px_1fr]">
                        <template v-for="[nama, nilai] in b.baris" :key="nama">
                            <dt class="text-[#615d59]">{{ nama }}</dt>
                            <dd class="text-black dark:text-foreground">{{ nilai ?? '—' }}</dd>
                        </template>
                    </dl>
                </section>

                <div class="flex justify-end">
                    <Button variant="outline" class="text-[#dd5b00]" @click="konfirmasiHapus = true">Hapus Pendaftar</Button>
                </div>

                <AlertModal
                    :open="konfirmasiHapus"
                    description="Anda yakin ingin menghapus data pendaftar ini?"
                    confirm-text="Ya"
                    cancel-text="Batal"
                    @update:open="konfirmasiHapus = $event"
                    @confirm="hapus"
                    @cancel="konfirmasiHapus = false"
                />
            </div>
        </div>
    </AppLayout>
</template>
