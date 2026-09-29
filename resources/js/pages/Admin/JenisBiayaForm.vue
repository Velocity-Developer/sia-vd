<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

type Tarif = { prodi_id: number | null; angkatan: number | null; nominal: number };
/** Baris tarif di form: angka disimpan sebagai teks karena isian number mengembalikan string. */
type BarisTarif = { prodi_id: number | null; angkatan: string; nominal: string };
type Jenis = {
    id: number;
    kode: string;
    nama: string;
    cara_hitung: string;
    kategori: string;
    keterangan: string | null;
    aktif: boolean;
    urutan: number;
    tarif: Tarif[];
};

const props = defineProps<{ jenis: Jenis | null; prodiOptions: { id: number; name: string }[] }>();

const form = useForm({
    kode: props.jenis?.kode ?? '',
    nama: props.jenis?.nama ?? '',
    cara_hitung: props.jenis?.cara_hitung ?? 'tetap',
    kategori: props.jenis?.kategori ?? 'semester',
    keterangan: props.jenis?.keterangan ?? '',
    aktif: props.jenis?.aktif ?? true,
    urutan: props.jenis?.urutan ?? 0,
    tarif: (props.jenis?.tarif ?? []).map(
        (tarif): BarisTarif => ({
            prodi_id: tarif.prodi_id,
            angkatan: tarif.angkatan === null ? '' : String(tarif.angkatan),
            nominal: String(tarif.nominal),
        }),
    ),
});

const perSks = computed(() => form.cara_hitung === 'per_sks');
// Remidi dan susulan ditagih per mata kuliah/ujian, bukan per semester.
const perMatkul = computed(() => form.kategori === 'remidi' || form.kategori === 'susulan');
// Biaya pendadaran/wisuda/cuti hanya informasi di Biaya Kuliah, nominalnya tetap.
const info = computed(() => ['pendadaran', 'wisuda', 'cuti'].includes(form.kategori));
const keteranganKategori = computed(
    () =>
        ({
            semester: 'Ikut dihitung saat menerbitkan tagihan semester.',
            remidi: 'Hanya dipakai saat menerbitkan tagihan remidi.',
            susulan: 'Hanya dipakai saat menerbitkan tagihan ujian susulan.',
            pendadaran:
                'Tidak ditagihkan: tampil sebagai informasi di Biaya Kuliah; mahasiswa mengunggah bukti bayar di form pendaftaran pendadaran.',
            wisuda: 'Tidak ditagihkan: tampil sebagai informasi di Biaya Kuliah; mahasiswa mengunggah bukti bayar di form pendaftaran wisuda.',
            cuti: 'Tidak ditagihkan: tampil sebagai informasi di Biaya Kuliah; mahasiswa mengunggah bukti bayar di form pengajuan cuti.',
        })[form.kategori as string] ?? '',
);
const keteranganHitung = computed(() => {
    if (info.value) return 'Nominal tetap sesuai tarif prodi/angkatan mahasiswa.';
    if (perMatkul.value)
        return perSks.value
            ? 'Nominal tarif dikali SKS mata kuliahnya.'
            : `Nominal tarif ditagihkan per ${form.kategori === 'susulan' ? 'ujian' : 'mata kuliah'}.`;

    return perSks.value
        ? 'Nominal tarif dikali jumlah SKS di KRS semester itu saat tagihan diterbitkan.'
        : 'Nominal tarif ditagihkan apa adanya setiap semester.';
});

const tambahTarif = () => form.tarif.push({ prodi_id: null, angkatan: '', nominal: '0' });
const hapusTarif = (index: number) => form.tarif.splice(index, 1);

const submit = () => {
    // Angkatan kosong berarti tarif berlaku untuk semua angkatan.
    form.transform((data) => ({
        ...data,
        tarif: data.tarif.map((tarif) => ({
            prodi_id: tarif.prodi_id,
            angkatan: tarif.angkatan === '' ? null : Number(tarif.angkatan),
            nominal: Number(tarif.nominal || 0),
        })),
    }));

    if (props.jenis) {
        form.put(route('admin.jenis-biaya.update', props.jenis.id));

        return;
    }

    form.post(route('admin.jenis-biaya.store'));
};

const rupiah = (nilai: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(nilai || 0);
</script>

<template>
    <Head :title="props.jenis ? 'Ubah Jenis Biaya' : 'Tambah Jenis Biaya'" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Jenis Biaya', href: route('admin.jenis-biaya.index') },
            { title: props.jenis ? 'Ubah' : 'Tambah', href: '#' },
        ]"
    >
        <div class="halaman">
            <div class="konten-form">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ props.jenis ? 'Ubah' : 'Tambah' }} Jenis Biaya</h1>
                        <p class="deskripsi-halaman">Tarif kosong berarti komponen ini tidak ditagihkan ke mahasiswa yang bersangkutan.</p>
                    </div>
                    <Button as-child variant="outline"><Link :href="route('admin.jenis-biaya.index')">Kembali</Link></Button>
                </div>

                <form class="flex flex-col gap-6" @submit.prevent="submit">
                    <section class="kartu p-6">
                        <h2 class="judul-bagian">Data Jenis Biaya</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="kode" class="label-isian">Kode</Label>
                                <Input id="kode" v-model="form.kode" placeholder="SPP-TETAP" required />
                                <InputError :message="form.errors.kode" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="nama" class="label-isian">Nama</Label>
                                <Input id="nama" v-model="form.nama" placeholder="SPP Tetap" required />
                                <InputError :message="form.errors.nama" />
                            </div>
                            <div class="grid content-start gap-2">
                                <Label for="kategori" class="label-isian">Kategori</Label>
                                <SelectFilter v-model="form.kategori" label="Kategori" penuh>
                                    <option value="semester">Tagihan semester</option>
                                    <option value="remidi">Remidi (per mata kuliah)</option>
                                    <option value="susulan">Ujian susulan (per ujian)</option>
                                    <option value="pendadaran">Pendadaran (informasi)</option>
                                    <option value="wisuda">Wisuda (informasi)</option>
                                    <option value="cuti">Cuti (informasi)</option>
                                </SelectFilter>
                                <p class="teks-bantu">
                                    {{ keteranganKategori }}
                                </p>
                                <InputError :message="form.errors.kategori" />
                            </div>
                            <div v-if="!info" class="grid content-start gap-2">
                                <Label for="cara_hitung" class="label-isian">Cara Hitung</Label>
                                <SelectFilter v-model="form.cara_hitung" label="Cara hitung" penuh>
                                    <option value="tetap">
                                        {{
                                            perMatkul
                                                ? form.kategori === 'susulan'
                                                    ? 'Tetap per ujian'
                                                    : 'Tetap per mata kuliah'
                                                : 'Tetap per semester'
                                        }}
                                    </option>
                                    <option value="per_sks">{{ perMatkul ? 'Per SKS mata kuliah' : 'Per SKS yang diambil' }}</option>
                                </SelectFilter>
                                <p class="teks-bantu">{{ keteranganHitung }}</p>
                                <InputError :message="form.errors.cara_hitung" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="urutan" class="label-isian">Urutan Tampil</Label>
                                <Input id="urutan" v-model="form.urutan" type="number" min="0" max="999" />
                                <InputError :message="form.errors.urutan" />
                            </div>
                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="keterangan" class="label-isian">Keterangan</Label>
                                <Input id="keterangan" v-model="form.keterangan" placeholder="Opsional" />
                                <InputError :message="form.errors.keterangan" />
                            </div>
                        </div>

                        <Label for="aktif" class="label-isian mt-4 flex w-fit items-center gap-2.5 font-normal">
                            <Checkbox id="aktif" v-model="form.aktif" />
                            <span>Aktif — ikut ditagihkan saat tagihan diterbitkan</span>
                        </Label>
                    </section>

                    <section class="kartu p-6">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="judul-bagian">Tarif</h2>
                                <p class="teks-bantu mt-1">
                                    Kosongkan program studi atau angkatan berarti berlaku untuk semua. Tarif paling khusus yang dipakai.
                                </p>
                            </div>
                            <Button type="button" variant="outline" size="sm" @click="tambahTarif"> <Plus /> Tambah Tarif </Button>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div
                                v-for="(tarif, index) in form.tarif"
                                :key="index"
                                class="grid content-start gap-3 rounded-lg border border-[#e6e6e6] p-3 dark:border-border sm:grid-cols-[1fr_140px_180px_auto] sm:items-end"
                            >
                                <div class="grid gap-2">
                                    <Label :for="`prodi-${index}`" class="label-isian">Program Studi</Label>
                                    <SelectFilter v-model="tarif.prodi_id" label="Program studi tarif" penuh>
                                        <option :value="null">Semua Program Studi</option>
                                        <option v-for="prodi in props.prodiOptions" :key="prodi.id" :value="prodi.id">{{ prodi.name }}</option>
                                    </SelectFilter>
                                </div>
                                <div class="grid gap-2">
                                    <Label :for="`angkatan-${index}`" class="label-isian">Angkatan</Label>
                                    <Input
                                        :id="`angkatan-${index}`"
                                        v-model="tarif.angkatan"
                                        type="number"
                                        min="1900"
                                        max="2999"
                                        placeholder="Semua"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label :for="`nominal-${index}`" class="label-isian"> Nominal {{ perSks ? 'per SKS' : 'per semester' }} </Label>
                                    <Input :id="`nominal-${index}`" v-model="tarif.nominal" type="number" min="0" required />
                                    <span class="teks-bantu">{{ rupiah(Number(tarif.nominal)) }}</span>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="icon-lg"
                                    class="justify-self-end text-[#dd5b00]"
                                    title="Hapus tarif"
                                    aria-label="Hapus tarif"
                                    @click="hapusTarif(index)"
                                    ><Trash2
                                /></Button>
                            </div>

                            <p
                                v-if="!form.tarif.length"
                                class="rounded-lg border border-dashed border-[#e6e6e6] px-4 py-6 text-center text-sm text-[#615d59] dark:border-border dark:text-muted-foreground"
                            >
                                Belum ada tarif. Tanpa tarif, komponen ini tidak akan muncul di tagihan.
                            </p>
                        </div>
                    </section>

                    <div class="flex justify-end gap-2">
                        <Button as-child variant="outline"><Link :href="route('admin.jenis-biaya.index')">Batal</Link></Button>
                        <Button type="submit" :disabled="form.processing">
                            <LoaderCircle v-if="form.processing" class="animate-spin" />
                            Simpan
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
