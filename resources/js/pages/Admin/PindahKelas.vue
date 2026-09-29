<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { AlertTriangle, Check, Search, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Pengajuan = {
    id: number;
    mahasiswa: string | null;
    nim: string | null;
    prodi: string | null;
    kelas_asal: string | null;
    kelas_asal_matkul: string | null;
    kelas_tujuan: string | null;
    kelas_tujuan_matkul: string | null;
    alasan: string;
    status: string;
    catatan_admin: string | null;
    diproses_oleh: string | null;
    diproses_at: string | null;
    created_at: string | null;
    nilai: string | null;
    nilai_terisi: boolean;
};

type Pagination = {
    data: Pengajuan[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
    from: number | null;
};

const props = defineProps<{
    isActive: boolean;
    pengajuans: Pagination;
    search?: string;
    tahunAkademiks: { id: number; tahun: string; semester: string }[];
    tahunAkademikId: number | null;
}>();
const search = ref(props.search ?? '');
const tahunAkademikId = ref<number | string>(props.tahunAkademikId ?? 'all');
const applyFilters = () =>
    router.get(
        route('admin.pindah-kelas.index'),
        { search: search.value, tahun_akademik_id: tahunAkademikId.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
watch(search, applyFilters);

const page = usePage<{ flash?: { success?: string; error?: string; pindah_kelas_warning?: string } }>();

const statusLabel: Record<string, string> = {
    pending: 'Menunggu',
    disetujui: 'Disetujui',
    ditolak: 'Ditolak',
};

const statusClass: Record<string, string> = {
    pending: 'bg-[#fff3e0] text-[#dd5b00] dark:bg-amber-950 dark:text-amber-400',
    disetujui: 'bg-[#e6f7ea] text-[#1aae39] dark:bg-emerald-950 dark:text-emerald-400',
    ditolak: 'bg-[#fdecea] text-[#d93025] dark:bg-red-950 dark:text-red-400',
};

const formatDateTime = (value: string | null): string => {
    if (!value) return '-';

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
};

/* Status form pindah kelas; diubah di Pengaturan Sistem → Akademik. */
const { can } = usePermissions();

/* Approve */
const approveTarget = ref<Pengajuan | null>(null);
const approveWarning = ref<string | null>(null);
const processing = ref(false);

const askApprove = (pengajuan: Pengajuan) => {
    approveTarget.value = pengajuan;
    approveWarning.value = pengajuan.nilai_terisi
        ? `Mahasiswa ini sudah memiliki nilai ${pengajuan.nilai} pada kelas asal ${pengajuan.kelas_asal}. Menyetujui pengajuan akan memindahkan baris KRS tersebut beserta nilainya ke kelas ${pengajuan.kelas_tujuan}.`
        : null;
};

const confirmApprove = () => {
    if (!approveTarget.value) return;

    processing.value = true;
    router.put(
        route('admin.pindah-kelas.approve', approveTarget.value.id),
        { force: approveWarning.value ? true : false },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                approveTarget.value = null;
                approveWarning.value = null;
            },
        },
    );
};

const cancelApprove = () => {
    approveTarget.value = null;
    approveWarning.value = null;
};

/* Reject */
const rejectTarget = ref<Pengajuan | null>(null);
const rejectForm = useForm({ catatan_admin: '' });

const askReject = (pengajuan: Pengajuan) => {
    rejectTarget.value = pengajuan;
    rejectForm.reset();
    rejectForm.clearErrors();
};

const confirmReject = () => {
    if (!rejectTarget.value) return;

    rejectForm.put(route('admin.pindah-kelas.reject', rejectTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            rejectTarget.value = null;
            rejectForm.reset();
        },
    });
};

const cancelReject = () => {
    rejectTarget.value = null;
    rejectForm.reset();
    rejectForm.clearErrors();
};

const warningFlash = ref<string | null>(null);
watch(
    () => page.props.flash?.pindah_kelas_warning,
    (value) => (warningFlash.value = value ?? null),
    { immediate: true },
);
</script>

<template>
    <Head title="Pindah Kelas" />
    <AppLayout :breadcrumbs="[{ title: 'Pindah Kelas', href: route('admin.pindah-kelas.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Pindah Kelas</h1>
                        <p class="deskripsi-halaman">Kelola pengaturan form dan proses pengajuan pindah kelas mahasiswa.</p>
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>
                <div v-if="warningFlash" class="alert-gagal" role="alert">{{ warningFlash }}</div>

                <section class="kartu flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                    <p class="flex items-center gap-2 text-sm text-[#31302e] dark:text-foreground">
                        <span class="size-2.5 rounded-full" :class="props.isActive ? 'bg-[#1aae39]' : 'bg-[#a39e98]'" aria-hidden="true" />
                        {{
                            props.isActive ? 'Form pindah kelas sedang dibuka untuk mahasiswa.' : 'Form pindah kelas sedang ditutup untuk mahasiswa.'
                        }}
                    </p>
                    <Link
                        v-if="can('admin.pengaturan-akademik')"
                        :href="`${route('pengaturan-sistem.akademik')}#pindah-kelas`"
                        class="text-sm font-medium text-[#0075de] hover:underline"
                        >Ubah di Pengaturan Sistem →</Link
                    >
                </section>

                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari nama atau NIM mahasiswa" aria-label="Cari" class="pl-9" />
                    </div>
                    <SelectFilter v-model="tahunAkademikId" label="Filter tahun akademik" @change="applyFilters">
                        <option value="all">Semua Tahun Akademik</option>
                        <option v-for="tahun in props.tahunAkademiks" :key="tahun.id" :value="tahun.id">
                            {{ tahun.tahun }} {{ tahun.semester }}
                        </option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.pengajuans.total }}</span> pengajuan
                    </p>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[900px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Mahasiswa</th>
                                    <th>Kelas Asal</th>
                                    <th>Kelas Tujuan</th>
                                    <th>Alasan</th>
                                    <th>Status</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(pengajuan, index) in props.pengajuans.data" :key="pengajuan.id">
                                    <td class="kolom-no">{{ (props.pengajuans.from ?? 1) + index }}</td>
                                    <td>
                                        <span class="block font-medium text-black dark:text-foreground">{{ pengajuan.mahasiswa ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ pengajuan.nim ?? '-' }} · {{ pengajuan.prodi ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <span class="block">{{ pengajuan.kelas_asal ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ pengajuan.kelas_asal_matkul ?? '' }}</span>
                                        <span
                                            v-if="pengajuan.nilai_terisi"
                                            class="mt-1 inline-flex items-center gap-1 rounded-full bg-[#fff3e0] px-2 py-0.5 text-xs font-semibold text-[#dd5b00] dark:bg-amber-950 dark:text-amber-400"
                                        >
                                            <AlertTriangle class="size-3" /> Nilai: {{ pengajuan.nilai }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="block">{{ pengajuan.kelas_tujuan ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ pengajuan.kelas_tujuan_matkul ?? '' }}</span>
                                    </td>
                                    <td class="whitespace-pre-line">{{ pengajuan.alasan }}</td>
                                    <td>
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="statusClass[pengajuan.status] ?? ''"
                                        >
                                            {{ statusLabel[pengajuan.status] ?? pengajuan.status }}
                                        </span>
                                        <span v-if="pengajuan.diproses_oleh" class="mt-1 block text-xs text-[#a39e98]"
                                            >oleh {{ pengajuan.diproses_oleh }}</span
                                        >
                                        <span v-if="pengajuan.diproses_at" class="block text-xs text-[#a39e98]">{{
                                            formatDateTime(pengajuan.diproses_at)
                                        }}</span>
                                        <span v-if="pengajuan.catatan_admin" class="mt-1 block whitespace-pre-line text-xs text-[#615d59]">{{
                                            pengajuan.catatan_admin
                                        }}</span>
                                    </td>
                                    <td class="kolom-aksi">
                                        <div v-if="pengajuan.status === 'pending'" class="aksi-tabel">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon-sm"
                                                title="Setujui"
                                                aria-label="Setujui"
                                                class="text-[#1aae39]"
                                                @click="askApprove(pengajuan)"
                                            >
                                                <Check />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon-sm"
                                                title="Tolak"
                                                aria-label="Tolak"
                                                class="text-[#dd5b00]"
                                                @click="askReject(pengajuan)"
                                            >
                                                <X />
                                            </Button>
                                        </div>
                                        <p v-else class="text-xs text-[#a39e98]">Sudah diproses</p>
                                    </td>
                                </tr>
                                <tr v-if="!props.pengajuans.data.length" class="baris-kosong">
                                    <td colspan="7" class="tabel-kosong">
                                        Belum ada pengajuan. Pengajuan pindah kelas dari mahasiswa akan tampil di sini.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.pengajuans.links" :total="props.pengajuans.data.length" />

                <AlertModal
                    :open="approveTarget !== null"
                    :title="approveWarning ? 'Mahasiswa sudah punya nilai' : 'Setujui pengajuan?'"
                    :description="approveWarning ?? 'Baris KRS mahasiswa pada kelas asal akan dipindahkan ke kelas tujuan.'"
                    confirm-text="Setujui"
                    cancel-text="Batal"
                    :loading="processing"
                    @update:open="(value: boolean) => !value && cancelApprove()"
                    @confirm="confirmApprove"
                    @cancel="cancelApprove"
                />

                <Teleport to="body">
                    <Transition
                        enter-active-class="duration-150 ease-out"
                        enter-from-class="opacity-0"
                        enter-to-class="opacity-100"
                        leave-active-class="duration-100 ease-in"
                        leave-from-class="opacity-100"
                        leave-to-class="opacity-0"
                    >
                        <div v-if="rejectTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
                            <div class="absolute inset-0 bg-black/40 backdrop-blur-[1px]" @click="cancelReject" />
                            <div class="kartu relative w-full max-w-sm p-6 shadow-xl">
                                <h2 class="judul-bagian">Tolak pengajuan?</h2>
                                <p class="mt-2 text-sm leading-5 text-[#615d59] dark:text-muted-foreground">
                                    Data KRS mahasiswa tidak akan diubah. Berikan alasan penolakan untuk mahasiswa.
                                </p>
                                <div class="mt-4 grid gap-2">
                                    <textarea
                                        v-model="rejectForm.catatan_admin"
                                        rows="4"
                                        placeholder="Tuliskan alasan penolakan"
                                        aria-label="Alasan penolakan"
                                        class="isian isian-area"
                                    />
                                    <InputError :message="rejectForm.errors.catatan_admin" />
                                </div>
                                <div class="mt-6 flex justify-end gap-2">
                                    <Button variant="outline" @click="cancelReject">Batal</Button>
                                    <Button variant="destructive" :disabled="rejectForm.processing" @click="confirmReject">Tolak</Button>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </Teleport>
            </div>
        </div>
    </AppLayout>
</template>
