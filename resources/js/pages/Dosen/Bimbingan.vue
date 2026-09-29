<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import InputError from '@/components/InputError.vue';
import PenilaianPendadaran, { type JadwalDosen } from '@/components/tugas-akhir/PenilaianPendadaran.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { LABEL_LAMPIRAN } from '@/lib/tugasAkhir';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { FileText, Paperclip } from 'lucide-vue-next';
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
type Jadwal = JadwalDosen & { nim: string | null; judul: string | null; pengajuan_id: number; lampiran: string[] };

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
</script>

<template>
    <Head title="Bimbingan & Pendadaran" />
    <AppLayout :breadcrumbs="[{ title: 'Bimbingan & Pendadaran', href: route('dosen.bimbingan.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Bimbingan & Pendadaran</h1>
                        <p class="deskripsi-halaman">Mahasiswa yang tugas akhirnya Anda bimbing, pendaftaran pendadaran, dan jadwal menguji.</p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <section v-if="props.menungguPersetujuan.length" class="kartu overflow-hidden">
                    <div class="border-b border-[#e6e6e6] px-6 py-4 dark:border-border">
                        <h2 class="judul-bagian">Pendaftaran pendadaran menunggu persetujuan Anda</h2>
                        <p class="teks-bantu">Cukup satu pembimbing yang menyetujui; setelah itu admin menjadwalkan pendadaran.</p>
                    </div>
                    <div class="divide-y divide-[#e6e6e6] dark:divide-border">
                        <div v-for="p in props.menungguPersetujuan" :key="p.id" class="flex flex-wrap items-start justify-between gap-3 px-6 py-4">
                            <div class="min-w-0 text-sm">
                                <span class="block font-medium text-black dark:text-foreground"
                                    >{{ p.nama }} <span class="teks-bantu">{{ p.nim }}</span></span
                                >
                                <span class="block text-[#31302e] dark:text-foreground">{{ p.judul }}</span>
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
                                <span class="teks-bantu mt-1 block">Dikirim {{ formatTanggal(p.diajukan_at, false) }}</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button variant="outline" size="sm" @click="bukaKembalikan(p, 'perbaikan')">Perbaikan</Button>
                                <Button variant="destructive" size="sm" @click="bukaKembalikan(p, 'tolak')">Tolak</Button>
                                <Button size="sm" @click="setujuiItem = p">Setujui</Button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="flex flex-col gap-3">
                    <h2 class="judul-bagian">Jadwal Pendadaran</h2>
                    <div class="tabel-wadah">
                        <div class="tabel-gulir">
                            <table class="tabel min-w-[880px]">
                                <thead>
                                    <tr>
                                        <th class="kolom-no">No</th>
                                        <th>Waktu & Ruang</th>
                                        <th>Mahasiswa</th>
                                        <th>Penguji</th>
                                        <th>Peran & Penilaian</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(j, index) in props.jadwalPendadaran" :key="j.id" class="[&>td]:align-top">
                                        <td class="kolom-no">{{ index + 1 }}</td>
                                        <td class="text-black dark:text-foreground">
                                            <span class="block font-medium">{{ formatTanggal(j.tanggal) }}</span>
                                            <span class="block">{{ j.jam_mulai }}–{{ j.jam_akhir }} · {{ j.ruang }}</span>
                                            <a
                                                v-if="j.nomor_surat"
                                                :href="route('berkas.surat-pendadaran', j.id)"
                                                target="_blank"
                                                rel="noopener"
                                                class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-[#0075de] hover:underline"
                                                ><FileText class="size-3" /> Surat</a
                                            >
                                        </td>
                                        <td class="max-w-[380px]">
                                            <span class="block font-medium text-black dark:text-foreground"
                                                >{{ j.nama }} <span class="teks-bantu font-normal">{{ j.nim }}</span></span
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
                                        <td>
                                            <span v-for="p in j.penguji" :key="p.peran" class="block"
                                                >{{ p.nama }} <span class="teks-bantu">· {{ p.peran }}</span></span
                                            >
                                        </td>
                                        <td>
                                            <PenilaianPendadaran :jadwal="j" />
                                        </td>
                                    </tr>
                                    <tr v-if="!props.jadwalPendadaran.length" class="baris-kosong">
                                        <td colspan="5" class="tabel-kosong">Belum ada jadwal pendadaran.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="flex flex-col gap-3">
                    <h2 class="judul-bagian">Mahasiswa Bimbingan</h2>
                    <div class="tabel-wadah">
                        <div class="tabel-gulir">
                            <table class="tabel min-w-[880px]">
                                <thead>
                                    <tr>
                                        <th class="kolom-no">No</th>
                                        <th>Mahasiswa</th>
                                        <th>Tugas Akhir</th>
                                        <th>Peran</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(b, index) in props.bimbingan" :key="b.id" class="[&>td]:align-top">
                                        <td class="kolom-no">{{ index + 1 }}</td>
                                        <td>
                                            <span class="block font-medium text-black dark:text-foreground">{{ b.nama }}</span>
                                            <span class="teks-bantu block">{{ b.nim }} · {{ b.prodi }}</span>
                                        </td>
                                        <td class="max-w-[460px]">
                                            <span class="block font-medium text-black dark:text-foreground">{{ b.judul }}</span>
                                            <span class="teks-bantu block"
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
                                        <td>
                                            {{ b.peran }}
                                            <span v-if="b.pembimbing_lain" class="teks-bantu block">bersama {{ b.pembimbing_lain }}</span>
                                        </td>
                                        <td>
                                            <span
                                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                                :class="b.status === 'selesai' ? 'bg-[#eaf7ed] text-[#1aae39]' : 'bg-[#f2f9ff] text-[#0075de]'"
                                                >{{ b.status === 'selesai' ? 'Selesai' : 'Berjalan' }}</span
                                            >
                                        </td>
                                    </tr>
                                    <tr v-if="!props.bimbingan.length" class="baris-kosong">
                                        <td colspan="5" class="tabel-kosong">Belum ada mahasiswa bimbingan.</td>
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
            <form class="kartu w-full max-w-md p-6" @submit.prevent="kembalikan">
                <h3 class="judul-bagian">{{ kembalikanItem.aksi === 'perbaikan' ? 'Minta perbaikan' : 'Tolak pendaftaran' }}</h3>
                <p class="teks-bantu mt-1">Catatan ditampilkan ke {{ kembalikanItem.baris.nama }}.</p>
                <div class="mt-4 grid gap-2">
                    <label for="catatan_kembalikan" class="label-isian">Catatan</label>
                    <textarea id="catatan_kembalikan" v-model="catatanForm.catatan" rows="3" maxlength="1000" class="isian isian-area" required />
                    <InputError :message="catatanForm.errors.catatan" />
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="kembalikanItem = null">Batal</Button>
                    <Button
                        type="submit"
                        :disabled="catatanForm.processing"
                        :variant="kembalikanItem.aksi === 'perbaikan' ? 'default' : 'destructive'"
                        >{{ kembalikanItem.aksi === 'perbaikan' ? 'Minta Perbaikan' : 'Tolak' }}</Button
                    >
                </div>
            </form>
        </div>
    </AppLayout>
</template>
