<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

type Periode = {
    id: number;
    nama: string;
    tanggal_acara: string;
    tempat: string | null;
    batas_daftar: string;
    kuota: number | null;
    sisa_kuota: number | null;
    dibuka: boolean;
    jumlah_peserta: number;
    jumlah_skl: number;
};

const props = defineProps<{ periode: Periode[] }>();
const page = usePage<{ flash?: { success?: string; error?: string } }>();

const buka = ref(false);
const diedit = ref<Periode | null>(null);
const form = useForm({ nama: '', tanggal_acara: '', tempat: '', batas_daftar: '', kuota: '' as number | string });
const bukaForm = (p: Periode | null) => {
    diedit.value = p;
    form.clearErrors();
    form.nama = p?.nama ?? '';
    form.tanggal_acara = p?.tanggal_acara ?? '';
    form.tempat = p?.tempat ?? '';
    form.batas_daftar = p?.batas_daftar ?? '';
    form.kuota = p?.kuota ?? '';
    buka.value = true;
};
const simpan = () => {
    const opsi = { preserveScroll: true, onSuccess: () => (buka.value = false) };
    const kirim = form.transform((d) => ({ ...d, tempat: d.tempat || null, kuota: d.kuota === '' ? null : d.kuota }));
    if (diedit.value) kirim.put(route('admin.periode-wisuda.update', diedit.value.id), opsi);
    else kirim.post(route('admin.periode-wisuda.store'), opsi);
};

const hapusItem = ref<Periode | null>(null);
const hapus = () => {
    if (!hapusItem.value) return;
    router.delete(route('admin.periode-wisuda.destroy', hapusItem.value.id), { preserveScroll: true, onFinish: () => (hapusItem.value = null) });
};

const th = 'px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]';
const inp = 'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px]';
</script>

<template>
    <Head title="Periode Wisuda" />
    <AppLayout :breadcrumbs="[{ title: 'Periode Wisuda', href: route('admin.periode-wisuda.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] text-black">Periode Wisuda</h1>
                        <p class="max-w-3xl text-sm text-[#615d59]">
                            Mahasiswa bisa mendaftar ke periode yang belum lewat batas daftar dan kuotanya belum penuh. Buka periode untuk melihat
                            daftar mahasiswa wisuda dan menerbitkan SKL.
                        </p>
                    </div>
                    <Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]" @click="bukaForm(null)"
                        ><Plus class="size-4" /> Periode Baru</Button
                    >
                </div>

                <div v-if="page.props.flash?.success" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]">
                    {{ page.props.flash.error }}
                </div>

                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[860px] text-left">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th :class="th">Periode</th>
                                    <th :class="th">Acara</th>
                                    <th :class="th">Pendaftaran</th>
                                    <th :class="th">Peserta</th>
                                    <th :class="[th, 'text-right']">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="p in props.periode" :key="p.id" class="align-top hover:bg-[#f6f5f4]/60">
                                    <td class="px-4 py-3">
                                        <Link :href="route('admin.periode-wisuda.show', p.id)" class="font-medium text-[#0075de] hover:underline">{{
                                            p.nama
                                        }}</Link>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-[#31302e]">
                                        {{ formatTanggal(p.tanggal_acara) }}
                                        <span v-if="p.tempat" class="block text-xs text-[#a39e98]">{{ p.tempat }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="p.dibuka ? 'bg-[#eaf7ed] text-[#1aae39]' : 'bg-[#f6f5f4] text-[#615d59]'"
                                            >{{ p.dibuka ? 'Dibuka' : 'Ditutup' }}</span
                                        >
                                        <span class="block text-xs text-[#a39e98]">s.d. {{ formatTanggal(p.batas_daftar, false) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-[#31302e]">
                                        {{ p.jumlah_peserta }}{{ p.kuota ? ` / ${p.kuota}` : '' }}
                                        <span class="block text-xs text-[#a39e98]">{{ p.jumlah_skl }} SKL terbit</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-1">
                                            <Button variant="ghost" size="icon" aria-label="Ubah periode" @click="bukaForm(p)"
                                                ><Pencil class="size-4"
                                            /></Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Hapus periode"
                                                :disabled="p.jumlah_peserta > 0"
                                                @click="hapusItem = p"
                                                ><Trash2 class="size-4"
                                            /></Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.periode.length">
                                    <td colspan="5" class="px-4 py-14 text-center text-sm text-[#615d59]">Belum ada periode wisuda.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="buka" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="buka = false">
            <form class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" @submit.prevent="simpan">
                <h3 class="text-lg font-semibold">{{ diedit ? 'Ubah periode wisuda' : 'Periode wisuda baru' }}</h3>
                <div class="mt-4 grid gap-4">
                    <div class="grid gap-2">
                        <Label for="nama">Nama periode</Label>
                        <Input id="nama" v-model="form.nama" :class="inp" placeholder="Mis. Wisuda Periode I 2026" maxlength="150" required />
                        <InputError :message="form.errors.nama" />
                    </div>
                    <div class="grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid content-start gap-2">
                            <Label for="tanggal_acara">Tanggal acara</Label>
                            <Input id="tanggal_acara" v-model="form.tanggal_acara" type="date" :class="inp" required />
                            <InputError :message="form.errors.tanggal_acara" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="batas_daftar">Batas daftar</Label>
                            <Input id="batas_daftar" v-model="form.batas_daftar" type="date" :class="inp" required />
                            <InputError :message="form.errors.batas_daftar" />
                        </div>
                    </div>
                    <div class="grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid content-start gap-2">
                            <Label for="tempat">Tempat <span class="font-normal text-[#a39e98]">(opsional)</span></Label>
                            <Input id="tempat" v-model="form.tempat" :class="inp" maxlength="200" />
                            <InputError :message="form.errors.tempat" />
                        </div>
                        <div class="grid content-start gap-2">
                            <Label for="kuota">Kuota <span class="font-normal text-[#a39e98]">(kosong = tanpa batas)</span></Label>
                            <Input id="kuota" v-model="form.kuota" type="number" min="1" :class="inp" />
                            <InputError :message="form.errors.kuota" />
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <Button type="button" variant="outline" class="rounded-full" @click="buka = false">Batal</Button>
                    <Button type="submit" :disabled="form.processing" class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]">Simpan</Button>
                </div>
            </form>
        </div>

        <AlertModal
            :open="!!hapusItem"
            title="Hapus periode wisuda?"
            :description="hapusItem ? `Periode ${hapusItem.nama} akan dihapus.` : ''"
            confirm-text="Hapus"
            cancel-text="Batal"
            @update:open="!$event && (hapusItem = null)"
            @confirm="hapus"
            @cancel="hapusItem = null"
        />
    </AppLayout>
</template>
