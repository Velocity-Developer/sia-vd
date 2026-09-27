<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { LABEL_LAMPIRAN, type JadwalPendadaran } from '@/lib/tugasAkhir';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Paperclip } from 'lucide-vue-next';
import { ref } from 'vue';

type Bimbingan = {
    id: number;
    nama: string | null;
    nim: string | null;
    prodi: string | null;
    judul: string;
    bidang: string;
    peran: string;
    pembimbing_lain: string | null;
    status: 'berjalan' | 'selesai';
    proposal_pengajuan_id: number | null;
    disahkan_at: string | null;
};
type Pendaftaran = { id: number; nama: string | null; nim: string | null; judul: string | null; lampiran: string[]; diajukan_at: string | null };
type Jadwal = JadwalPendadaran & {
    nama: string | null;
    nim: string | null;
    judul: string | null;
    peran: string;
    pengajuan_id: number;
    lampiran: string[];
};

const props = defineProps<{ bimbingan: Bimbingan[]; menungguPersetujuan: Pendaftaran[]; jadwalPendadaran: Jadwal[] }>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();

const setujuiItem = ref<Pendaftaran | null>(null);
const setujui = () => {
    if (!setujuiItem.value) return;
    router.post(
        route('dosen.bimbingan.pendadaran.setujui', setujuiItem.value.id),
        {},
        { preserveScroll: true, onFinish: () => (setujuiItem.value = null) },
    );
};

const kembalikanItem = ref<{ baris: Pendaftaran; aksi: 'perbaikan' | 'tolak' } | null>(null);
const catatanForm = useForm({ catatan: '' });
const bukaKembalikan = (baris: Pendaftaran, aksi: 'perbaikan' | 'tolak') => {
    kembalikanItem.value = { baris, aksi };
    catatanForm.reset();
    catatanForm.clearErrors();
};
const kembalikan = () => {
    if (!kembalikanItem.value) return;
    const { baris, aksi } = kembalikanItem.value;
    catatanForm.post(route(`dosen.bimbingan.pendadaran.${aksi}`, baris.id), { preserveScroll: true, onSuccess: () => (kembalikanItem.value = null) });
};

const th = 'px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]';
</script>

<template>
    <Head title="Bimbingan & Pendadaran" />
    <AppLayout :breadcrumbs="[{ title: 'Bimbingan & Pendadaran', href: route('dosen.bimbingan.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] text-black">Bimbingan & Pendadaran</h1>
                    <p class="text-sm text-[#615d59]">Mahasiswa yang tugas akhirnya Anda bimbing, pendaftaran pendadaran, dan jadwal menguji.</p>
                </div>

                <div v-if="page.props.flash?.success" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]">
                    {{ page.props.flash.error }}
                </div>

                <section v-if="props.menungguPersetujuan.length" class="overflow-hidden rounded-xl border border-[#cfe3f8] bg-white shadow-sm">
                    <div class="border-b border-[#e6e6e6] bg-[#f2f9ff] px-4 py-3">
                        <h2 class="text-sm font-semibold text-[#005bab]">Pendaftaran pendadaran menunggu persetujuan Anda</h2>
                        <p class="text-xs text-[#615d59]">Cukup satu pembimbing yang menyetujui; setelah itu admin menjadwalkan pendadaran.</p>
                    </div>
                    <div class="divide-y divide-[#e6e6e6]">
                        <div v-for="p in props.menungguPersetujuan" :key="p.id" class="flex flex-wrap items-start justify-between gap-3 px-4 py-3">
                            <div class="min-w-0 text-sm">
                                <span class="block font-medium text-black"
                                    >{{ p.nama }} <span class="text-xs text-[#a39e98]">{{ p.nim }}</span></span
                                >
                                <span class="block text-[#31302e]">{{ p.judul }}</span>
                                <span class="mt-1 flex flex-wrap gap-3">
                                    <a
                                        v-for="k in p.lampiran"
                                        :key="k"
                                        :href="route('berkas.pengajuan-akademik', [p.id, k])"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex items-center gap-1 text-xs font-medium text-[#0075de] hover:underline"
                                        ><Paperclip class="size-3" /> {{ LABEL_LAMPIRAN[k] ?? k }}</a
                                    >
                                </span>
                                <span class="mt-1 block text-xs text-[#a39e98]">Dikirim {{ formatTanggal(p.diajukan_at, false) }}</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    class="h-8 rounded-lg border-[#d8d5d2] px-3 text-sm text-[#b25000]"
                                    @click="bukaKembalikan(p, 'perbaikan')"
                                    >Perbaikan</Button
                                >
                                <Button
                                    variant="outline"
                                    class="h-8 rounded-lg border-[#d8d5d2] px-3 text-sm text-[#b42318]"
                                    @click="bukaKembalikan(p, 'tolak')"
                                    >Tolak</Button
                                >
                                <Button class="h-8 rounded-lg bg-[#0075de] px-3 text-sm text-white hover:bg-[#005bab]" @click="setujuiItem = p"
                                    >Setujui</Button
                                >
                            </div>
                        </div>
                    </div>
                </section>

                <section class="flex flex-col gap-3">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Jadwal Pendadaran</h2>
                    <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                        <div class="relative overflow-x-auto">
                            <table class="w-full min-w-[820px] text-left">
                                <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                    <tr>
                                        <th :class="th">Waktu & Ruang</th>
                                        <th :class="th">Mahasiswa</th>
                                        <th :class="th">Penguji</th>
                                        <th :class="th">Peran Anda</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e6e6e6]">
                                    <tr v-for="j in props.jadwalPendadaran" :key="j.id" class="align-top">
                                        <td class="px-4 py-3 text-sm text-black">
                                            <span class="block font-medium">{{ formatTanggal(j.tanggal) }}</span>
                                            <span class="block">{{ j.jam_mulai }}–{{ j.jam_akhir }} · {{ j.ruang }}</span>
                                        </td>
                                        <td class="max-w-[380px] px-4 py-3 text-sm text-[#31302e]">
                                            <span class="block font-medium text-black"
                                                >{{ j.nama }} <span class="text-xs text-[#a39e98]">{{ j.nim }}</span></span
                                            >
                                            <span class="block">{{ j.judul }}</span>
                                            <a
                                                v-if="j.lampiran.includes('naskah')"
                                                :href="route('berkas.pengajuan-akademik', [j.pengajuan_id, 'naskah'])"
                                                target="_blank"
                                                rel="noopener"
                                                class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-[#0075de] hover:underline"
                                                ><Paperclip class="size-3" /> Naskah</a
                                            >
                                        </td>
                                        <td class="px-4 py-3 text-sm text-[#31302e]">
                                            <span v-for="p in j.penguji" :key="p.peran" class="block"
                                                >{{ p.nama }} <span class="text-xs text-[#a39e98]">· {{ p.peran }}</span></span
                                            >
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            <span class="rounded-full bg-[#f2f9ff] px-2 py-0.5 text-xs font-medium text-[#0075de]">{{
                                                j.peran
                                            }}</span>
                                        </td>
                                    </tr>
                                    <tr v-if="!props.jadwalPendadaran.length">
                                        <td colspan="4" class="px-4 py-10 text-center text-sm text-[#615d59]">Belum ada jadwal pendadaran.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="flex flex-col gap-3">
                    <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Mahasiswa Bimbingan</h2>
                    <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                        <div class="relative overflow-x-auto">
                            <table class="w-full min-w-[820px] text-left">
                                <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                    <tr>
                                        <th :class="th">Mahasiswa</th>
                                        <th :class="th">Tugas Akhir</th>
                                        <th :class="th">Peran</th>
                                        <th :class="th">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#e6e6e6]">
                                    <tr v-for="b in props.bimbingan" :key="b.id" class="align-top hover:bg-[#f6f5f4]/60">
                                        <td class="px-4 py-3 text-[15px] text-black">
                                            <span class="block font-medium">{{ b.nama }}</span>
                                            <span class="block text-xs text-[#a39e98]">{{ b.nim }} · {{ b.prodi }}</span>
                                        </td>
                                        <td class="max-w-[460px] px-4 py-3 text-sm text-[#31302e]">
                                            <span class="block text-[15px] font-medium text-black">{{ b.judul }}</span>
                                            <span class="block text-xs text-[#615d59]"
                                                >Bidang: {{ b.bidang }} · disahkan {{ formatTanggal(b.disahkan_at, false) }}</span
                                            >
                                            <a
                                                v-if="b.proposal_pengajuan_id"
                                                :href="route('berkas.pengajuan-akademik', [b.proposal_pengajuan_id, 'proposal'])"
                                                target="_blank"
                                                rel="noopener"
                                                class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-[#0075de] hover:underline"
                                                ><Paperclip class="size-3" /> Proposal</a
                                            >
                                        </td>
                                        <td class="px-4 py-3 text-sm text-[#31302e]">
                                            {{ b.peran }}
                                            <span v-if="b.pembimbing_lain" class="block text-xs text-[#a39e98]">bersama {{ b.pembimbing_lain }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span
                                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="b.status === 'selesai' ? 'bg-[#eaf7ed] text-[#1aae39]' : 'bg-[#f2f9ff] text-[#0075de]'"
                                                >{{ b.status === 'selesai' ? 'Selesai' : 'Berjalan' }}</span
                                            >
                                        </td>
                                    </tr>
                                    <tr v-if="!props.bimbingan.length">
                                        <td colspan="4" class="px-4 py-10 text-center text-sm text-[#615d59]">Belum ada mahasiswa bimbingan.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <AlertModal
            :open="!!setujuiItem"
            title="Setujui pendaftaran pendadaran?"
            :description="setujuiItem ? `Pendaftaran ${setujuiItem.nama} diteruskan ke admin untuk dijadwalkan beserta tiga penguji.` : ''"
            confirm-text="Setujui"
            cancel-text="Batal"
            @update:open="!$event && (setujuiItem = null)"
            @confirm="setujui"
            @cancel="setujuiItem = null"
        />
        <div v-if="kembalikanItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="kembalikanItem = null">
            <form class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" @submit.prevent="kembalikan">
                <h3 class="text-lg font-semibold">{{ kembalikanItem.aksi === 'perbaikan' ? 'Minta perbaikan' : 'Tolak pendaftaran' }}</h3>
                <p class="mt-2 text-sm text-[#615d59]">Catatan ditampilkan ke {{ kembalikanItem.baris.nama }}.</p>
                <label class="mt-4 grid gap-2 text-sm">
                    <span class="font-medium">Catatan</span>
                    <textarea
                        v-model="catatanForm.catatan"
                        rows="3"
                        maxlength="1000"
                        class="w-full rounded-[4px] border border-[#dddddd] px-3 py-2 text-[15px] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]"
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
