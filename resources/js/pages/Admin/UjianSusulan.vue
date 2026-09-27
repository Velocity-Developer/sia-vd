<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { JENIS_UJIAN, STATUS_PENGAJUAN_SUSULAN, type JenisUjian, type StatusPengajuanSusulan } from '@/lib/ujian';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Paperclip, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Baris = {
    id: number;
    nama: string | null;
    nim: string | null;
    kelas: string | null;
    matkul: string | null;
    jenis: JenisUjian;
    tanggal_ujian: string | null;
    alasan: string;
    jumlah_lampiran: number;
    status: StatusPengajuanSusulan;
    ikut_ujian_utama: boolean;
    catatan_admin: string | null;
    diproses_oleh: string | null;
    diproses_at: string | null;
    diajukan_at: string | null;
};
type Opsi = { id: number; name: string };

const props = defineProps<{
    pengajuan: { data: Baris[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    filter: { tahun_akademik_id: number | null; status: string | null; search: string };
    jumlahMenunggu: number;
    tahunAkademikOptions: Opsi[];
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const semua = 'all';
const tahun = ref<number | string>(props.filter.tahun_akademik_id ?? '');
const status = ref<string>(props.filter.status ?? semua);
const search = ref(props.filter.search);
let jeda: number | undefined;

const kirim = () =>
    router.get(
        route('admin.ujian-susulan.index'),
        { tahun_akademik_id: tahun.value, status: status.value === semua ? null : status.value, search: search.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
watch(search, () => {
    window.clearTimeout(jeda);
    jeda = window.setTimeout(kirim, 400);
});

const setujuiItem = ref<Baris | null>(null);
const setujui = () => {
    if (!setujuiItem.value) return;
    router.post(route('admin.ujian-susulan.setujui', setujuiItem.value.id), {}, { preserveScroll: true, onFinish: () => (setujuiItem.value = null) });
};

const tolakItem = ref<Baris | null>(null);
const catatan = ref('');
const catatanError = ref('');
const bukaTolak = (baris: Baris) => {
    tolakItem.value = baris;
    catatan.value = '';
    catatanError.value = '';
};
const tolak = () => {
    if (!tolakItem.value) return;
    router.post(
        route('admin.ujian-susulan.tolak', tolakItem.value.id),
        { catatan: catatan.value },
        { preserveScroll: true, onSuccess: () => (tolakItem.value = null), onError: (e) => (catatanError.value = e.catatan ?? '') },
    );
};
</script>

<template>
    <Head title="Ujian Susulan" />
    <AppLayout :breadcrumbs="[{ title: 'Ujian Susulan', href: route('admin.ujian-susulan.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] text-black">Ujian Susulan</h1>
                    <p class="max-w-3xl text-sm text-[#615d59]">
                        Pengajuan mahasiswa yang tidak bisa mengikuti UTS/UAS. Setelah disetujui, terbitkan tagihannya di Keuangan → Tagihan Susulan;
                        jadwal susulan hanya tampil bagi yang sudah lunas.
                    </p>
                </div>

                <div v-if="page.props.flash?.success" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]">
                    {{ page.props.flash.error }}
                </div>
                <div v-if="props.jumlahMenunggu" class="rounded-xl border border-[#cfe3f8] bg-[#f2f9ff] px-4 py-3 text-sm text-[#005bab]">
                    {{ props.jumlahMenunggu }} pengajuan menunggu keputusan.
                </div>

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
                        <SelectFilter v-model="tahun" label="Filter tahun akademik" @change="kirim">
                            <option v-for="t in props.tahunAkademikOptions" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </SelectFilter>
                        <SelectFilter v-model="status" label="Filter status" @change="kirim">
                            <option :value="semua">Semua status</option>
                            <option value="menunggu">Menunggu</option>
                            <option value="disetujui">Disetujui</option>
                            <option value="ditolak">Ditolak</option>
                            <option value="dibatalkan">Dibatalkan</option>
                        </SelectFilter>
                    </div>
                    <p class="whitespace-nowrap text-sm text-[#615d59]">
                        <span class="font-medium text-black">{{ props.pengajuan.total }}</span> pengajuan
                    </p>
                </div>

                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[980px] text-left">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Mahasiswa</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Ujian</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Alasan</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="b in props.pengajuan.data" :key="b.id" class="align-top hover:bg-[#f6f5f4]/60">
                                    <td class="px-4 py-3 text-[15px] text-black">
                                        <span class="block font-medium">{{ b.nama }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ b.nim }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">
                                        <span class="block">{{ JENIS_UJIAN[b.jenis] }} {{ b.matkul }}</span>
                                        <span class="block text-xs text-[#a39e98]"
                                            >Kelas {{ b.kelas }} · {{ formatTanggal(b.tanggal_ujian, false) }}</span
                                        >
                                    </td>
                                    <td class="max-w-[320px] px-4 py-3 text-sm text-[#31302e]">
                                        <span class="block whitespace-pre-line">{{ b.alasan }}</span>
                                        <span class="mt-1 flex flex-wrap gap-3">
                                            <a
                                                v-for="i in b.jumlah_lampiran"
                                                :key="i"
                                                :href="route('berkas.lampiran-susulan', [b.id, i - 1])"
                                                target="_blank"
                                                rel="noopener"
                                                class="inline-flex items-center gap-1 text-xs font-medium text-[#0075de] hover:underline"
                                                ><Paperclip class="size-3" /> Lampiran {{ i }}</a
                                            >
                                        </span>
                                        <span class="mt-1 block text-xs text-[#a39e98]">Diajukan {{ formatTanggal(b.diajukan_at, false) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px]">
                                        <span
                                            class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="STATUS_PENGAJUAN_SUSULAN[b.status].kelas"
                                            >{{ STATUS_PENGAJUAN_SUSULAN[b.status].label }}</span
                                        >
                                        <span v-if="b.ikut_ujian_utama && b.status === 'dibatalkan'" class="mt-1 block text-xs text-[#a39e98]"
                                            >Ikut ujian utama</span
                                        >
                                        <span v-if="b.catatan_admin" class="mt-1 block max-w-[220px] text-xs text-[#a39e98]">{{
                                            b.catatan_admin
                                        }}</span>
                                        <span v-if="b.diproses_oleh" class="mt-1 block text-xs text-[#a39e98]"
                                            >{{ formatTanggal(b.diproses_at, false) }} · {{ b.diproses_oleh }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-3">
                                        <div v-if="b.status === 'menunggu'" class="flex items-center justify-end gap-2">
                                            <Button
                                                variant="outline"
                                                class="h-8 rounded-lg border-[#d8d5d2] px-3 text-sm text-[#dd5b00]"
                                                @click="bukaTolak(b)"
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
                                    <td colspan="5" class="px-4 py-14 text-center text-sm text-[#615d59]">Belum ada pengajuan ujian susulan.</td>
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
            title="Setujui pengajuan?"
            :description="
                setujuiItem
                    ? `Setujui ujian susulan ${JENIS_UJIAN[setujuiItem.jenis]} ${setujuiItem.matkul} untuk ${setujuiItem.nama}? Tagihannya diterbitkan dari menu Tagihan Susulan.`
                    : ''
            "
            confirm-text="Setujui"
            cancel-text="Batal"
            @update:open="!$event && (setujuiItem = null)"
            @confirm="setujui"
            @cancel="setujuiItem = null"
        />
        <div v-if="tolakItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="tolakItem = null">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold">Tolak pengajuan</h3>
                <p class="mt-2 text-sm text-[#615d59]">Alasan penolakan ditampilkan ke {{ tolakItem.nama }}.</p>
                <label class="mt-4 grid gap-2 text-sm">
                    <span class="font-medium">Alasan</span>
                    <Input v-model="catatan" placeholder="Mis. bukti tidak sah" class="h-10" />
                    <span v-if="catatanError" class="text-xs text-[#dd5b00]">{{ catatanError }}</span>
                </label>
                <div class="mt-6 flex justify-end gap-2">
                    <Button variant="outline" class="rounded-full" @click="tolakItem = null">Batal</Button>
                    <Button class="rounded-full bg-[#dd5b00] text-white hover:bg-[#b84b00]" @click="tolak">Tolak</Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
