<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import ModalJadwalPendadaran from '@/components/tugas-akhir/ModalJadwalPendadaran.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import {
    JENIS_PENGAJUAN,
    LABEL_LAMPIRAN,
    STATUS_PENGAJUAN,
    type JadwalPendadaran,
    type JenisPengajuan,
    type StatusPengajuan,
} from '@/lib/tugasAkhir';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Paperclip, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Baris = {
    id: number;
    nama: string | null;
    nim: string | null;
    prodi: string | null;
    isian: Record<string, any>;
    usulan_pembimbing: string[];
    lampiran: string[];
    pembimbing: string[];
    disetujui_pembimbing: string | null;
    disetujui_pembimbing_at: string | null;
    jadwal: JadwalPendadaran | null;
    status: StatusPengajuan;
    catatan: string | null;
    diproses_oleh: string | null;
    diproses_at: string | null;
    diajukan_at: string | null;
};

const props = defineProps<{
    pengajuan: { data: Baris[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    filter: { jenis: JenisPengajuan; status: string | null; search: string };
    jenisTersedia: JenisPengajuan[];
    jumlahMenunggu: Partial<Record<JenisPengajuan, number>>;
    dosenOptions: { id: number; name: string }[];
    ruangOptions: { id: number; name: string }[];
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const semua = 'all';
const status = ref<string>(props.filter.status ?? semua);
const search = ref(props.filter.search);
let jeda: number | undefined;

const kirim = () =>
    router.get(
        route('admin.pengajuan-akademik.index'),
        { jenis: props.filter.jenis, status: status.value === semua ? null : status.value, search: search.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
watch(search, () => {
    window.clearTimeout(jeda);
    jeda = window.setTimeout(kirim, 400);
});

const terbuka = ref<number | null>(null);

// Setujui TA: admin mengesahkan judul dan menetapkan pembimbing (awalnya diisi dari usulan mahasiswa).
const setujuiItem = ref<Baris | null>(null);
const setujuiForm = useForm({ judul: '', pembimbing_1_id: null as number | null, pembimbing_2_id: null as number | null });
// Pendadaran disetujui sekaligus dijadwalkan lewat modal tersendiri.
const jadwalkanItem = ref<Baris | null>(null);
const bukaSetujui = (b: Baris) => {
    if (props.filter.jenis === 'pendadaran') {
        jadwalkanItem.value = b;
        return;
    }
    setujuiItem.value = b;
    setujuiForm.clearErrors();
    setujuiForm.judul = b.isian.judul ?? '';
    setujuiForm.pembimbing_1_id = b.isian.usulan_pembimbing_1_id ?? null;
    setujuiForm.pembimbing_2_id = b.isian.usulan_pembimbing_2_id ?? null;
};
const setujui = () => {
    if (!setujuiItem.value) return;
    setujuiForm.post(route('admin.pengajuan-akademik.setujui', setujuiItem.value.id), {
        preserveScroll: true,
        onSuccess: () => (setujuiItem.value = null),
    });
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
    catatanForm.post(route(`admin.pengajuan-akademik.${aksi}`, baris.id), { preserveScroll: true, onSuccess: () => (kembalikanItem.value = null) });
};
</script>

<template>
    <Head title="Pengajuan TA & Wisuda" />
    <AppLayout :breadcrumbs="[{ title: 'Pengajuan TA & Wisuda', href: route('admin.pengajuan-akademik.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] text-black">Pengajuan TA & Wisuda</h1>
                    <p class="max-w-3xl text-sm text-[#615d59]">
                        Setujui, minta perbaikan, atau tolak pengajuan mahasiswa. Selama menunggu keputusan, mahasiswa tidak bisa mengirim pengajuan
                        baru.
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
                        v-for="j in props.jenisTersedia"
                        :key="j"
                        :href="route('admin.pengajuan-akademik.index', { jenis: j })"
                        class="-mb-px flex items-center gap-2 border-b-2 px-4 py-2 text-sm font-medium"
                        :class="j === props.filter.jenis ? 'border-[#0075de] text-[#0075de]' : 'border-transparent text-[#615d59] hover:text-black'"
                    >
                        {{ JENIS_PENGAJUAN[j] }}
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
                            <option v-for="(s, kunci) in STATUS_PENGAJUAN" :key="kunci" :value="kunci">{{ s.label }}</option>
                        </SelectFilter>
                    </div>
                    <p class="whitespace-nowrap text-sm text-[#615d59]">
                        <span class="font-medium text-black">{{ props.pengajuan.total }}</span> pengajuan
                    </p>
                </div>

                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[960px] text-left">
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
                                    </td>
                                    <td class="max-w-[440px] px-4 py-3 text-sm text-[#31302e]">
                                        <span class="block text-[15px] font-medium text-black">{{ b.isian.judul }}</span>
                                        <span v-if="b.isian.bidang" class="block text-xs text-[#615d59]">Bidang: {{ b.isian.bidang }}</span>
                                        <span v-if="b.usulan_pembimbing.length" class="block text-xs text-[#615d59]"
                                            >Usulan pembimbing: {{ b.usulan_pembimbing.join(', ') }}</span
                                        >
                                        <span v-if="b.pembimbing.length" class="block text-xs text-[#615d59]"
                                            >Pembimbing: {{ b.pembimbing.join(', ') }}</span
                                        >
                                        <span v-if="b.disetujui_pembimbing" class="block text-xs text-[#1aae39]"
                                            >Disetujui {{ b.disetujui_pembimbing }} · {{ formatTanggal(b.disetujui_pembimbing_at, false) }}</span
                                        >
                                        <button
                                            v-if="b.isian.ringkasan"
                                            type="button"
                                            class="mt-1 text-xs font-medium text-[#0075de] hover:underline"
                                            @click="terbuka = terbuka === b.id ? null : b.id"
                                        >
                                            {{ terbuka === b.id ? 'Sembunyikan ringkasan' : 'Lihat ringkasan' }}
                                        </button>
                                        <span v-if="terbuka === b.id" class="mt-1 block whitespace-pre-line rounded-lg bg-[#f6f5f4] p-3 text-sm">{{
                                            b.isian.ringkasan
                                        }}</span>
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
                                        <span v-if="b.jadwal" class="mt-1 block text-xs text-[#31302e]"
                                            >Pendadaran {{ formatTanggal(b.jadwal.tanggal, false) }}, {{ b.jadwal.jam_mulai }}–{{
                                                b.jadwal.jam_akhir
                                            }}
                                            · {{ b.jadwal.ruang }}</span
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
                                                @click="bukaSetujui(b)"
                                                >Setujui</Button
                                            >
                                        </div>
                                        <p v-else-if="b.status === 'menunggu_pembimbing'" class="text-right text-xs text-[#a39e98]">
                                            Menunggu persetujuan pembimbing
                                        </p>
                                    </td>
                                </tr>
                                <tr v-if="!props.pengajuan.data.length">
                                    <td colspan="4" class="px-4 py-14 text-center text-sm text-[#615d59]">
                                        Belum ada pengajuan {{ JENIS_PENGAJUAN[props.filter.jenis].toLowerCase() }}.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.pengajuan.links" :total="props.pengajuan.total" />
            </div>
        </div>

        <ModalJadwalPendadaran
            v-if="jadwalkanItem"
            :pengajuan="{ id: jadwalkanItem.id, nama: jadwalkanItem.nama, judul: jadwalkanItem.isian.judul, pembimbing: jadwalkanItem.pembimbing }"
            :dosen-options="props.dosenOptions"
            :ruang-options="props.ruangOptions"
            @tutup="jadwalkanItem = null"
        />
        <div v-if="setujuiItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="setujuiItem = null">
            <form class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" @submit.prevent="setujui">
                <h3 class="text-lg font-semibold">Setujui tugas akhir</h3>
                <p class="mt-1 text-sm text-[#615d59]">Sahkan judul dan tetapkan pembimbing {{ setujuiItem.nama }}. Boleh berbeda dari usulan.</p>
                <div class="mt-4 grid gap-4">
                    <div class="grid gap-2">
                        <Label for="setujui_judul">Judul disahkan</Label>
                        <textarea
                            id="setujui_judul"
                            v-model="setujuiForm.judul"
                            maxlength="300"
                            rows="3"
                            class="w-full rounded-[4px] border border-[#dddddd] px-3 py-2 text-[15px] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]"
                            required
                        />
                        <InputError :message="setujuiForm.errors.judul" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="pembimbing_1_id">Pembimbing 1</Label>
                        <SearchSelect
                            id="pembimbing_1_id"
                            v-model="setujuiForm.pembimbing_1_id"
                            :options="props.dosenOptions"
                            placeholder="Pilih dosen"
                            search-placeholder="Cari dosen"
                            required
                        />
                        <InputError :message="setujuiForm.errors.pembimbing_1_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="pembimbing_2_id" class="flex items-center justify-between">
                            <span>Pembimbing 2 <span class="font-normal text-[#a39e98]">(opsional)</span></span>
                            <button
                                v-if="setujuiForm.pembimbing_2_id"
                                type="button"
                                class="text-xs font-normal text-[#0075de] hover:underline"
                                @click="setujuiForm.pembimbing_2_id = null"
                            >
                                Kosongkan
                            </button>
                        </Label>
                        <SearchSelect
                            id="pembimbing_2_id"
                            v-model="setujuiForm.pembimbing_2_id"
                            :options="props.dosenOptions"
                            placeholder="Tanpa pembimbing 2"
                            search-placeholder="Cari dosen"
                        />
                        <InputError :message="setujuiForm.errors.pembimbing_2_id" />
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <Button type="button" variant="outline" class="rounded-full" @click="setujuiItem = null">Batal</Button>
                    <Button type="submit" :disabled="setujuiForm.processing" class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]"
                        >Setujui</Button
                    >
                </div>
            </form>
        </div>

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
                        :placeholder="kembalikanItem.aksi === 'perbaikan' ? 'Mis. perjelas rumusan masalah' : 'Mis. topik di luar bidang prodi'"
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
