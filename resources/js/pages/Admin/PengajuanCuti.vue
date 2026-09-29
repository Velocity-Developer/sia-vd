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
    pengajuan: { data: Baris[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };
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
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Pengajuan Cuti</h1>
                        <p class="deskripsi-halaman">
                            Setujui, minta perbaikan, atau tolak pengajuan cuti dan aktif kembali. Batas cuti {{ props.maksCuti }} semester selama
                            studi; periode pengajuan diatur per semester di Tahun Akademik.
                        </p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <nav class="flex gap-1 overflow-x-auto border-b border-[#e6e6e6] dark:border-border" aria-label="Jenis pengajuan">
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

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari nama atau NIM" aria-label="Cari mahasiswa" class="pl-9" />
                    </div>
                    <SelectFilter v-model="status" label="Filter status" @change="kirim">
                        <option :value="semua">Semua status</option>
                        <option v-for="s in ['menunggu', 'perlu_perbaikan', 'disetujui', 'ditolak'] as const" :key="s" :value="s">
                            {{ STATUS_PENGAJUAN[s].label }}
                        </option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.pengajuan.total }}</span> pengajuan
                    </p>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[940px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Mahasiswa</th>
                                    <th>Pengajuan</th>
                                    <th>Status</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(b, index) in props.pengajuan.data" :key="b.id">
                                    <td class="kolom-no">{{ (props.pengajuan.from ?? 1) + index }}</td>
                                    <td>
                                        <span class="block font-medium text-black">{{ b.nama }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ b.nim }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ b.prodi }}</span>
                                        <span class="block text-xs text-[#615d59]">Status: {{ b.status_mahasiswa }}</span>
                                        <span class="block text-xs text-[#615d59]">Cuti disetujui: {{ b.jumlah_cuti }}/{{ props.maksCuti }}</span>
                                    </td>
                                    <td class="max-w-[440px]">
                                        <template v-if="cutiTab">
                                            <span class="block font-medium text-black"
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
                                                class="mt-1 block whitespace-pre-line rounded-lg bg-[#f6f5f4] p-3 text-sm dark:bg-muted"
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
                                    <td>
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
                                    <td class="kolom-aksi">
                                        <div v-if="b.status === 'menunggu'" class="aksi-tabel">
                                            <Button variant="outline" size="sm" @click="bukaKembalikan(b, 'perbaikan')">Perbaikan</Button>
                                            <Button variant="destructive" size="sm" @click="bukaKembalikan(b, 'tolak')">Tolak</Button>
                                            <Button size="sm" @click="setujuiItem = b">Setujui</Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.pengajuan.data.length" class="baris-kosong">
                                    <td colspan="5" class="tabel-kosong">
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
            <form class="kartu w-full max-w-md p-6 shadow-xl" @submit.prevent="kembalikan">
                <h3 class="judul-bagian">{{ kembalikanItem.aksi === 'perbaikan' ? 'Minta perbaikan' : 'Tolak pengajuan' }}</h3>
                <p class="mt-2 text-sm text-[#615d59]">
                    {{
                        kembalikanItem.aksi === 'perbaikan'
                            ? `${kembalikanItem.baris.nama} bisa memperbaiki isian lalu mengirim ulang.`
                            : `${kembalikanItem.baris.nama} bisa mengajukan lagi dengan form baru.`
                    }}
                    Catatan ditampilkan ke mahasiswa.
                </p>
                <label class="mt-4 grid gap-2">
                    <span class="label-isian">Catatan</span>
                    <textarea
                        v-model="catatanForm.catatan"
                        rows="3"
                        maxlength="1000"
                        class="isian isian-area"
                        :placeholder="
                            kembalikanItem.aksi === 'perbaikan' ? 'Mis. bukti bayar tidak terbaca' : 'Mis. alasan cuti tidak memenuhi ketentuan'
                        "
                        required
                    />
                    <InputError :message="catatanForm.errors.catatan" />
                </label>
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
