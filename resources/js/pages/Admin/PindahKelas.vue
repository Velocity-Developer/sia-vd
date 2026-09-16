<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { AlertTriangle, Check, X } from 'lucide-vue-next';
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

const props = defineProps<{ isActive: boolean; pengajuans: Pagination }>();

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

/* Toggle switch pengaturan form pindah kelas */
const settingForm = useForm({ is_active: props.isActive });

watch(
    () => props.isActive,
    (value) => {
        settingForm.is_active = value;
    },
);

const toggleSetting = () => {
    settingForm.is_active = !settingForm.is_active;
    settingForm.put(route('admin.pindah-kelas.pengaturan'), {
        preserveScroll: true,
        onError: () => {
            settingForm.is_active = props.isActive;
        },
    });
};

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
watch(() => page.props.flash?.pindah_kelas_warning, (value) => (warningFlash.value = value ?? null), { immediate: true });
</script>

<template>
    <Head title="Pindah Kelas" />
    <AppLayout :breadcrumbs="[{ title: 'Pindah Kelas', href: route('admin.pindah-kelas.index') }]">
        <div class="min-h-full bg-[#f6f5f4] dark:bg-gray-950">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black dark:text-white">Pindah Kelas</h1>
                    <p class="text-sm leading-5 text-[#615d59] dark:text-gray-400">Kelola pengaturan form dan proses pengajuan pindah kelas mahasiswa.</p>
                </div>

                <div v-if="page.props.flash?.success" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39] dark:border-gray-800 dark:bg-gray-900" role="alert">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00] dark:border-gray-800 dark:bg-gray-900" role="alert">
                    {{ page.props.flash.error }}
                </div>
                <div v-if="warningFlash" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00] dark:border-gray-800 dark:bg-gray-900" role="alert">
                    {{ warningFlash }}
                </div>

                <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="space-y-1">
                            <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Pengaturan Form Pindah Kelas</h2>
                            <p class="text-sm leading-5 text-[#615d59] dark:text-gray-400">
                                {{ settingForm.is_active ? 'Form pindah kelas sedang dibuka untuk mahasiswa.' : 'Form pindah kelas sedang ditutup untuk mahasiswa.' }}
                            </p>
                        </div>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="settingForm.is_active"
                            aria-label="Buka atau tutup form pindah kelas"
                            :disabled="settingForm.processing"
                            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full border border-transparent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0075de] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                            :class="settingForm.is_active ? 'bg-[#1aae39]' : 'bg-[#dddddd] dark:bg-gray-700'"
                            @click="toggleSetting"
                        >
                            <span
                                class="inline-block size-5 transform rounded-full bg-white shadow transition-transform"
                                :class="settingForm.is_active ? 'translate-x-[22px]' : 'translate-x-0.5'"
                            />
                        </button>
                    </div>
                    <InputError class="mt-2" :message="settingForm.errors.is_active" />
                </section>

                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-[#e6e6e6] px-6 py-4 dark:border-gray-800">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Daftar Pengajuan</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[900px] text-left">
                            <thead>
                                <tr class="border-b border-[#e6e6e6] bg-[#f6f5f4] dark:border-gray-800 dark:bg-gray-800">
                                    <th class="w-16 px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">No.</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Mahasiswa</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Kelas Asal</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Kelas Tujuan</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Alasan</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Status</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6] dark:divide-gray-800">
                                <tr v-for="(pengajuan, index) in props.pengajuans.data" :key="pengajuan.id" class="transition-colors hover:bg-[#f6f5f4]/60 dark:hover:bg-gray-800">
                                    <td class="px-4 py-3 text-[15px] leading-5 text-[#615d59] dark:text-gray-400">{{ (props.pengajuans.from ?? 1) + index }}</td>
                                    <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e] dark:text-gray-200">
                                        <span class="block font-medium text-black dark:text-white">{{ pengajuan.mahasiswa ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ pengajuan.nim ?? '-' }} · {{ pengajuan.prodi ?? '-' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e] dark:text-gray-200">
                                        <span class="block">{{ pengajuan.kelas_asal ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ pengajuan.kelas_asal_matkul ?? '' }}</span>
                                        <span v-if="pengajuan.nilai_terisi" class="mt-1 inline-flex items-center gap-1 rounded-full bg-[#fff3e0] px-2 py-0.5 text-xs font-semibold text-[#dd5b00] dark:bg-amber-950 dark:text-amber-400">
                                            <AlertTriangle class="size-3" /> Nilai: {{ pengajuan.nilai }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px] leading-5 text-[#31302e] dark:text-gray-200">
                                        <span class="block">{{ pengajuan.kelas_tujuan ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ pengajuan.kelas_tujuan_matkul ?? '' }}</span>
                                    </td>
                                    <td class="whitespace-pre-line px-4 py-3 text-[15px] leading-5 text-[#31302e] dark:text-gray-200">{{ pengajuan.alasan }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass[pengajuan.status] ?? ''">
                                            {{ statusLabel[pengajuan.status] ?? pengajuan.status }}
                                        </span>
                                        <span v-if="pengajuan.diproses_oleh" class="mt-1 block text-xs text-[#a39e98]">oleh {{ pengajuan.diproses_oleh }}</span>
                                        <span v-if="pengajuan.diproses_at" class="block text-xs text-[#a39e98]">{{ formatDateTime(pengajuan.diproses_at) }}</span>
                                        <span v-if="pengajuan.catatan_admin" class="mt-1 block whitespace-pre-line text-xs text-[#615d59] dark:text-gray-400">{{ pengajuan.catatan_admin }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div v-if="pengajuan.status === 'pending'" class="flex justify-end gap-1.5">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                title="Setujui"
                                                aria-label="Setujui"
                                                class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#1aae39] hover:bg-[#f6f5f4] dark:border-gray-700 dark:bg-gray-900"
                                                @click="askApprove(pengajuan)"
                                            >
                                                <Check class="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                title="Tolak"
                                                aria-label="Tolak"
                                                class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#dd5b00] hover:bg-[#f6f5f4] dark:border-gray-700 dark:bg-gray-900"
                                                @click="askReject(pengajuan)"
                                            >
                                                <X class="size-4" />
                                            </Button>
                                        </div>
                                        <p v-else class="text-right text-xs text-[#a39e98]">Sudah diproses</p>
                                    </td>
                                </tr>
                                <tr v-if="!props.pengajuans.data.length">
                                    <td colspan="7" class="px-4 py-16 text-center">
                                        <div class="mx-auto max-w-sm rounded-xl border border-dashed border-[#e6e6e6] bg-[#f6f5f4] px-6 py-8 dark:border-gray-700 dark:bg-gray-800">
                                            <p class="text-sm font-medium text-black dark:text-white">Belum ada pengajuan</p>
                                            <p class="mt-1 text-sm leading-5 text-[#615d59] dark:text-gray-400">Pengajuan pindah kelas dari mahasiswa akan tampil di sini.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <nav v-if="props.pengajuans.links?.length" class="flex flex-wrap items-center gap-2" aria-label="Pagination">
                    <Link
                        v-for="link in props.pengajuans.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        preserve-scroll
                        preserve-state
                        class="rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors"
                        :class="
                            link.active
                                ? 'border-[#0075de] bg-[#0075de] text-white'
                                : link.url
                                  ? 'border-[#e6e6e6] bg-white text-black hover:bg-[#f6f5f4] dark:border-gray-700 dark:bg-gray-900 dark:text-white'
                                  : 'pointer-events-none border-[#e6e6e6] bg-white opacity-40 dark:border-gray-700 dark:bg-gray-900'
                        "
                        v-html="link.label"
                    />
                </nav>

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
                            <div class="relative w-full max-w-sm rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_8px_28px_rgba(0,0,0,0.08),0_23px_52px_rgba(0,0,0,0.08)] dark:border-gray-800 dark:bg-gray-900">
                                <h2 class="text-[15px] font-semibold leading-5 text-black dark:text-white">Tolak pengajuan?</h2>
                                <p class="mt-2 text-sm leading-5 text-[#615d59] dark:text-gray-400">Data KRS mahasiswa tidak akan diubah. Berikan alasan penolakan untuk mahasiswa.</p>
                                <div class="mt-4 grid gap-2">
                                    <textarea
                                        v-model="rejectForm.catatan_admin"
                                        rows="4"
                                        placeholder="Tuliskan alasan penolakan"
                                        class="w-full rounded-[4px] border border-[#dddddd] bg-white px-3 py-2 text-[15px] text-black placeholder:text-[#a39e98] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de] dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                    />
                                    <InputError :message="rejectForm.errors.catatan_admin" />
                                </div>
                                <div class="mt-6 flex justify-end gap-2">
                                    <Button variant="outline" class="rounded-full border-[#e6e6e6] bg-white text-black hover:bg-[#f6f5f4] dark:border-gray-700 dark:bg-gray-900 dark:text-white" @click="cancelReject">
                                        Batal
                                    </Button>
                                    <Button
                                        class="rounded-full bg-[#dd5b00] px-6 text-white hover:bg-[#b44a00]"
                                        :disabled="rejectForm.processing"
                                        @click="confirmReject"
                                    >
                                        Tolak
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </Teleport>
            </div>
        </div>
    </AppLayout>
</template>
