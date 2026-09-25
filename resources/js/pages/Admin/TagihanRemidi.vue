<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { rupiah, STATUS_TAGIHAN_REMIDI, type Rincian, type StatusTagihanRemidi } from '@/lib/tagihanRemidi';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type Baris = {
    id: number;
    nama: string | null;
    nim: string | null;
    kelas: string | null;
    matkul: string | null;
    total: number;
    rincian: Rincian[];
    status: StatusTagihanRemidi;
    ada_bukti: boolean;
    bukti_diunggah_at: string | null;
    alasan_tolak: string | null;
    diverifikasi_oleh: string | null;
    diverifikasi_at: string | null;
    ujian_remidi: { tanggal: string; lewat: boolean } | null;
};
type Opsi = { id: number; name: string };

const props = defineProps<{
    tagihan: { data: Baris[]; links: { url: string | null; label: string; active: boolean }[]; from: number | null; total: number };
    ringkasan: {
        kelas_dikunci: number;
        belum_ditagih: number;
        belum_bayar: number;
        menunggu: number;
        ditolak: number;
        lunas: number;
    };
    kelasBelumKunci: { id: number; kode_kelas: string; matkul: string | null; dosen: string | null }[];
    filter: { tahun_akademik_id: number | null; status: string; search: string };
    batasBayar: string | null;
    batasLewat: boolean;
    adaJenisBiaya: boolean;
    tahunAkademikOptions: Opsi[];
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();

const semua = 'all';
const tahunAkademikId = ref<number | string>(props.filter.tahun_akademik_id ?? '');
const status = ref<string>(props.filter.status || semua);
const search = ref(props.filter.search);
let jedaCari: number | undefined;

const kirim = () =>
    router.get(
        route('admin.tagihan-remidi.index'),
        { tahun_akademik_id: tahunAkademikId.value, status: status.value, search: search.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );

watch(search, () => {
    window.clearTimeout(jedaCari);
    jedaCari = window.setTimeout(kirim, 400);
});

watch(
    () => props.filter,
    (baru) => {
        tahunAkademikId.value = baru.tahun_akademik_id ?? '';
        status.value = baru.status || semua;
        search.value = baru.search;
    },
);

const kunciMassalOpen = ref(false);
const kunciMassal = () =>
    router.post(
        route('admin.tagihan-remidi.kunci-massal'),
        { tahun_akademik_id: tahunAkademikId.value },
        { preserveScroll: true, onFinish: () => (kunciMassalOpen.value = false) },
    );
const lihatSemuaKelas = ref(false);
const kelasTampil = computed(() => (lihatSemuaKelas.value ? props.kelasBelumKunci : props.kelasBelumKunci.slice(0, 5)));

const terbitOpen = ref(false);
const terbitkan = () =>
    router.post(
        route('admin.tagihan-remidi.terbitkan'),
        { tahun_akademik_id: tahunAkademikId.value },
        { preserveScroll: true, onFinish: () => (terbitOpen.value = false) },
    );

const lunasItem = ref<Baris | null>(null);
const tandaiLunas = () => {
    if (!lunasItem.value) return;
    router.post(route('admin.tagihan-remidi.lunas', lunasItem.value.id), {}, { preserveScroll: true, onFinish: () => (lunasItem.value = null) });
};

const tolakItem = ref<Baris | null>(null);
const alasan = ref('');
const alasanError = ref('');
const bukaTolak = (baris: Baris) => {
    tolakItem.value = baris;
    alasan.value = '';
    alasanError.value = '';
};
const tolak = () => {
    if (!tolakItem.value) return;
    router.post(
        route('admin.tagihan-remidi.tolak', tolakItem.value.id),
        { alasan: alasan.value },
        {
            preserveScroll: true,
            onSuccess: () => (tolakItem.value = null),
            onError: (errors) => (alasanError.value = errors.alasan ?? ''),
        },
    );
};

const deskripsiLunas = (baris: Baris | null) =>
    baris
        ? `Tandai tagihan remidi ${baris.nama} (${baris.matkul}) sebesar ${rupiah(baris.total)} sebagai lunas?${baris.ada_bukti ? '' : ' Belum ada bukti yang diunggah; pastikan pembayaran sudah diterima.'}`
        : '';
</script>

<template>
    <Head title="Tagihan Remidi" />
    <AppLayout :breadcrumbs="[{ title: 'Tagihan Remidi', href: route('admin.tagihan-remidi.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] text-black">Tagihan Remidi</h1>
                        <p class="text-sm text-[#615d59]">
                            Diterbitkan dari daftar remidi yang sudah dikunci dosen.
                            <template v-if="props.batasBayar">
                                Batas bayar:
                                <span class="font-medium" :class="props.batasLewat ? 'text-[#dd5b00]' : 'text-black'">{{
                                    formatTanggal(props.batasBayar)
                                }}</span
                                ><template v-if="props.batasLewat"> (sudah lewat)</template>.
                            </template>
                        </p>
                    </div>
                    <Button
                        class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]"
                        :disabled="!props.ringkasan.belum_ditagih"
                        @click="terbitOpen = true"
                    >
                        Terbitkan Tagihan ({{ props.ringkasan.belum_ditagih }})
                    </Button>
                </div>

                <div v-if="page.props.flash?.success" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]">
                    {{ page.props.flash.error }}
                </div>
                <div v-if="!props.adaJenisBiaya" class="rounded-xl border border-[#f6d7c4] bg-[#fdf6f1] px-4 py-3 text-sm text-[#dd5b00]">
                    Belum ada jenis biaya kategori Remidi yang aktif.
                    <Link :href="route('admin.jenis-biaya.index')" class="font-medium text-[#0075de] hover:underline">Atur jenis biaya</Link>
                </div>
                <div v-if="!props.batasBayar" class="rounded-xl border border-[#f6d7c4] bg-[#fdf6f1] px-4 py-3 text-sm text-[#dd5b00]">
                    Batas bayar remidi tahun akademik ini belum diisi.
                    <Link :href="route('admin.tahun-akademik.index')" class="font-medium text-[#0075de] hover:underline">Atur di Tahun Akademik</Link>
                </div>
                <div v-if="props.ringkasan.menunggu" class="rounded-xl border border-[#cfe3f8] bg-[#f2f9ff] px-4 py-3 text-sm text-[#005bab]">
                    {{ props.ringkasan.menunggu }} bukti bayar menunggu verifikasi. Verifikasi sebelum ujian remidi berlangsung; mahasiswa baru bisa
                    ikut remidi setelah dinyatakan lunas.
                </div>
                <div v-if="props.kelasBelumKunci.length" class="rounded-xl border border-[#f1d9a0] bg-[#fff6e0] px-4 py-3 text-sm text-[#8a5a00]">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span
                            >{{ props.kelasBelumKunci.length }} kelas sudah final tetapi daftar remidinya belum dikunci dosen, jadi belum bisa
                            ditagih.</span
                        >
                        <Button size="sm" variant="outline" class="bg-white" @click="kunciMassalOpen = true"
                            >Kunci semua pakai usulan otomatis</Button
                        >
                    </div>
                    <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1">
                        <li v-for="k in kelasTampil" :key="k.id">
                            <Link :href="route('admin.kelas-kuliah.show', k.id)" class="font-medium hover:underline">{{ k.kode_kelas }}</Link>
                            <span class="text-[#a37b2a]">
                                · {{ k.matkul }}<template v-if="k.dosen"> · {{ k.dosen }}</template></span
                            >
                        </li>
                        <li v-if="props.kelasBelumKunci.length > 5 && !lihatSemuaKelas">
                            <button type="button" class="font-medium hover:underline" @click="lihatSemuaKelas = true">
                                +{{ props.kelasBelumKunci.length - 5 }} lainnya
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Menunggu Verifikasi</p>
                        <p class="text-[22px] font-bold text-[#0075de]">{{ props.ringkasan.menunggu }}</p>
                    </div>
                    <div class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Belum Bayar</p>
                        <p class="text-[22px] font-bold text-[#dd5b00]">{{ props.ringkasan.belum_bayar + props.ringkasan.ditolak }}</p>
                    </div>
                    <div class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Lunas</p>
                        <p class="text-[22px] font-bold text-[#1aae39]">{{ props.ringkasan.lunas }}</p>
                    </div>
                    <div class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 shadow-sm">
                        <p class="text-xs font-medium uppercase tracking-[0.08em] text-[#a39e98]">Kelas Remidi</p>
                        <p class="text-[22px] font-bold text-black">{{ props.ringkasan.kelas_dikunci }}</p>
                    </div>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="grid gap-3 sm:flex sm:flex-wrap sm:items-center">
                        <div class="relative w-full sm:w-72">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                            <Input
                                v-model="search"
                                placeholder="Cari nama atau NIM"
                                aria-label="Cari mahasiswa"
                                class="h-10 rounded-lg border-[#d8d5d2] bg-white pl-9 text-sm shadow-sm placeholder:text-[#a39e98] focus-visible:border-[#0075de] focus-visible:ring-2 focus-visible:ring-[#0075de]/15"
                            />
                        </div>
                        <SelectFilter v-model="tahunAkademikId" label="Filter tahun akademik" @change="kirim">
                            <option v-for="item in props.tahunAkademikOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
                        </SelectFilter>
                        <SelectFilter v-model="status" label="Filter status" @change="kirim">
                            <option :value="semua">Semua Status</option>
                            <option value="menunggu_verifikasi">Menunggu Verifikasi</option>
                            <option value="belum_bayar">Belum Bayar</option>
                            <option value="ditolak">Ditolak</option>
                            <option value="lunas">Lunas</option>
                        </SelectFilter>
                    </div>
                    <p class="whitespace-nowrap text-sm text-[#615d59]">
                        <span class="font-medium text-black">{{ props.tagihan.total }}</span> tagihan
                    </p>
                </div>

                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[960px] text-left">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Mahasiswa</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Mata Kuliah</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Tagihan</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Status</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Bukti</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="baris in props.tagihan.data" :key="baris.id" class="align-top hover:bg-[#f6f5f4]/60">
                                    <td class="px-4 py-3 text-[15px] text-black">
                                        <span class="block font-medium">{{ baris.nama }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ baris.nim }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">
                                        <span class="block">{{ baris.matkul }}</span>
                                        <span class="block text-xs text-[#a39e98]">Kelas {{ baris.kelas }}</span>
                                        <span
                                            v-if="baris.ujian_remidi && baris.status !== 'lunas'"
                                            class="block text-xs"
                                            :class="baris.ujian_remidi.lewat ? 'text-[#b42318]' : 'text-[#dd5b00]'"
                                            >Remidi {{ formatTanggal(baris.ujian_remidi.tanggal, false)
                                            }}{{ baris.ujian_remidi.lewat ? ' (sudah mulai)' : '' }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">
                                        <span class="block">{{ rupiah(baris.total) }}</span>
                                        <span v-for="r in baris.rincian" :key="r.nama" class="block text-xs text-[#a39e98]">
                                            {{ r.nama }}<template v-if="r.jumlah > 1"> · {{ r.jumlah }} × {{ rupiah(r.nominal_satuan) }}</template>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px]">
                                        <span
                                            class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="STATUS_TAGIHAN_REMIDI[baris.status].kelas"
                                            >{{ STATUS_TAGIHAN_REMIDI[baris.status].label }}</span
                                        >
                                        <span v-if="baris.alasan_tolak" class="mt-1 block max-w-[200px] text-xs text-[#a39e98]"
                                            >Ditolak: {{ baris.alasan_tolak }}</span
                                        >
                                        <span v-if="baris.status === 'lunas' && baris.diverifikasi_oleh" class="mt-1 block text-xs text-[#a39e98]"
                                            >{{ formatTanggal(baris.diverifikasi_at, false) }} · {{ baris.diverifikasi_oleh }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <template v-if="baris.ada_bukti">
                                            <a
                                                :href="route('berkas.bukti-remidi', baris.id)"
                                                target="_blank"
                                                rel="noopener"
                                                class="font-medium text-[#0075de] hover:underline"
                                                >Lihat bukti</a
                                            >
                                            <span class="block text-xs text-[#a39e98]">{{ formatTanggal(baris.bukti_diunggah_at, false) }}</span>
                                        </template>
                                        <span v-else class="text-xs text-[#a39e98]">Belum ada</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div v-if="baris.status !== 'lunas'" class="flex items-center justify-end gap-2">
                                            <Button
                                                v-if="baris.status === 'menunggu_verifikasi'"
                                                variant="outline"
                                                class="h-8 rounded-lg border-[#d8d5d2] px-3 text-sm text-[#dd5b00]"
                                                @click="bukaTolak(baris)"
                                                >Tolak</Button
                                            >
                                            <Button
                                                class="h-8 rounded-lg bg-[#0075de] px-3 text-sm text-white hover:bg-[#005bab]"
                                                @click="lunasItem = baris"
                                                >Tandai Lunas</Button
                                            >
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.tagihan.data.length">
                                    <td colspan="6" class="px-4 py-14 text-center text-sm text-[#615d59]">Belum ada tagihan remidi yang cocok.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.tagihan.links" :total="props.tagihan.total" />
            </div>
        </div>

        <AlertModal
            :open="kunciMassalOpen"
            title="Kunci semua daftar remidi?"
            :description="`${props.kelasBelumKunci.length} kelas akan dikunci memakai usulan otomatis (huruf tidak lulus/boleh diulang yang ikut UAS), tanpa tambahan atau coretan dosen.`"
            confirm-text="Kunci Semua"
            cancel-text="Batal"
            @update:open="kunciMassalOpen = $event"
            @confirm="kunciMassal"
            @cancel="kunciMassalOpen = false"
        />
        <AlertModal
            :open="terbitOpen"
            title="Terbitkan tagihan remidi?"
            :description="`${props.ringkasan.belum_ditagih} peserta remidi akan ditagih sesuai tarif jenis biaya Remidi. Tagihan yang sudah terbit tidak diubah.`"
            confirm-text="Terbitkan"
            cancel-text="Batal"
            @update:open="terbitOpen = $event"
            @confirm="terbitkan"
            @cancel="terbitOpen = false"
        />
        <AlertModal
            :open="!!lunasItem"
            title="Tandai lunas?"
            :description="deskripsiLunas(lunasItem)"
            confirm-text="Tandai Lunas"
            cancel-text="Batal"
            @update:open="!$event && (lunasItem = null)"
            @confirm="tandaiLunas"
            @cancel="lunasItem = null"
        />
        <div v-if="tolakItem" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="tolakItem = null">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold">Tolak bukti bayar</h3>
                <p class="mt-2 text-sm text-[#615d59]">
                    {{ tolakItem.nama }} bisa mengunggah ulang sebelum batas bayar. Alasan penolakan ditampilkan ke mahasiswa.
                </p>
                <label class="mt-4 grid gap-2 text-sm">
                    <span class="font-medium">Alasan</span>
                    <Input v-model="alasan" placeholder="Mis. nominal tidak sesuai" class="h-10" />
                    <span v-if="alasanError" class="text-xs text-[#dd5b00]">{{ alasanError }}</span>
                </label>
                <div class="mt-6 flex justify-end gap-2">
                    <Button variant="outline" class="rounded-full" @click="tolakItem = null">Batal</Button>
                    <Button class="rounded-full bg-[#dd5b00] text-white hover:bg-[#b84b00]" @click="tolak">Tolak</Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
