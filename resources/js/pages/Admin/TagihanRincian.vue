<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';

type Item = { nama: string; cara_hitung: string; nominal_satuan: number; jumlah: number; subtotal: number };
type Tagihan = { id: number; status: string; total: number; tanggal_lunas: string | null; items: Item[] };

const props = defineProps<{
    mahasiswa: { id: number; nama: string | null; nim: string | null; prodi: string | null; angkatan: number | null };
    tahunAkademik: string | null;
    tahunAkademikId: number | null;
    tagihan: Tagihan | null;
    sks: number;
    kuota: number;
    krsTersimpan: boolean;
}>();

/** Rincian diketik sebagai teks karena isian number mengembalikan string. */
const form = useForm({
    tahun_akademik_id: props.tahunAkademikId,
    items: (props.tagihan?.items ?? []).map((item) => ({ nama: item.nama, subtotal: String(item.subtotal) })),
});

const totalBaru = computed(() => form.items.reduce((jumlah, item) => jumlah + Number(item.subtotal || 0), 0));

// Galat baris bernama "items.0.nama"; dibaca lewat peta agar tidak perlu indeks bertipe longgar.
const galat = (index: number, kolom: 'nama' | 'subtotal'): string | undefined =>
    (form.errors as Record<string, string | undefined>)[`items.${index}.${kolom}`];

const tambahBaris = () => form.items.push({ nama: '', subtotal: '0' });
const hapusBaris = (index: number) => form.items.splice(index, 1);

const simpan = () => {
    form.transform((data) => ({
        ...data,
        items: data.items.map((item) => ({ nama: item.nama, subtotal: Number(item.subtotal || 0) })),
    })).put(route('admin.tagihan.rincian.simpan', props.mahasiswa.id), { preserveScroll: true });
};

const inp =
    'h-10 rounded-lg border-[#d8d5d2] bg-white text-sm text-[#31302e] shadow-sm placeholder:text-[#a39e98] focus-visible:border-[#0075de] focus-visible:ring-2 focus-visible:ring-[#0075de]/15';

const rupiah = (nilai: number) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(nilai || 0);
</script>

<template>
    <Head title="Rincian Tagihan" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Tagihan Mahasiswa', href: route('admin.tagihan.index') },
            { title: 'Rincian', href: '#' },
        ]"
    >
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[900px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] text-black">{{ props.mahasiswa.nama }}</h1>
                    <p class="text-sm text-[#615d59]">
                        {{ props.mahasiswa.nim }} · {{ props.mahasiswa.prodi ?? '-' }} · Angkatan {{ props.mahasiswa.angkatan }} ·
                        {{ props.tahunAkademik ?? 'Tahun akademik tidak dipilih' }}
                    </p>
                </div>

                <div class="rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Status</p>
                            <span
                                class="mt-1 inline-block rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="props.tagihan?.status === 'lunas' ? 'bg-[#eaf7ed] text-[#1aae39]' : 'bg-[#fdf1e9] text-[#dd5b00]'"
                            >
                                {{ props.tagihan?.status === 'lunas' ? 'Lunas' : 'Belum Bayar' }}
                            </span>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Total Tagihan</p>
                            <p class="text-[22px] font-bold text-black">{{ rupiah(props.tagihan?.total ?? 0) }}</p>
                        </div>
                    </div>

                    <p class="mt-3 text-sm text-[#615d59]">
                        Kuota SKS (dasar biaya per SKS): <span class="font-medium text-black">{{ props.kuota }} SKS</span> · SKS yang sudah diambil:
                        {{ props.sks }} SKS · KRS {{ props.krsTersimpan ? 'sudah disimpan mahasiswa (terkunci)' : 'belum disimpan mahasiswa' }}.
                    </p>
                </div>

                <form class="rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-sm" @submit.prevent="simpan">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <h2 class="text-lg font-semibold text-black">Rincian Tagihan</h2>
                            <p class="text-sm text-[#615d59]">Boleh diketik manual, mis. keringanan atau biaya tambahan. Total ikut menyesuaikan.</p>
                        </div>
                        <Button type="button" variant="outline" class="h-9 rounded-lg border-[#d8d5d2]" @click="tambahBaris">
                            <Plus class="size-4" /> Tambah Baris
                        </Button>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div
                            v-for="(item, index) in form.items"
                            :key="index"
                            class="grid content-start gap-3 rounded-lg border border-[#e6e6e6] p-3 sm:grid-cols-[1fr_200px_auto] sm:items-end"
                        >
                            <div class="grid gap-1.5">
                                <Label :for="`nama-${index}`" class="text-xs text-[#615d59]">Komponen</Label>
                                <Input :id="`nama-${index}`" v-model="item.nama" :class="inp" placeholder="SPP Tetap" required />
                                <InputError :message="galat(index, 'nama')" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label :for="`subtotal-${index}`" class="text-xs text-[#615d59]">Nominal</Label>
                                <Input :id="`subtotal-${index}`" v-model="item.subtotal" type="number" min="0" :class="inp" required />
                                <span class="text-xs text-[#a39e98]">{{ rupiah(Number(item.subtotal)) }}</span>
                                <InputError :message="galat(index, 'subtotal')" />
                            </div>
                            <button type="button" class="justify-self-end" title="Hapus baris" aria-label="Hapus baris" @click="hapusBaris(index)">
                                <Button variant="outline" size="icon" class="size-9 rounded-full border-[#e6e6e6] bg-white text-[#dd5b00]">
                                    <Trash2 class="size-4" />
                                </Button>
                            </button>
                        </div>

                        <p
                            v-if="!form.items.length"
                            class="rounded-lg border border-dashed border-[#e6e6e6] px-4 py-6 text-center text-sm text-[#615d59]"
                        >
                            Belum ada rincian. Tambahkan baris, atau terbitkan tagihan massal dari halaman daftar.
                        </p>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-[#e6e6e6] pt-4">
                        <p class="text-sm text-[#615d59]">
                            Total baru: <span class="text-[17px] font-bold text-black">{{ rupiah(totalBaru) }}</span>
                        </p>
                        <Button
                            type="submit"
                            :disabled="form.processing || !props.tahunAkademikId"
                            class="h-10 rounded-lg bg-[#0075de] px-5 text-sm font-medium text-white hover:bg-[#005bab]"
                        >
                            <LoaderCircle v-if="form.processing" class="size-4 animate-spin" />
                            Simpan Rincian
                        </Button>
                    </div>
                </form>

                <Link :href="route('admin.tagihan.index')" class="text-sm font-medium text-[#0075de] hover:underline"
                    >← Kembali ke daftar tagihan</Link
                >
            </div>
        </div>
    </AppLayout>
</template>
