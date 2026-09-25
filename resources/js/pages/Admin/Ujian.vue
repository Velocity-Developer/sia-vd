<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal, jam } from '@/lib/presensi';
import { JENIS_UJIAN, STATUS_UJIAN, labelMode, type JenisUjian, type ModeUjian } from '@/lib/ujian';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CalendarPlus, Pencil, Plus, Search, Send, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Opsi = { id: number; name: string };
type Ujian = {
    id: number;
    jenis: JenisUjian;
    mode: ModeUjian;
    tanggal: string;
    jam_mulai: string;
    jam_akhir: string;
    status: 'draf' | 'terbit';
    pengawas: string | null;
    ruang?: { kode_ruang: string } | null;
    kelas_kuliah?: {
        kode_kelas: string;
        mata_kuliah?: { kode_matkul: string; nama_matkul: string } | null;
        dosen?: { user?: { name: string } | null } | null;
    } | null;
};

const props = defineProps<{
    ujians: { data: Ujian[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
    filter: { tahun_akademik_id: number | null; prodi_id: number | null; jenis: JenisUjian | null; status: 'draf' | 'terbit' | null; search: string };
    belumAda: Record<JenisUjian, number>;
    jumlahDraf: number;
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
}>();

const page = usePage<{ flash?: { success?: string; error?: string } }>();
const tahun = ref<number | string>(props.filter.tahun_akademik_id ?? '');
const prodi = ref<number | string>(props.filter.prodi_id ?? 'all');
const jenis = ref<string>(props.filter.jenis ?? 'all');
const status = ref<string>(props.filter.status ?? 'all');
const search = ref(props.filter.search);
const kirim = () =>
    router.get(
        route('admin.ujian.index'),
        {
            tahun_akademik_id: tahun.value,
            prodi_id: prodi.value === 'all' ? null : prodi.value,
            jenis: jenis.value === 'all' ? null : jenis.value,
            status: status.value === 'all' ? null : status.value,
            search: search.value || null,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
let jeda: number | undefined;
watch(search, () => {
    window.clearTimeout(jeda);
    jeda = window.setTimeout(kirim, 350);
});

const buatMassal = (j: JenisUjian) =>
    router.post(route('admin.ujian.buat-massal'), { tahun_akademik_id: props.filter.tahun_akademik_id, jenis: j }, { preserveScroll: true });
const terbitkanSemua = () =>
    router.put(route('admin.ujian.terbitkan'), { tahun_akademik_id: props.filter.tahun_akademik_id }, { preserveScroll: true });
const terbitkan = (u: Ujian) => router.put(route('admin.ujian.terbitkan'), { ids: [u.id] }, { preserveScroll: true });

const pendingHapus = ref<Ujian | null>(null);
const hapus = () => {
    if (!pendingHapus.value) return;
    router.delete(route('admin.ujian.destroy', pendingHapus.value.id), { preserveScroll: true, onFinish: () => (pendingHapus.value = null) });
};

const th = 'px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]';
</script>

<template>
    <Head title="Jadwal Ujian" />
    <AppLayout :breadcrumbs="[{ title: 'Jadwal Ujian', href: route('admin.ujian.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] text-black">Jadwal Ujian</h1>
                        <p class="max-w-2xl text-sm text-[#615d59]">
                            Jadwal UTS/UAS per kelas beserta modenya. Pertemuan UTS/UAS kelas otomatis mengikuti tanggal, jam, dan ruang di sini.
                            Mahasiswa hanya melihat jadwal yang sudah diterbitkan.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="route('admin.ujian.create', { tahun_akademik_id: props.filter.tahun_akademik_id })">
                            <Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]"
                                ><Plus class="mr-1 size-4" /> Tambah jadwal</Button
                            >
                        </Link>
                        <Button
                            v-if="props.jumlahDraf"
                            variant="outline"
                            class="rounded-lg border-[#e6e6e6] bg-white text-black"
                            @click="terbitkanSemua"
                        >
                            <Send class="mr-1 size-4" /> Terbitkan {{ props.jumlahDraf }} draf
                        </Button>
                    </div>
                </div>

                <div
                    v-if="page.props.flash?.success"
                    class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]"
                    role="alert"
                >
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <div
                    v-if="props.belumAda.uts || props.belumAda.uas"
                    class="flex flex-wrap items-center gap-3 rounded-xl border border-[#f1d9a0] bg-[#fff6e0] px-4 py-3 text-sm text-[#8a5a00]"
                >
                    <span>Kelas yang belum punya jadwal: UTS {{ props.belumAda.uts }}, UAS {{ props.belumAda.uas }}.</span>
                    <Button
                        v-for="j in ['uts', 'uas'] as const"
                        v-show="props.belumAda[j]"
                        :key="j"
                        size="sm"
                        variant="outline"
                        class="bg-white"
                        @click="buatMassal(j)"
                    >
                        <CalendarPlus class="mr-1 size-4" /> Buat semua jadwal {{ JENIS_UJIAN[j] }} dari pertemuan
                    </Button>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative w-full sm:max-w-xs">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari kelas atau mata kuliah" class="h-10 rounded-lg bg-white pl-9 text-sm" />
                    </div>
                    <SelectFilter v-model="tahun" label="Tahun akademik" @change="kirim">
                        <option v-for="t in props.tahunAkademikOptions" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </SelectFilter>
                    <SelectFilter v-model="prodi" label="Program studi" @change="kirim">
                        <option value="all">Semua prodi</option>
                        <option v-for="p in props.prodiOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </SelectFilter>
                    <SelectFilter v-model="jenis" label="Jenis ujian" @change="kirim">
                        <option value="all">UTS &amp; UAS</option>
                        <option value="uts">UTS</option>
                        <option value="uas">UAS</option>
                    </SelectFilter>
                    <SelectFilter v-model="status" label="Status" @change="kirim">
                        <option value="all">Semua status</option>
                        <option value="draf">Draf</option>
                        <option value="terbit">Terbit</option>
                    </SelectFilter>
                </div>

                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[980px] text-left">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th :class="th">Kelas</th>
                                    <th :class="th">Jenis &amp; mode</th>
                                    <th :class="th">Tanggal &amp; jam</th>
                                    <th :class="th">Tempat</th>
                                    <th :class="th">Status</th>
                                    <th :class="[th, 'text-right']">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="u in props.ujians.data" :key="u.id" class="hover:bg-[#f6f5f4]/60">
                                    <td class="px-4 py-3 text-[15px]">
                                        <span class="block font-medium text-black">{{ u.kelas_kuliah?.mata_kuliah?.nama_matkul }}</span>
                                        <span class="block text-xs text-[#a39e98]">
                                            {{ u.kelas_kuliah?.kode_kelas }} · {{ u.kelas_kuliah?.dosen?.user?.name ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="font-semibold text-black">{{ JENIS_UJIAN[u.jenis] }}</span>
                                        <span class="block text-xs text-[#615d59]">{{ labelMode(u.mode) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-[#31302e]">
                                        <span class="block">{{ formatTanggal(u.tanggal) }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ jam(u.jam_mulai) }}–{{ jam(u.jam_akhir) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-[#31302e]">
                                        {{ u.mode === 'tatap_muka' ? (u.ruang?.kode_ruang ?? '-') : 'Online' }}
                                        <span v-if="u.pengawas" class="block text-xs text-[#a39e98]">Pengawas: {{ u.pengawas }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="STATUS_UJIAN[u.status].kelas">{{
                                            STATUS_UJIAN[u.status].label
                                        }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center justify-end gap-2 text-sm font-medium">
                                            <Link :href="route('admin.ujian.show', u.id)" class="text-[#0075de] hover:underline">Detail</Link>
                                            <button
                                                v-if="u.status === 'draf'"
                                                type="button"
                                                class="text-[#0075de] hover:underline"
                                                @click="terbitkan(u)"
                                            >
                                                Terbitkan
                                            </button>
                                            <Link :href="route('admin.ujian.edit', u.id)" :aria-label="`Ubah jadwal ${u.kelas_kuliah?.kode_kelas}`">
                                                <Button variant="outline" size="icon" class="size-8 rounded-full border-[#e6e6e6] text-[#2a9d99]"
                                                    ><Pencil class="size-4"
                                                /></Button>
                                            </Link>
                                            <Button
                                                variant="outline"
                                                size="icon"
                                                class="size-8 rounded-full border-[#e6e6e6] text-[#dd5b00]"
                                                :aria-label="`Hapus jadwal ${u.kelas_kuliah?.kode_kelas}`"
                                                @click="pendingHapus = u"
                                            >
                                                <Trash2 class="size-4" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.ujians.data.length">
                                    <td colspan="6" class="px-4 py-14 text-center text-sm text-[#615d59]">
                                        Belum ada jadwal ujian yang cocok dengan filter.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.ujians.links" :total="props.ujians.total" />

                <AlertModal
                    :open="pendingHapus !== null"
                    :description="`Hapus jadwal ${pendingHapus ? JENIS_UJIAN[pendingHapus.jenis] : ''} kelas ${pendingHapus?.kelas_kuliah?.kode_kelas ?? ''}?`"
                    confirm-text="Hapus"
                    cancel-text="Batal"
                    @update:open="!$event && (pendingHapus = null)"
                    @confirm="hapus"
                    @cancel="pendingHapus = null"
                />
            </div>
        </div>
    </AppLayout>
</template>
