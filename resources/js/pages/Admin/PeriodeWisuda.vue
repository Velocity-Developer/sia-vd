<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-vue-next';
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
</script>

<template>
    <Head title="Periode Wisuda" />
    <AppLayout :breadcrumbs="[{ title: 'Periode Wisuda', href: route('admin.periode-wisuda.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Periode Wisuda</h1>
                        <p class="deskripsi-halaman">
                            Mahasiswa bisa mendaftar ke periode yang belum lewat batas daftar dan kuotanya belum penuh. Buka periode untuk melihat
                            daftar mahasiswa wisuda dan menerbitkan SKL.
                        </p>
                    </div>
                    <Button @click="bukaForm(null)"><Plus /> Periode Baru</Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[900px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Periode</th>
                                    <th>Acara</th>
                                    <th>Pendaftaran</th>
                                    <th>Peserta</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(p, index) in props.periode" :key="p.id">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td>
                                        <Link :href="route('admin.periode-wisuda.show', p.id)" class="font-medium text-[#0075de] hover:underline">{{
                                            p.nama
                                        }}</Link>
                                    </td>
                                    <td>
                                        {{ formatTanggal(p.tanggal_acara) }}
                                        <span v-if="p.tempat" class="block text-xs text-[#a39e98]">{{ p.tempat }}</span>
                                    </td>
                                    <td>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="p.dibuka ? 'bg-[#eaf7ed] text-[#1aae39]' : 'bg-[#f6f5f4] text-[#615d59]'"
                                            >{{ p.dibuka ? 'Dibuka' : 'Ditutup' }}</span
                                        >
                                        <span class="block text-xs text-[#a39e98]">s.d. {{ formatTanggal(p.batas_daftar, false) }}</span>
                                    </td>
                                    <td class="tabular-nums">
                                        {{ p.jumlah_peserta }}{{ p.kuota ? ` / ${p.kuota}` : '' }}
                                        <span class="block text-xs text-[#a39e98]">{{ p.jumlah_skl }} SKL terbit</span>
                                    </td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#0075de]">
                                                <Link :href="route('admin.periode-wisuda.show', p.id)" title="Detail" aria-label="Detail"
                                                    ><Eye
                                                /></Link>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="icon-sm"
                                                class="text-[#2a9d99]"
                                                title="Edit"
                                                aria-label="Ubah periode"
                                                @click="bukaForm(p)"
                                                ><Pencil
                                            /></Button>
                                            <Button
                                                variant="outline"
                                                size="icon-sm"
                                                class="text-[#dd5b00]"
                                                title="Hapus"
                                                aria-label="Hapus periode"
                                                :disabled="p.jumlah_peserta > 0"
                                                @click="hapusItem = p"
                                                ><Trash2
                                            /></Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.periode.length" class="baris-kosong">
                                    <td colspan="6" class="tabel-kosong">Belum ada periode wisuda.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="buka" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="buka = false">
            <form class="kartu w-full max-w-lg p-6 shadow-xl" @submit.prevent="simpan">
                <h3 class="judul-bagian">{{ diedit ? 'Ubah periode wisuda' : 'Periode wisuda baru' }}</h3>
                <div class="mt-4 grid gap-4">
                    <div class="grid gap-2">
                        <Label for="nama" class="label-isian">Nama periode</Label>
                        <Input id="nama" v-model="form.nama" placeholder="Mis. Wisuda Periode I 2026" maxlength="150" required />
                        <InputError :message="form.errors.nama" />
                    </div>
                    <div class="grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="tanggal_acara" class="label-isian">Tanggal acara</Label>
                            <Input id="tanggal_acara" v-model="form.tanggal_acara" type="date" required />
                            <InputError :message="form.errors.tanggal_acara" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="batas_daftar" class="label-isian">Batas daftar</Label>
                            <Input id="batas_daftar" v-model="form.batas_daftar" type="date" required />
                            <InputError :message="form.errors.batas_daftar" />
                        </div>
                    </div>
                    <div class="grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="tempat" class="label-isian">Tempat <span class="font-normal text-[#a39e98]">(opsional)</span></Label>
                            <Input id="tempat" v-model="form.tempat" maxlength="200" />
                            <InputError :message="form.errors.tempat" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="kuota" class="label-isian"
                                >Kuota <span class="font-normal text-[#a39e98]">(kosong = tanpa batas)</span></Label
                            >
                            <Input id="kuota" v-model="form.kuota" type="number" min="1" />
                            <InputError :message="form.errors.kuota" />
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="buka = false">Batal</Button>
                    <Button type="submit" :disabled="form.processing">Simpan</Button>
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
