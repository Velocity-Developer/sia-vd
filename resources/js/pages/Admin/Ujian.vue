<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal, jam } from '@/lib/presensi';
import { JENIS_UJIAN, MODE_UJIAN, STATUS_UJIAN, labelMode, type JenisUjian, type ModeUjian } from '@/lib/ujian';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { CalendarPlus, Eye, Pencil, Plus, Search, Send, Trash2 } from 'lucide-vue-next';
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
    ujians: { data: Ujian[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };
    filter: { tahun_akademik_id: number | null; prodi_id: number | null; jenis: JenisUjian | null; status: 'draf' | 'terbit' | null; search: string };
    belumAda: Record<'uts' | 'uas', number>;
    remidiSiap: number;
    susulanSiap: Record<'uts' | 'uas', number>;
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
// Satu modal untuk jadwal massal remidi dan susulan (UTS/UAS).
type JenisMassal = 'remidi' | 'uts_susulan' | 'uas_susulan';
const massalJenis = ref<JenisMassal | null>(null);
const formMassal = useForm({ tanggal: '', jam_mulai: '', jam_akhir: '', mode: 'online_berkas' });
const jumlahSiap = (jenis: JenisMassal) => (jenis === 'remidi' ? props.remidiSiap : props.susulanSiap[jenis === 'uts_susulan' ? 'uts' : 'uas']);
const buatMassalKhusus = () => {
    const jenis = massalJenis.value;
    if (!jenis) return;
    formMassal
        .transform((data) => ({ ...data, tahun_akademik_id: props.filter.tahun_akademik_id, ...(jenis === 'remidi' ? {} : { jenis }) }))
        .post(route(jenis === 'remidi' ? 'admin.ujian.remidi-massal' : 'admin.ujian.susulan-massal'), {
            preserveScroll: true,
            onSuccess: () => (massalJenis.value = null),
        });
};

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
</script>

<template>
    <Head title="Jadwal Ujian" />
    <AppLayout :breadcrumbs="[{ title: 'Jadwal Ujian', href: route('admin.ujian.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Jadwal Ujian</h1>
                        <p class="deskripsi-halaman">
                            Jadwal UTS/UAS per kelas beserta modenya. Pertemuan UTS/UAS kelas otomatis mengikuti tanggal, jam, dan ruang di sini.
                            Mahasiswa hanya melihat jadwal yang sudah diterbitkan.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button v-if="props.jumlahDraf" variant="outline" @click="terbitkanSemua">
                            <Send /> Terbitkan {{ props.jumlahDraf }} draf
                        </Button>
                        <Button as-child>
                            <Link :href="route('admin.ujian.create', { tahun_akademik_id: props.filter.tahun_akademik_id })"
                                ><Plus /> Tambah jadwal</Link
                            >
                        </Button>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <div v-if="props.remidiSiap" class="alert-info flex flex-wrap items-center gap-3">
                    <span>{{ props.remidiSiap }} kelas punya peserta remidi yang sudah lunas tetapi belum dijadwalkan remidinya.</span>
                    <Button size="sm" variant="outline" @click="massalJenis = 'remidi'">Buat semua jadwal remidi</Button>
                    <Button as-child size="sm" variant="outline">
                        <Link :href="route('admin.ujian.create', { tahun_akademik_id: props.filter.tahun_akademik_id, jenis: 'remidi' })"
                            >Jadwalkan satu kelas</Link
                        >
                    </Button>
                </div>
                <template v-for="j in ['uts', 'uas'] as const" :key="`susulan-${j}`">
                    <div v-if="props.susulanSiap[j]" class="alert-info flex flex-wrap items-center gap-3">
                        <span
                            >{{ props.susulanSiap[j] }} kelas punya pemohon susulan {{ JENIS_UJIAN[j] }} yang sudah lunas tetapi belum dijadwalkan
                            susulannya.</span
                        >
                        <Button size="sm" variant="outline" @click="massalJenis = `${j}_susulan`"
                            >Buat semua jadwal {{ JENIS_UJIAN[j] }} susulan</Button
                        >
                        <Button as-child size="sm" variant="outline">
                            <Link :href="route('admin.ujian.create', { tahun_akademik_id: props.filter.tahun_akademik_id, jenis: `${j}_susulan` })"
                                >Jadwalkan satu kelas</Link
                            >
                        </Button>
                    </div>
                </template>

                <div v-if="props.belumAda.uts || props.belumAda.uas" class="alert-gagal flex flex-wrap items-center gap-3">
                    <span>Kelas yang belum punya jadwal: UTS {{ props.belumAda.uts }}, UAS {{ props.belumAda.uas }}.</span>
                    <Button
                        v-for="j in ['uts', 'uas'] as const"
                        v-show="props.belumAda[j]"
                        :key="j"
                        size="sm"
                        variant="outline"
                        @click="buatMassal(j)"
                    >
                        <CalendarPlus /> Buat semua jadwal {{ JENIS_UJIAN[j] }} dari pertemuan
                    </Button>
                </div>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari kelas atau mata kuliah" aria-label="Cari" class="pl-9" />
                    </div>
                    <SelectFilter v-model="tahun" label="Tahun akademik" @change="kirim">
                        <option v-for="t in props.tahunAkademikOptions" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </SelectFilter>
                    <SelectFilter v-model="prodi" label="Program studi" @change="kirim">
                        <option value="all">Semua prodi</option>
                        <option v-for="p in props.prodiOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </SelectFilter>
                    <SelectFilter v-model="jenis" label="Jenis ujian" @change="kirim">
                        <option value="all">Semua jenis</option>
                        <option value="uts">UTS</option>
                        <option value="uas">UAS</option>
                        <option value="uts_susulan">UTS Susulan</option>
                        <option value="uas_susulan">UAS Susulan</option>
                        <option value="remidi">Remidi</option>
                    </SelectFilter>
                    <SelectFilter v-model="status" label="Status" @change="kirim">
                        <option value="all">Semua status</option>
                        <option value="draf">Draf</option>
                        <option value="terbit">Terbit</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.ujians.total }}</span> jadwal
                    </p>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[1020px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Kelas</th>
                                    <th>Jenis &amp; mode</th>
                                    <th>Tanggal &amp; jam</th>
                                    <th>Tempat</th>
                                    <th>Status</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(u, index) in props.ujians.data" :key="u.id">
                                    <td class="kolom-no">{{ (props.ujians.from ?? 1) + index }}</td>
                                    <td>
                                        <span class="block font-medium text-black">{{ u.kelas_kuliah?.mata_kuliah?.nama_matkul }}</span>
                                        <span class="block text-xs text-[#a39e98]">
                                            {{ u.kelas_kuliah?.kode_kelas }} · {{ u.kelas_kuliah?.dosen?.user?.name ?? '-' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="font-semibold text-black">{{ JENIS_UJIAN[u.jenis] }}</span>
                                        <span class="block text-xs text-[#615d59]">{{ labelMode(u.mode) }}</span>
                                    </td>
                                    <td>
                                        <span class="block">{{ formatTanggal(u.tanggal) }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ jam(u.jam_mulai) }}–{{ jam(u.jam_akhir) }}</span>
                                    </td>
                                    <td>
                                        {{ u.mode === 'tatap_muka' ? (u.ruang?.kode_ruang ?? '-') : 'Online' }}
                                        <span v-if="u.pengawas" class="block text-xs text-[#a39e98]">Pengawas: {{ u.pengawas }}</span>
                                    </td>
                                    <td>
                                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="STATUS_UJIAN[u.status].kelas">{{
                                            STATUS_UJIAN[u.status].label
                                        }}</span>
                                    </td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button v-if="u.status === 'draf'" variant="outline" size="sm" @click="terbitkan(u)">Terbitkan</Button>
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#0075de]">
                                                <Link :href="route('admin.ujian.show', u.id)" title="Detail" aria-label="Detail"><Eye /></Link>
                                            </Button>
                                            <Button as-child variant="outline" size="icon-sm" class="text-[#2a9d99]">
                                                <Link
                                                    :href="route('admin.ujian.edit', u.id)"
                                                    title="Edit"
                                                    :aria-label="`Ubah jadwal ${u.kelas_kuliah?.kode_kelas}`"
                                                    ><Pencil
                                                /></Link>
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="icon-sm"
                                                class="text-[#dd5b00]"
                                                title="Hapus"
                                                :aria-label="`Hapus jadwal ${u.kelas_kuliah?.kode_kelas}`"
                                                @click="pendingHapus = u"
                                            >
                                                <Trash2 />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.ujians.data.length" class="baris-kosong">
                                    <td colspan="7" class="tabel-kosong">Belum ada jadwal ujian yang cocok dengan filter.</td>
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

        <div v-if="massalJenis" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="massalJenis = null">
            <form class="kartu w-full max-w-lg p-6 shadow-xl" @submit.prevent="buatMassalKhusus">
                <h3 class="judul-bagian">Buat semua jadwal {{ JENIS_UJIAN[massalJenis].toLowerCase() }}</h3>
                <p class="mt-1 text-sm text-[#615d59]">
                    {{ jumlahSiap(massalJenis) }} kelas yang siap mendapat jadwal {{ JENIS_UJIAN[massalJenis].toLowerCase() }} berstatus draf dengan
                    waktu dan mode yang sama. Setelahnya sunting yang perlu berbeda, lalu terbitkan.
                    <template v-if="massalJenis !== 'remidi'">
                        Kelas yang ujian utamanya sesudah tanggal ini, atau batas input nilainya sebelum tanggal ini, dilewati.</template
                    >
                </p>
                <div class="mt-4 grid items-start gap-4 sm:grid-cols-3">
                    <label class="grid gap-2"
                        ><span class="label-isian">Tanggal</span><Input v-model="formMassal.tanggal" type="date" required
                    /></label>
                    <label class="grid gap-2"
                        ><span class="label-isian">Jam mulai</span><Input v-model="formMassal.jam_mulai" type="time" required
                    /></label>
                    <label class="grid gap-2"
                        ><span class="label-isian">Jam selesai</span><Input v-model="formMassal.jam_akhir" type="time" required
                    /></label>
                    <label class="grid gap-2 sm:col-span-3">
                        <span class="label-isian">Mode</span>
                        <select v-model="formMassal.mode" class="isian isian-pilih">
                            <option v-for="m in MODE_UJIAN" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                        <span v-if="formMassal.mode === 'tatap_muka'" class="teks-bantu">Ruang diisi per kelas sebelum diterbitkan.</span>
                    </label>
                </div>
                <p v-for="(pesan, k) in formMassal.errors" :key="k" class="mt-2 text-xs text-[#dd5b00]">{{ pesan }}</p>
                <div class="mt-6 flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="massalJenis = null">Batal</Button>
                    <Button type="submit" :disabled="formMassal.processing">Buat Draf</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
