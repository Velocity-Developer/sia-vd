<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { JENIS_PENGAJUAN_CUTI, LABEL_LAMPIRAN, STATUS_PENGAJUAN, type JenisPengajuanCuti, type StatusPengajuan } from '@/lib/tugasAkhir';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Paperclip, Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type Baris = {
    id: number;
    nama: string | null;
    nim: string | null;
    prodi: string | null;
    status_mahasiswa: string | null;
    jumlah_cuti: number;
    isian: Record<string, any>;
    tahun_akademik: string | null;
    tahun_aktif: boolean;
    lampiran: string[];
    status: StatusPengajuan;
    catatan: string | null;
    diproses_oleh: string | null;
    diproses_at: string | null;
    diajukan_at: string | null;
};

const props = defineProps<{
    pengajuan: { data: Baris[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    filter: { jenis: JenisPengajuanCuti; status: string | null; search: string };
    maksCuti: number;
    jumlahMenunggu: Partial<Record<JenisPengajuanCuti, number>>;
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const semua = 'all';
const status = ref<string>(props.filter.status ?? semua);
const search = ref(props.filter.search);
let jeda: number | undefined;
const kirim = () =>
    router.get(
        route('admin.pengajuan-cuti.index'),
        { jenis: props.filter.jenis, status: status.value === semua ? null : status.value, search: search.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
watch(search, () => {
    window.clearTimeout(jeda);
    jeda = window.setTimeout(kirim, 400);
});

const cutiTab = computed(() => props.filter.jenis === 'cuti');
const terbuka = ref<number | null>(null);

const setujuiItem = ref<Baris | null>(null);
const deskripsiSetujui = computed(() => {
    const b = setujuiItem.value;
    if (!b) return '';
    if (!cutiTab.value) return `Status ${b.nama} berubah dari Cuti menjadi Aktif.`;
    return b.tahun_aktif
        ? `${b.nama} cuti pada ${b.tahun_akademik}; statusnya langsung menjadi Cuti.`
        : `${b.nama} cuti pada ${b.tahun_akademik}; statusnya menjadi Cuti saat semester itu diaktifkan.`;
});
const setujui = () => {
    if (!setujuiItem.value) return;
    router.post(
        route('admin.pengajuan-cuti.setujui', setujuiItem.value.id),
        {},
        { preserveScroll: true, onFinish: () => (setujuiItem.value = null) },
    );
};

// Perlu perbaikan dan tolak sama-sama wajib bercatatan yang ditampilkan ke mahasiswa.
const kembalikanItem = ref<{ baris: Baris; aksi: 'perbaikan' | 'tolak' } | null>(null);
const catatanForm = useForm({ catatan: '' });
const bukaKembalikan = (baris: Baris, aksi: 'perbaikan' | 'tolak') => {
    kembalikanItem.value = { baris, aksi };
    catatanForm.reset();
    catatanForm.clearErrors();
};
const kembalikan = () => {
    if (!kembalikanItem.value) return;
    const { baris, aksi } = kembalikanItem.value;
    catatanForm.post(route(`admin.pengajuan-cuti.${aksi}`, baris.id), { preserveScroll: true, onSuccess: () => (kembalikanItem.value = null) });
};
</script>

<template>
    <Head title="Pengajuan Cuti" />
    <AppLayout :breadcrumbs="[{ title: 'Pengajuan Cuti', href: route('admin.pengajuan-cuti.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] text-black">Pengajuan Cuti</h1>
                    <p class="max-w-3xl text-sm text-[#615d59]">
                        Setujui, minta perbaikan, atau tolak pengajuan cuti dan aktif kembali. Batas cuti {{ props.maksCuti }} semester selama studi;
                        periode pengajuan diatur per semester di Tahun Akademik.
                    </p>
                </div>

                <div v-if="page.props.flash?.success" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]">
                    {{ page.props.flash.error }}
                </div>

                <nav class="flex gap-1 border-b border-[#e6e6e6]" aria-label="Jenis pengajuan">
                    <Link
                        v-for="(label, j) in JENIS_PENGAJUAN_CUTI"
                        :key="j"
                        :href="route('admin.pengajuan-cuti.index', { jenis: j })"
                        class="-mb-px flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium"
                        :class="j === props.filter.jenis ? 'border-[#0075de] text-[#0075de]' : 'border-transparent text-[#615d59] hover:text-black'"
                    >
                        {{ label }}
                        <span v-if="props.jumlahMenunggu[j]" class="rounded-full bg-[#0075de] px-1.5 text-xs text-white">{{
                            props.jumlahMenunggu[j]
                        }}</span>
                    </Link>
                </nav>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="grid gap-3 sm:flex sm:flex-wrap sm:items-center">
                        <div class="relative w-full sm:w-72">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                            <Input
                                v-model="search"
                                placeholder="Cari nama atau NIM"
                                aria-label="Cari mahasiswa"
                                class="h-10 rounded-lg bg-white pl-9 text-sm"
                            />
                        </div>
                        <SelectFilter v-model="status" label="Filter status" @change="kirim">
                            <option :value="semua">Semua status</option>
                            <option v-for="s in ['menunggu', 'perlu_perbaikan', 'disetujui', 'ditolak'] as const" :key="s" :value="s">
                                {{ STATUS_PENGAJUAN[s].label }}
                            </option>
                        </SelectFilter>
                    </div>
                    <p class="whitespace-nowrap text-sm text-[#615d59]">
                        <span class="font-medium text-black">{{ props.pengajuan.total }}</span> pengajuan
                    </p>
                </div>

                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[900px] text-left">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Mahasiswa</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Pengajuan</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Status</th>
                                    <th class="w-[260px] px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="b in props.pengajuan.data" :key="b.id" class="align-top hover:bg-[#f6f5f4]/60">
                                    <td class="px-4 py-3 text-[15px] text-black">
                                        <span class="block font-medium">{{ b.nama }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ b.nim }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ b.prodi }}</span>
                                        <span class="block text-xs text-[#615d59]">Status: {{ b.status_mahasiswa }}</span>
                                        <span class="block text-xs text-[#615d59]">Cuti disetujui: {{ b.jumlah_cuti }}/{{ props.maksCuti }}</span>
                                    </td>
                                    <td class="max-w-[440px] px-4 py-3 text-sm text-[#31302e]">
                                        <template v-if="cutiTab">
                                            <span class="block text-[15px] font-medium text-black"
                                                >{{ b.tahun_akademik
                                                }}<span v-if="b.tahun_aktif" class="font-normal text-[#615d59]"> (sedang berjalan)</span></span
                                            >
                                            <button
                                                type="button"
                                                class="mt-1 text-xs font-medium text-[#0075de] hover:underline"
                                                @click="terbuka = terbuka === b.id ? null : b.id"
                                            >
                                                {{ terbuka === b.id ? 'Sembunyikan alasan' : 'Lihat alasan' }}
                                            </button>
                                            <span
                                                v-if="terbuka === b.id"
                                                class="mt-1 block whitespace-pre-line rounded-lg bg-[#f6f5f4] p-3 text-sm"
                                                >{{ b.isian.alasan }}</span
                                            >
                                        </template>
                                        <span v-else class="block whitespace-pre-line">{{ b.isian.keterangan || 'Tanpa keterangan' }}</span>
                                        <span class="mt-1 flex flex-wrap gap-3">
                                            <a
                                                v-for="k in b.lampiran"
                                                :key="k"
                                                :href="route('berkas.pengajuan-akademik', [b.id, k])"
                                                target="_blank"
                                                rel="noopener"
                                                class="inline-flex items-center gap-1 text-xs font-medium text-[#0075de] hover:underline"
                                                ><Paperclip class="size-3" /> {{ LABEL_LAMPIRAN[k] ?? k }}</a
                                            >
                                        </span>
                                        <span class="mt-1 block text-xs text-[#a39e98]">Dikirim {{ formatTanggal(b.diajukan_at, false) }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="STATUS_PENGAJUAN[b.status].kelas"
                                            >{{ STATUS_PENGAJUAN[b.status].label }}</span
                                        >
                                        <span v-if="b.catatan" class="mt-1 block max-w-[220px] text-xs text-[#615d59]">{{ b.catatan }}</span>
                                        <span v-if="b.diproses_oleh" class="mt-1 block text-xs text-[#a39e98]"
                                            >{{ formatTanggal(b.diproses_at, false) }} · {{ b.diproses_oleh }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-3">
                                        <div v-if="b.status === 'menunggu'" class="flex flex-wrap items-center justify-end gap-2">
                                            <Button
                                                variant="outline"
                                                class="h-8 rounded-lg border-[#d8d5d2] px-3 text-sm text-[#b25000]"
                                                @click="bukaKembalikan(b, 'perbaikan')"
                                                >Perbaikan</Button
                                            >
                                            <Button
                                                variant="outline"
                                                class="h-8 rounded-lg border-[#d8d5d2] px-3 text-sm text-[#b42318]"
                                                @click="bukaKembalikan(b, 'tolak')"
                                                >Tolak</Button
                                            >
                                            <Button
                                                class="h-8 rounded-lg bg-[#0075de] px-3 text-sm text-white hover:bg-[#005bab]"
                                                @click="setujuiItem = b"
                                                >Setujui</Button
                                            >
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.pengajuan.data.length">
                                    <td colspan="4" class="px-4 py-14 text-center text-sm text-[#615d59]">
                                        Belum ada pengajuan {{ JENIS_PENGAJUAN_CUTI[props.filter.jenis].toLowerCase() }}.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.pengajuan.links" :total="props.pengajuan.total" />
            </div>
        </div>

        <AlertModal
            :open="!!setujuiItem"
            :title="cutiTab ? 'Setujui pengajuan cuti?' : 'Setujui aktif kembali?'"
            :description="deskripsiSetujui"
            confirm-text="Setujui"
            cancel-text="Batal"
            @update:open="!$event && (setujuiItem = null)"
            @confirm="setujui"
            @cancel="setujuiItem = null"
        />

        <div v-if="kembalikanItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="kembalikanItem = null">
            <form class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" @submit.prevent="kembalikan">
                <h3 class="text-lg font-semibold">{{ kembalikanItem.aksi === 'perbaikan' ? 'Minta perbaikan' : 'Tolak pengajuan' }}</h3>
                <p class="mt-2 text-sm text-[#615d59]">
                    {{
                        kembalikanItem.aksi === 'perbaikan'
                            ? `${kembalikanItem.baris.nama} bisa memperbaiki isian lalu mengirim ulang.`
                            : `${kembalikanItem.baris.nama} bisa mengajukan lagi dengan form baru.`
                    }}
                    Catatan ditampilkan ke mahasiswa.
                </p>
                <label class="mt-4 grid gap-2 text-sm">
                    <span class="font-medium">Catatan</span>
                    <textarea
                        v-model="catatanForm.catatan"
                        rows="3"
                        maxlength="1000"
                        class="w-full rounded-[4px] border border-[#dddddd] px-3 py-2 text-[15px] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]"
                        :placeholder="
                            kembalikanItem.aksi === 'perbaikan' ? 'Mis. bukti bayar tidak terbaca' : 'Mis. alasan cuti tidak memenuhi ketentuan'
                        "
                        required
                    />
                    <InputError :message="catatanForm.errors.catatan" />
                </label>
                <div class="mt-6 flex justify-end gap-2">
                    <Button type="button" variant="outline" class="rounded-full" @click="kembalikanItem = null">Batal</Button>
                    <Button
                        type="submit"
                        :disabled="catatanForm.processing"
                        class="rounded-full text-white"
                        :class="kembalikanItem.aksi === 'perbaikan' ? 'bg-[#b25000] hover:bg-[#8f4000]' : 'bg-[#dd5b00] hover:bg-[#b84b00]'"
                        >{{ kembalikanItem.aksi === 'perbaikan' ? 'Minta Perbaikan' : 'Tolak' }}</Button
                    >
                </div>
            </form>
        </div>
    </AppLayout>
</template>
