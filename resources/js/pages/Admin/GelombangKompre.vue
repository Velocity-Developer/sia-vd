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

type Gelombang = {
    id: number;
    nama: string;
    tanggal_buka: string;
    tanggal_tutup: string;
    tanggal_ujian: string;
    kuota: number | null;
    sisa_kuota: number | null;
    keterangan: string | null;
    dibuka: boolean;
    jumlah_pendaftar: number;
    jumlah_disetujui: number;
};

const props = defineProps<{ gelombang: Gelombang[] }>();
const page = usePage<{ flash?: { success?: string; error?: string } }>();

const buka = ref(false);
const diedit = ref<Gelombang | null>(null);
const form = useForm({ nama: '', tanggal_buka: '', tanggal_tutup: '', tanggal_ujian: '', kuota: '' as number | string, keterangan: '' });
const bukaForm = (g: Gelombang | null) => {
    diedit.value = g;
    form.clearErrors();
    form.nama = g?.nama ?? '';
    form.tanggal_buka = g?.tanggal_buka ?? '';
    form.tanggal_tutup = g?.tanggal_tutup ?? '';
    form.tanggal_ujian = g?.tanggal_ujian ?? '';
    form.kuota = g?.kuota ?? '';
    form.keterangan = g?.keterangan ?? '';
    buka.value = true;
};
const simpan = () => {
    const opsi = { preserveScroll: true, onSuccess: () => (buka.value = false) };
    const kirim = form.transform((d) => ({ ...d, keterangan: d.keterangan || null, kuota: d.kuota === '' ? null : d.kuota }));
    if (diedit.value) kirim.put(route('admin.gelombang-kompre.update', diedit.value.id), opsi);
    else kirim.post(route('admin.gelombang-kompre.store'), opsi);
};

const hapusItem = ref<Gelombang | null>(null);
const hapus = () => {
    if (!hapusItem.value) return;
    router.delete(route('admin.gelombang-kompre.destroy', hapusItem.value.id), { preserveScroll: true, onFinish: () => (hapusItem.value = null) });
};
</script>

<template>
    <Head title="Gelombang Ujian Komprehensif" />
    <AppLayout :breadcrumbs="[{ title: 'Gelombang Ujian Komprehensif', href: route('admin.gelombang-kompre.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Gelombang Ujian Komprehensif</h1>
                        <p class="deskripsi-halaman">
                            Mahasiswa memilih gelombang yang sedang dibuka saat mengajukan ujian komprehensif. Pendaftarnya diproses di
                            <Link :href="route('admin.pengajuan-kompre.index')" class="text-[#0075de] hover:underline">Daftar Ujian Komprehensif</Link
                            >; ujiannya berlangsung di luar sistem.
                        </p>
                    </div>
                    <Button @click="bukaForm(null)"><Plus /> Gelombang Baru</Button>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[900px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Gelombang</th>
                                    <th>Pendaftaran</th>
                                    <th>Tanggal ujian</th>
                                    <th>Pendaftar</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(g, index) in props.gelombang" :key="g.id">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td>
                                        <span class="block font-medium text-black">{{ g.nama }}</span>
                                        <span v-if="g.keterangan" class="block text-xs text-[#a39e98]">{{ g.keterangan }}</span>
                                    </td>
                                    <td>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="g.dibuka ? 'bg-[#eaf7ed] text-[#1aae39]' : 'bg-[#f6f5f4] text-[#615d59]'"
                                            >{{ g.dibuka ? 'Dibuka' : 'Ditutup' }}</span
                                        >
                                        <span class="block text-xs text-[#a39e98]"
                                            >{{ formatTanggal(g.tanggal_buka, false) }} – {{ formatTanggal(g.tanggal_tutup, false) }}</span
                                        >
                                    </td>
                                    <td>{{ formatTanggal(g.tanggal_ujian) }}</td>
                                    <td class="tabular-nums">
                                        {{ g.jumlah_pendaftar }}{{ g.kuota ? ` / ${g.kuota}` : '' }}
                                        <span class="block text-xs text-[#a39e98]">{{ g.jumlah_disetujui }} disetujui</span>
                                    </td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button
                                                variant="outline"
                                                size="icon-sm"
                                                class="text-[#2a9d99]"
                                                title="Edit"
                                                aria-label="Ubah gelombang"
                                                @click="bukaForm(g)"
                                                ><Pencil
                                            /></Button>
                                            <Button
                                                variant="outline"
                                                size="icon-sm"
                                                class="text-[#dd5b00]"
                                                title="Hapus"
                                                aria-label="Hapus gelombang"
                                                :disabled="g.jumlah_pendaftar > 0"
                                                @click="hapusItem = g"
                                                ><Trash2
                                            /></Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.gelombang.length" class="baris-kosong">
                                    <td colspan="6" class="tabel-kosong">Belum ada gelombang ujian komprehensif.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="buka" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="buka = false">
            <form class="kartu w-full max-w-lg p-6 shadow-xl" @submit.prevent="simpan">
                <h3 class="judul-bagian">{{ diedit ? 'Ubah gelombang' : 'Gelombang baru' }}</h3>
                <div class="mt-4 grid gap-4">
                    <div class="grid gap-2">
                        <Label for="nama" class="label-isian">Nama gelombang</Label>
                        <Input id="nama" v-model="form.nama" placeholder="Mis. Gelombang I 2026/2027" maxlength="150" required />
                        <InputError :message="form.errors.nama" />
                    </div>
                    <div class="grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="tanggal_buka" class="label-isian">Pendaftaran dibuka</Label>
                            <Input id="tanggal_buka" v-model="form.tanggal_buka" type="date" required />
                            <InputError :message="form.errors.tanggal_buka" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="tanggal_tutup" class="label-isian">Pendaftaran ditutup</Label>
                            <Input id="tanggal_tutup" v-model="form.tanggal_tutup" type="date" required />
                            <InputError :message="form.errors.tanggal_tutup" />
                        </div>
                    </div>
                    <div class="grid items-start gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="tanggal_ujian" class="label-isian">Tanggal ujian</Label>
                            <Input id="tanggal_ujian" v-model="form.tanggal_ujian" type="date" required />
                            <InputError :message="form.errors.tanggal_ujian" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="kuota" class="label-isian"
                                >Kuota <span class="font-normal text-[#a39e98]">(kosong = tanpa batas)</span></Label
                            >
                            <Input id="kuota" v-model="form.kuota" type="number" min="1" />
                            <InputError :message="form.errors.kuota" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="keterangan" class="label-isian">Keterangan <span class="font-normal text-[#a39e98]">(opsional)</span></Label>
                        <textarea id="keterangan" v-model="form.keterangan" rows="2" maxlength="500" class="isian isian-area" />
                        <InputError :message="form.errors.keterangan" />
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
            title="Hapus gelombang?"
            :description="hapusItem ? `Gelombang ${hapusItem.nama} akan dihapus.` : ''"
            confirm-text="Hapus"
            cancel-text="Batal"
            @update:open="!$event && (hapusItem = null)"
            @confirm="hapus"
            @cancel="hapusItem = null"
        />
    </AppLayout>
</template>
