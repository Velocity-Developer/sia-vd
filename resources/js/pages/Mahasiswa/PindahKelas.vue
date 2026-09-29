<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

type KelasAsal = {
    id: number;
    kode_kelas: string;
    matkul_id: number;
    mata_kuliah?: { kode_matkul: string; nama_matkul: string; sks: number } | null;
    dosen?: string | null;
};

type KelasTujuan = {
    id: number;
    kode_kelas: string;
    matkul_id: number;
    mata_kuliah?: { kode_matkul: string; nama_matkul: string } | null;
    dosen?: string | null;
};

type Pengajuan = {
    id: number;
    kelas_asal: string | null;
    kelas_asal_matkul: string | null;
    kelas_tujuan: string | null;
    kelas_tujuan_matkul: string | null;
    alasan: string;
    status: string;
    catatan_admin: string | null;
    diproses_oleh: string | null;
    diproses_at: string | null;
    created_at: string | null;
};

const props = defineProps<{
    isActive: boolean;
    kelasAsal: KelasAsal[];
    kelasTujuan: KelasTujuan[];
    pengajuans: Pengajuan[];
}>();

const page = usePage<{ flash?: { pindah_kelas_success?: string; pindah_kelas_error?: string } }>();

const form = useForm({
    kelas_asal_id: null as number | null,
    kelas_tujuan_id: null as number | null,
    alasan: '',
});

const kelasTujuanTersedia = computed(() => {
    const kelas = props.kelasAsal.find((item) => item.id === Number(form.kelas_asal_id));

    if (!kelas) return [];

    return props.kelasTujuan.filter((item) => item.matkul_id === kelas.matkul_id);
});

watch(
    () => form.kelas_asal_id,
    () => {
        form.kelas_tujuan_id = null;
    },
);

const submit = () => {
    form.post(route('mahasiswa.pindah-kelas.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const statusLabel: Record<string, string> = {
    pending: 'Menunggu',
    disetujui: 'Disetujui',
    ditolak: 'Ditolak',
};

const statusClass: Record<string, string> = {
    pending: 'bg-[#fff3e0] text-[#dd5b00] dark:bg-amber-950 dark:text-amber-400',
    disetujui: 'bg-[#e6f7ea] text-[#1aae39] dark:bg-emerald-950 dark:text-emerald-400',
    ditolak: 'bg-[#fdecea] text-[#d93025] dark:bg-red-950 dark:text-red-400',
};

const formatDateTime = (value: string | null): string => {
    if (!value) return '-';

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
};

const flashMessage = ref<{ type: 'success' | 'error'; text: string } | null>(null);

const showFlash = () => {
    const success = page.props.flash?.pindah_kelas_success;
    const error = page.props.flash?.pindah_kelas_error;

    if (success) flashMessage.value = { type: 'success', text: success };
    else if (error) flashMessage.value = { type: 'error', text: error };
};

onMounted(showFlash);
watch(() => [page.props.flash?.pindah_kelas_success, page.props.flash?.pindah_kelas_error], showFlash);
</script>

<template>
    <Head title="Pindah Kelas" />
    <AppLayout :breadcrumbs="[{ title: 'Pindah Kelas', href: route('mahasiswa.pindah-kelas') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Pindah Kelas</h1>
                        <p class="deskripsi-halaman">Ajukan perpindahan kelas pada mata kuliah yang sedang Anda ambil.</p>
                    </div>
                </div>

                <div v-if="flashMessage" role="alert" :class="flashMessage.type === 'success' ? 'alert-sukses' : 'alert-gagal'">
                    {{ flashMessage.text }}
                </div>

                <section v-if="isActive" class="kartu p-6">
                    <h2 class="judul-bagian">Form Pindah Kelas</h2>
                    <form class="mt-4 space-y-4" @submit.prevent="submit">
                        <div class="grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="kelas_asal_id" class="label-isian">Kelas Asal</Label>
                                <select id="kelas_asal_id" v-model="form.kelas_asal_id" class="isian isian-pilih" required>
                                    <option :value="null" disabled>Pilih kelas asal</option>
                                    <option v-for="kelas in props.kelasAsal" :key="kelas.id" :value="kelas.id">
                                        {{ kelas.kode_kelas }} — {{ kelas.mata_kuliah?.nama_matkul ?? '-' }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.kelas_asal_id" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="kelas_tujuan_id" class="label-isian">Kelas Tujuan</Label>
                                <select
                                    id="kelas_tujuan_id"
                                    v-model="form.kelas_tujuan_id"
                                    class="isian isian-pilih"
                                    :disabled="!form.kelas_asal_id"
                                    required
                                >
                                    <option :value="null" disabled>Pilih kelas tujuan</option>
                                    <option v-for="kelas in kelasTujuanTersedia" :key="kelas.id" :value="kelas.id">
                                        {{ kelas.kode_kelas }} — {{ kelas.dosen ?? 'Dosen belum ditentukan' }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.kelas_tujuan_id" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="alasan" class="label-isian">Alasan</Label>
                            <textarea
                                id="alasan"
                                v-model="form.alasan"
                                class="isian isian-area"
                                placeholder="Tuliskan alasan pindah kelas"
                                required
                            />
                            <InputError :message="form.errors.alasan" />
                        </div>
                        <div class="flex justify-end">
                            <Button type="submit" :disabled="form.processing">Kirim Pengajuan</Button>
                        </div>
                    </form>
                </section>

                <div v-else class="alert-info">Form pindah kelas sedang ditutup. Silakan hubungi admin akademik untuk informasi lebih lanjut.</div>

                <section class="tabel-wadah">
                    <div class="border-b border-[#e6e6e6] px-6 py-4 dark:border-border">
                        <h2 class="judul-bagian">Riwayat Pengajuan</h2>
                    </div>
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[820px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Kelas Asal</th>
                                    <th>Kelas Tujuan</th>
                                    <th>Alasan</th>
                                    <th>Status</th>
                                    <th>Catatan Admin</th>
                                    <th>Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(pengajuan, index) in props.pengajuans" :key="pengajuan.id">
                                    <td class="kolom-no">{{ index + 1 }}</td>
                                    <td>
                                        {{ pengajuan.kelas_asal ?? '-'
                                        }}<span v-if="pengajuan.kelas_asal_matkul" class="teks-bantu block">{{ pengajuan.kelas_asal_matkul }}</span>
                                    </td>
                                    <td>
                                        {{ pengajuan.kelas_tujuan ?? '-'
                                        }}<span v-if="pengajuan.kelas_tujuan_matkul" class="teks-bantu block">{{
                                            pengajuan.kelas_tujuan_matkul
                                        }}</span>
                                    </td>
                                    <td class="whitespace-pre-line">{{ pengajuan.alasan }}</td>
                                    <td>
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="statusClass[pengajuan.status] ?? ''"
                                            >{{ statusLabel[pengajuan.status] ?? pengajuan.status }}</span
                                        >
                                        <span v-if="pengajuan.diproses_at" class="teks-bantu mt-1 block">{{
                                            formatDateTime(pengajuan.diproses_at)
                                        }}</span>
                                    </td>
                                    <td>{{ pengajuan.catatan_admin ?? '-' }}</td>
                                    <td>{{ formatDateTime(pengajuan.created_at) }}</td>
                                </tr>
                                <tr v-if="!props.pengajuans.length" class="baris-kosong">
                                    <td colspan="7" class="tabel-kosong">Belum ada pengajuan pindah kelas.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
