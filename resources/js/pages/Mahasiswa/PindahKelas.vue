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

const control =
    'h-10 w-full rounded-[4px] border border-[#dddddd] bg-white px-3 text-[15px] text-black placeholder:text-[#a39e98] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de] dark:border-gray-700 dark:bg-gray-950 dark:text-white';
const area =
    'min-h-24 w-full rounded-[4px] border border-[#dddddd] bg-white px-3 py-2 text-[15px] text-black placeholder:text-[#a39e98] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de] dark:border-gray-700 dark:bg-gray-950 dark:text-white';
</script>

<template>
    <Head title="Pindah Kelas" />
    <AppLayout :breadcrumbs="[{ title: 'Pindah Kelas', href: route('mahasiswa.pindah-kelas') }]">
        <div class="min-h-full bg-[#f6f5f4] dark:bg-gray-950">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black dark:text-white">Pindah Kelas</h1>
                    <p class="text-sm leading-5 text-[#615d59] dark:text-gray-400">
                        Ajukan perpindahan kelas pada mata kuliah yang sedang Anda ambil.
                    </p>
                </div>

                <div
                    v-if="flashMessage"
                    role="alert"
                    class="rounded-xl border px-4 py-3 text-sm shadow-sm"
                    :class="
                        flashMessage.type === 'success'
                            ? 'border-[#e6e6e6] bg-white text-[#1aae39] dark:border-gray-800 dark:bg-gray-900'
                            : 'border-[#e6e6e6] bg-white text-[#dd5b00] dark:border-gray-800 dark:bg-gray-900'
                    "
                >
                    {{ flashMessage.text }}
                </div>

                <section
                    v-if="isActive"
                    class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)] dark:border-gray-800 dark:bg-gray-900"
                >
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Form Pindah Kelas</h2>
                    <form class="mt-4 space-y-4" @submit.prevent="submit">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="kelas_asal_id" class="text-sm font-medium text-black dark:text-white">Kelas Asal</Label>
                                <select id="kelas_asal_id" v-model="form.kelas_asal_id" :class="control" required>
                                    <option :value="null" disabled>Pilih kelas asal</option>
                                    <option v-for="kelas in props.kelasAsal" :key="kelas.id" :value="kelas.id">
                                        {{ kelas.kode_kelas }} — {{ kelas.mata_kuliah?.nama_matkul ?? '-' }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.kelas_asal_id" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="kelas_tujuan_id" class="text-sm font-medium text-black dark:text-white">Kelas Tujuan</Label>
                                <select id="kelas_tujuan_id" v-model="form.kelas_tujuan_id" :class="control" :disabled="!form.kelas_asal_id" required>
                                    <option :value="null" disabled>Pilih kelas tujuan</option>
                                    <option v-for="kelas in kelasTujuanTersedia" :key="kelas.id" :value="kelas.id">
                                        {{ kelas.kode_kelas }} — {{ kelas.dosen ?? 'Dosen belum ditentukan' }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.kelas_tujuan_id" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="alasan" class="text-sm font-medium text-black dark:text-white">Alasan</Label>
                            <textarea id="alasan" v-model="form.alasan" :class="area" placeholder="Tuliskan alasan pindah kelas" required />
                            <InputError :message="form.errors.alasan" />
                        </div>
                        <div class="flex justify-end">
                            <Button type="submit" :disabled="form.processing" class="rounded-full bg-[#0075de] px-8 text-white hover:bg-[#005bab]"
                                >Kirim Pengajuan</Button
                            >
                        </div>
                    </form>
                </section>

                <section v-else class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-sm text-[#615d59] dark:text-gray-400">
                        Form pindah kelas sedang ditutup. Silakan hubungi admin akademik untuk informasi lebih lanjut.
                    </p>
                </section>

                <section class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-[#e6e6e6] px-6 py-4 dark:border-gray-800">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Riwayat Pengajuan</h2>
                    </div>
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[720px] text-left">
                            <thead>
                                <tr class="border-b border-[#e6e6e6] bg-[#f6f5f4] dark:border-gray-800 dark:bg-gray-800">
                                    <th class="w-16 px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">No.</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Kelas Asal</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Kelas Tujuan</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Alasan</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Status</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Catatan Admin</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6] dark:divide-gray-800">
                                <tr
                                    v-for="(pengajuan, index) in props.pengajuans"
                                    :key="pengajuan.id"
                                    class="transition-colors hover:bg-[#f6f5f4]/60 dark:hover:bg-gray-800"
                                >
                                    <td class="px-4 py-3 text-[15px] text-[#615d59] dark:text-gray-400">{{ index + 1 }}</td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e] dark:text-gray-200">
                                        {{ pengajuan.kelas_asal ?? '-'
                                        }}<span v-if="pengajuan.kelas_asal_matkul" class="block text-xs text-[#615d59] dark:text-gray-400">{{
                                            pengajuan.kelas_asal_matkul
                                        }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e] dark:text-gray-200">
                                        {{ pengajuan.kelas_tujuan ?? '-'
                                        }}<span v-if="pengajuan.kelas_tujuan_matkul" class="block text-xs text-[#615d59] dark:text-gray-400">{{
                                            pengajuan.kelas_tujuan_matkul
                                        }}</span>
                                    </td>
                                    <td class="whitespace-pre-line px-4 py-3 text-[15px] text-[#31302e] dark:text-gray-200">
                                        {{ pengajuan.alasan }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="statusClass[pengajuan.status] ?? ''"
                                            >{{ statusLabel[pengajuan.status] ?? pengajuan.status }}</span
                                        >
                                        <span v-if="pengajuan.diproses_at" class="mt-1 block text-xs text-[#615d59] dark:text-gray-400">{{
                                            formatDateTime(pengajuan.diproses_at)
                                        }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e] dark:text-gray-200">
                                        {{ pengajuan.catatan_admin ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e] dark:text-gray-200">
                                        {{ formatDateTime(pengajuan.created_at) }}
                                    </td>
                                </tr>
                                <tr v-if="!props.pengajuans.length">
                                    <td colspan="7" class="px-4 py-16 text-center text-sm text-[#615d59] dark:text-gray-400">
                                        Belum ada pengajuan pindah kelas.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
