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

const inp =
    'h-10 rounded-lg border-[#d8d5d2] bg-white text-sm text-[#31302e] shadow-sm placeholder:text-[#a39e98] focus-visible:border-[#0075de] focus-visible:ring-2 focus-visible:ring-[#0075de]/15';
</script>

<template>
    <Head :title="props.jenis ? 'Ubah Jenis Biaya' : 'Tambah Jenis Biaya'" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Jenis Biaya', href: route('admin.jenis-biaya.index') },
            { title: props.jenis ? 'Ubah' : 'Tambah', href: '#' },
        ]"
    >
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[900px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] text-black">{{ props.jenis ? 'Ubah' : 'Tambah' }} Jenis Biaya</h1>
                    <p class="text-sm text-[#615d59]">Tarif kosong berarti komponen ini tidak ditagihkan ke mahasiswa yang bersangkutan.</p>
                </div>

                <form class="flex flex-col gap-5" @submit.prevent="submit">
                    <div class="rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm">
                        <div class="grid content-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="kode">Kode</Label>
                                <Input id="kode" v-model="form.kode" :class="inp" placeholder="SPP-TETAP" required />
                                <InputError :message="form.errors.kode" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="nama">Nama</Label>
                                <Input id="nama" v-model="form.nama" :class="inp" placeholder="SPP Tetap" required />
                                <InputError :message="form.errors.nama" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="cara_hitung">Cara Hitung</Label>
                                <SelectFilter v-model="form.cara_hitung" label="Cara hitung" penuh>
                                    <option value="tetap">Tetap per semester</option>
                                    <option value="per_sks">Per SKS yang diambil</option>
                                </SelectFilter>
                                <p class="text-xs text-[#615d59]">
                                    {{
                                        perSks
                                            ? 'Nominal tarif dikali jumlah SKS di KRS semester itu saat tagihan diterbitkan.'
                                            : 'Nominal tarif ditagihkan apa adanya setiap semester.'
                                    }}
                                </p>
                                <InputError :message="form.errors.cara_hitung" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="urutan">Urutan Tampil</Label>
                                <Input id="urutan" v-model="form.urutan" type="number" min="0" max="999" :class="inp" />
                                <InputError :message="form.errors.urutan" />
                            </div>
                            <div class="grid gap-2 sm:col-span-2">
                                <Label for="keterangan">Keterangan</Label>
                                <Input id="keterangan" v-model="form.keterangan" :class="inp" placeholder="Opsional" />
                                <InputError :message="form.errors.keterangan" />
                            </div>
                        </div>

                        <Label for="aktif" class="mt-4 flex w-fit items-center gap-2.5 text-sm text-[#31302e]">
                            <Checkbox id="aktif" v-model:checked="form.aktif" />
                            <span>Aktif — ikut ditagihkan saat tagihan diterbitkan</span>
                        </Label>
                    </div>

                    <div class="rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-1">
                                <h2 class="text-lg font-semibold text-black">Tarif</h2>
                                <p class="text-sm text-[#615d59]">
                                    Kosongkan program studi atau angkatan berarti berlaku untuk semua. Tarif paling khusus yang dipakai.
                                </p>
                            </div>
                            <Button type="button" variant="outline" class="h-9 rounded-lg border-[#d8d5d2]" @click="tambahTarif">
                                <Plus class="size-4" /> Tambah Tarif
                            </Button>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div
                                v-for="(tarif, index) in form.tarif"
                                :key="index"
                                class="grid content-start gap-3 rounded-lg border border-[#e6e6e6] p-3 sm:grid-cols-[1fr_140px_180px_auto] sm:items-end"
                            >
                                <div class="grid gap-1.5">
                                    <Label :for="`prodi-${index}`" class="text-xs text-[#615d59]">Program Studi</Label>
                                    <SelectFilter v-model="tarif.prodi_id" label="Program studi tarif" penuh>
                                        <option :value="null">Semua Program Studi</option>
                                        <option v-for="prodi in props.prodiOptions" :key="prodi.id" :value="prodi.id">{{ prodi.name }}</option>
                                    </SelectFilter>
                                </div>
                                <div class="grid gap-1.5">
                                    <Label :for="`angkatan-${index}`" class="text-xs text-[#615d59]">Angkatan</Label>
                                    <Input
                                        :id="`angkatan-${index}`"
                                        v-model="tarif.angkatan"
                                        type="number"
                                        min="1900"
                                        max="2999"
                                        placeholder="Semua"
                                        :class="inp"
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label :for="`nominal-${index}`" class="text-xs text-[#615d59]">
                                        Nominal {{ perSks ? 'per SKS' : 'per semester' }}
                                    </Label>
                                    <Input :id="`nominal-${index}`" v-model="tarif.nominal" type="number" min="0" :class="inp" required />
                                    <span class="text-xs text-[#a39e98]">{{ rupiah(Number(tarif.nominal)) }}</span>
                                </div>
                                <button
                                    type="button"
                                    class="justify-self-end"
                                    title="Hapus tarif"
                                    aria-label="Hapus tarif"
                                    @click="hapusTarif(index)"
                                >
                                    <Button variant="outline" size="icon" class="size-9 rounded-full border-[#e6e6e6] bg-white text-[#dd5b00]">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </button>
                            </div>

                            <p
                                v-if="!form.tarif.length"
                                class="rounded-lg border border-dashed border-[#e6e6e6] px-4 py-6 text-center text-sm text-[#615d59]"
                            >
                                Belum ada tarif. Tanpa tarif, komponen ini tidak akan muncul di tagihan.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="h-10 rounded-lg bg-[#0075de] px-5 text-sm font-medium text-white hover:bg-[#005bab]"
                        >
                            <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                            Simpan
                        </Button>
                        <Link :href="route('admin.jenis-biaya.index')" class="text-sm font-medium text-[#615d59] hover:underline">Batal</Link>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
