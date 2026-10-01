<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { STATUS_KRS, type StatusKrs } from '@/lib/krs';
import { formatTanggal } from '@/lib/presensi';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type Baris = {
    id: number;
    nama: string | null;
    nim: string | null;
    prodi: string | null;
    semester: number | null;
    sks: number;
    status: StatusKrs;
    disimpan_pada: string | null;
    catatan_revisi: string | null;
    dibuka_sampai: string | null;
    diverifikasi_oleh: string | null;
    diverifikasi_pada: string | null;
};
type Opsi = { id: number; name: string };

const props = defineProps<{
    krs: { data: Baris[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };
    filter: { tahun_akademik_id: number | null; prodi_id: number | null; status: StatusKrs | 'semua'; search: string };
    jumlah: Partial<Record<StatusKrs, number>>;
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
    periode: { krs_awal: string | null; krs_akhir: string | null; batas_revisi: string | null; masa_revisi_berjalan: boolean } | null;
    verifikasiAktif: boolean;
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const semua = 'semua';
const tahunAkademikId = ref(props.filter.tahun_akademik_id);
const prodiId = ref<number | string>(props.filter.prodi_id ?? semua);
const status = ref<string>(props.filter.status);
const search = ref(props.filter.search);
const kirim = (ubah: Record<string, unknown> = {}) =>
    router.get(
        route('admin.verifikasi-krs.index'),
        {
            tahun_akademik_id: tahunAkademikId.value,
            prodi_id: prodiId.value === semua ? null : prodiId.value,
            status: status.value,
            search: search.value || null,
            ...ubah,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
let jeda: number | undefined;
watch(search, () => {
    window.clearTimeout(jeda);
    jeda = window.setTimeout(kirim, 400);
});
const pilihStatus = (nilai: string) => {
    status.value = nilai;
    kirim();
};

// Pilihan untuk setujui massal hanya berlaku bagi KRS yang menunggu verifikasi di halaman ini.
const terpilih = ref<number[]>([]);
const bisaDipilih = computed(() => props.krs.data.filter((b) => b.status === 'diajukan').map((b) => b.id));
watch(
    () => props.krs.data,
    () => (terpilih.value = terpilih.value.filter((id) => bisaDipilih.value.includes(id))),
);
const semuaTerpilih = computed({
    get: () => bisaDipilih.value.length > 0 && bisaDipilih.value.every((id) => terpilih.value.includes(id)),
    set: (nilai: boolean) => (terpilih.value = nilai ? [...bisaDipilih.value] : []),
});
const konfirmasiMassal = ref(false);
const setujuiMassal = () =>
    router.post(
        route('admin.verifikasi-krs.setujui-massal'),
        { ids: terpilih.value },
        {
            preserveScroll: true,
            onSuccess: () => (terpilih.value = []),
            onFinish: () => (konfirmasiMassal.value = false),
        },
    );

const tabStatus: { nilai: StatusKrs | 'semua'; label: string }[] = [
    { nilai: 'diajukan', label: 'Menunggu verifikasi' },
    { nilai: 'perlu_revisi', label: 'Perlu revisi' },
    { nilai: 'disetujui', label: 'Disetujui' },
    { nilai: 'semua', label: 'Semua' },
];
</script>

<template>
    <Head title="Verifikasi KRS" />
    <AppLayout :breadcrumbs="[{ title: 'Verifikasi KRS', href: route('admin.verifikasi-krs.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Verifikasi KRS</h1>
                        <p class="deskripsi-halaman">
                            Periksa KRS yang diajukan mahasiswa, lalu setujui atau kembalikan untuk revisi. KRS yang disetujui menjadi final.
                        </p>
                    </div>
                </div>

                <div v-if="!props.verifikasiAktif" class="alert-info" role="status">
                    Verifikasi KRS sedang mati, jadi KRS yang disimpan mahasiswa langsung disetujui. Nyalakan di Pengaturan Sistem → Akademik.
                </div>
                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <p v-if="props.periode" class="text-sm text-[#615d59] dark:text-muted-foreground">
                    Masa KRS {{ formatTanggal(props.periode.krs_awal, false) }} – {{ formatTanggal(props.periode.krs_akhir, false) }}
                    <template v-if="props.periode.batas_revisi && props.periode.batas_revisi !== props.periode.krs_akhir?.slice(0, 10)">
                        · revisi sampai {{ formatTanggal(props.periode.batas_revisi, false) }}</template
                    >
                    <span v-if="!props.periode.masa_revisi_berjalan" class="font-medium"> · masa KRS &amp; revisi sudah ditutup</span>
                </p>

                <nav class="flex gap-1 overflow-x-auto border-b border-[#e6e6e6] dark:border-border" aria-label="Status KRS">
                    <button
                        v-for="tab in tabStatus"
                        :key="tab.nilai"
                        type="button"
                        class="-mb-px flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium"
                        :class="
                            tab.nilai === props.filter.status
                                ? 'border-[#0075de] text-[#0075de]'
                                : 'border-transparent text-[#615d59] hover:text-black'
                        "
                        @click="pilihStatus(tab.nilai)"
                    >
                        {{ tab.label }}
                        <span v-if="tab.nilai !== 'semua' && props.jumlah[tab.nilai]" class="rounded-full bg-[#0075de] px-1.5 text-xs text-white">{{
                            props.jumlah[tab.nilai]
                        }}</span>
                    </button>
                </nav>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari nama atau NIM" aria-label="Cari mahasiswa" class="pl-9" />
                    </div>
                    <SelectFilter v-model="tahunAkademikId" label="Filter tahun akademik" @change="kirim()">
                        <option v-for="item in props.tahunAkademikOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </SelectFilter>
                    <SelectFilter v-model="prodiId" label="Filter program studi" @change="kirim()">
                        <option :value="semua">Semua Program Studi</option>
                        <option v-for="item in props.prodiOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black dark:text-foreground">{{ props.krs.total }}</span> KRS
                    </p>
                </div>

                <div v-if="bisaDipilih.length" class="flex flex-wrap items-center justify-between gap-3">
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="semuaTerpilih" type="checkbox" class="size-4 accent-[#0075de]" />
                        Pilih semua yang menunggu di halaman ini
                    </label>
                    <Button size="sm" :disabled="!terpilih.length" @click="konfirmasiMassal = true">
                        Setujui {{ terpilih.length ? `${terpilih.length} KRS` : 'terpilih' }}
                    </Button>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[860px]">
                            <thead>
                                <tr>
                                    <th class="w-10"><span class="sr-only">Pilih</span></th>
                                    <th class="kolom-no">No</th>
                                    <th>Mahasiswa</th>
                                    <th>SKS</th>
                                    <th>Diajukan</th>
                                    <th>Status</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(b, index) in props.krs.data" :key="b.id">
                                    <td>
                                        <input
                                            v-if="b.status === 'diajukan'"
                                            v-model="terpilih"
                                            type="checkbox"
                                            :value="b.id"
                                            class="size-4 accent-[#0075de]"
                                            :aria-label="`Pilih KRS ${b.nama}`"
                                        />
                                    </td>
                                    <td class="kolom-no">{{ (props.krs.from ?? 1) + index }}</td>
                                    <td>
                                        <span class="block font-medium text-black dark:text-foreground">{{ b.nama }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ b.nim }} · Semester {{ b.semester ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ b.prodi }}</span>
                                    </td>
                                    <td>{{ b.sks }}</td>
                                    <td class="whitespace-nowrap">{{ formatTanggal(b.disimpan_pada, false) }}</td>
                                    <td>
                                        <span
                                            class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="STATUS_KRS[b.status].kelas"
                                            >{{ STATUS_KRS[b.status].label }}</span
                                        >
                                        <span
                                            v-if="b.status === 'perlu_revisi' && b.catatan_revisi"
                                            class="mt-1 block max-w-[240px] text-xs text-[#615d59]"
                                            >{{ b.catatan_revisi }}</span
                                        >
                                        <span v-if="b.status === 'perlu_revisi' && b.dibuka_sampai" class="mt-1 block text-xs text-[#615d59]"
                                            >Dibuka sampai {{ formatTanggal(b.dibuka_sampai, false) }}</span
                                        >
                                        <span v-if="b.diverifikasi_oleh" class="mt-1 block text-xs text-[#a39e98]"
                                            >{{ formatTanggal(b.diverifikasi_pada, false) }} · {{ b.diverifikasi_oleh }}</span
                                        >
                                    </td>
                                    <td class="kolom-aksi">
                                        <Button as-child size="sm" :variant="b.status === 'diajukan' ? 'default' : 'outline'">
                                            <Link :href="route('admin.verifikasi-krs.show', b.id)">{{
                                                b.status === 'diajukan' ? 'Periksa' : 'Lihat'
                                            }}</Link>
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="!props.krs.data.length" class="baris-kosong">
                                    <td colspan="7" class="tabel-kosong">Tidak ada KRS dengan status ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.krs.links" :total="props.krs.total" />
            </div>
        </div>

        <AlertModal
            v-model:open="konfirmasiMassal"
            :title="`Setujui ${terpilih.length} KRS?`"
            description="KRS yang disetujui menjadi final dan tidak bisa diubah mahasiswa, kecuali dikembalikan untuk revisi."
            confirm-text="Setujui"
            cancel-text="Batal"
            @confirm="setujuiMassal"
        />
    </AppLayout>
</template>
