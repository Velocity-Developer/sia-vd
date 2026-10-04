<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import DialogBukaKunciKrs from '@/components/DialogBukaKunciKrs.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { STATUS_KRS, type StatusKrs } from '@/lib/krs';
import { formatTanggal } from '@/lib/presensi';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Baris = {
    id: number;
    nim: string | null;
    nama: string | null;
    prodi: string | null;
    sks: number;
    final: boolean;
    status_krs: StatusKrs | null;
    catatan_revisi: string | null;
    batas_revisi: string | null;
    diubah_oleh: string | null;
    diubah_pada: string | null;
};
type Opsi = { id: number; name: string };

const props = defineProps<{
    mahasiswa: { data: Baris[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };
    filter: { tahun_akademik_id: number | null; prodi_id: number | null; status: 'ya' | 'tidak' | 'semua'; search: string };
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
    masaRevisiBerjalan: boolean;
    batasRevisi: string | null;
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const semua = 'semua';
const tahunAkademikId = ref(props.filter.tahun_akademik_id);
const prodiId = ref<number | string>(props.filter.prodi_id ?? semua);
const status = ref<string>(props.filter.status);
const search = ref(props.filter.search);
const kirim = () =>
    router.get(
        route('admin.status-krs.index'),
        {
            tahun_akademik_id: tahunAkademikId.value,
            prodi_id: prodiId.value === semua ? null : prodiId.value,
            status: status.value === semua ? null : status.value,
            search: search.value || null,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
let jeda: number | undefined;
watch(search, () => {
    window.clearTimeout(jeda);
    jeda = window.setTimeout(kirim, 400);
});

// Ya = KRS disetujui & terkunci; Tidak = dibuka agar bisa diubah mahasiswa (perlu batas tanggal di luar masa revisi).
const jadikanYa = ref<Baris | null>(null);
const jadikanTidak = ref<Baris | null>(null);
const ubah = (b: Baris, nilai: string) => {
    if ((nilai === 'ya') === b.final) return;
    if (nilai === 'ya') jadikanYa.value = b;
    else jadikanTidak.value = b;
};
const konfirmasiYa = () => {
    if (!jadikanYa.value) return;
    router.post(
        route('admin.status-krs.ya', { mahasiswa: jadikanYa.value.id, tahun_akademik_id: props.filter.tahun_akademik_id }),
        {},
        { preserveScroll: true, onFinish: () => (jadikanYa.value = null) },
    );
};
const tidakOpen = ref(false);
watch(jadikanTidak, (b) => (tidakOpen.value = b !== null));
watch(tidakOpen, (buka) => !buka && (jadikanTidak.value = null));
</script>

<template>
    <Head title="Status KRS" />
    <AppLayout :breadcrumbs="[{ title: 'Status KRS', href: route('admin.status-krs.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Status KRS</h1>
                        <p class="deskripsi-halaman">
                            Ya = KRS final (disetujui dan terkunci). Tidak = KRS belum final atau dibuka agar mahasiswa bisa mengubahnya.
                        </p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

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
                    <SelectFilter v-model="status" label="Filter status" @change="kirim()">
                        <option :value="semua">Semua Status</option>
                        <option value="ya">Ya</option>
                        <option value="tidak">Tidak</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black dark:text-foreground">{{ props.mahasiswa.total }}</span> mahasiswa
                    </p>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[720px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>NIM</th>
                                    <th>Nama</th>
                                    <th>SKS</th>
                                    <th class="w-[150px]">Status</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(b, index) in props.mahasiswa.data" :key="b.id">
                                    <td class="kolom-no">{{ (props.mahasiswa.from ?? 1) + index }}</td>
                                    <td class="whitespace-nowrap">{{ b.nim ?? '-' }}</td>
                                    <td>
                                        <span class="block font-medium text-black dark:text-foreground">{{ b.nama }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ b.prodi }}</span>
                                    </td>
                                    <td>{{ b.sks }}</td>
                                    <td>
                                        <select
                                            :value="b.final ? 'ya' : 'tidak'"
                                            class="isian isian-pilih"
                                            :class="b.final ? 'text-[#1aae39]' : 'text-[#b25000]'"
                                            :aria-label="`Status KRS ${b.nama}`"
                                            @change="
                                                ubah(b, ($event.target as HTMLSelectElement).value);
                                                ($event.target as HTMLSelectElement).value = b.final ? 'ya' : 'tidak';
                                            "
                                        >
                                            <option value="ya">Ya</option>
                                            <option value="tidak">Tidak</option>
                                        </select>
                                    </td>
                                    <td class="text-xs text-[#615d59]">
                                        <span
                                            v-if="b.status_krs"
                                            class="whitespace-nowrap rounded-full px-2 py-0.5 font-medium"
                                            :class="STATUS_KRS[b.status_krs].kelas"
                                            >{{ STATUS_KRS[b.status_krs].label }}</span
                                        >
                                        <span v-else>Belum disimpan</span>
                                        <span v-if="b.batas_revisi" class="mt-1 block">Dibuka sampai {{ formatTanggal(b.batas_revisi, false) }}</span>
                                        <span v-if="b.catatan_revisi && b.status_krs === 'perlu_revisi'" class="mt-1 block max-w-[260px]">{{
                                            b.catatan_revisi
                                        }}</span>
                                        <span v-if="b.diubah_oleh" class="mt-1 block text-[#a39e98]"
                                            >{{ formatTanggal(b.diubah_pada, false) }} · {{ b.diubah_oleh }}</span
                                        >
                                    </td>
                                </tr>
                                <tr v-if="!props.mahasiswa.data.length" class="baris-kosong">
                                    <td colspan="6" class="tabel-kosong">Belum ada mahasiswa yang mengisi KRS.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.mahasiswa.links" :total="props.mahasiswa.total" />
            </div>
        </div>

        <AlertModal
            :open="jadikanYa !== null"
            title="Ubah status KRS menjadi Ya?"
            :description="`KRS ${jadikanYa?.nama ?? ''} (${jadikanYa?.sks ?? 0} SKS) disetujui dan terkunci, sehingga tidak bisa diubah mahasiswa.`"
            confirm-text="Ya, setujui"
            cancel-text="Batal"
            @update:open="(buka: boolean) => !buka && (jadikanYa = null)"
            @confirm="konfirmasiYa"
        />
        <DialogBukaKunciKrs
            v-model:open="tidakOpen"
            judul="Ubah status KRS menjadi Tidak"
            :nama="jadikanTidak?.nama ?? 'Mahasiswa'"
            :url="
                jadikanTidak ? route('admin.status-krs.tidak', { mahasiswa: jadikanTidak.id, tahun_akademik_id: props.filter.tahun_akademik_id }) : ''
            "
            metode="post"
            :masa-revisi-berjalan="props.masaRevisiBerjalan"
            :batas-revisi="props.batasRevisi"
        />
    </AppLayout>
</template>
