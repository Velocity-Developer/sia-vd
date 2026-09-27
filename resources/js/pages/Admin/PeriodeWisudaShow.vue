<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatTanggal } from '@/lib/presensi';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Download, FileText } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Peserta = {
    id: number;
    nama: string | null;
    nim: string | null;
    prodi: string | null;
    judul: string | null;
    ukuran_toga: string | null;
    pengajuan_id: number;
    status_mahasiswa: string | null;
    nomor_skl: string | null;
    skl_terbit_at: string | null;
    ipk: number | null;
    predikat: string | null;
};

const props = defineProps<{
    periode: { id: number; nama: string; tanggal_acara: string; tempat: string | null; batas_daftar: string; kuota: number | null; dibuka: boolean };
    peserta: Peserta[];
}>();
const page = usePage<{ flash?: { success?: string; error?: string } }>();

const belumSkl = computed(() => props.peserta.filter((p) => !p.nomor_skl).length);

// SKL mengubah status mahasiswa menjadi Lulus, jadi selalu dikonfirmasi.
const sklItem = ref<Peserta | 'semua' | null>(null);
const terbitkan = () => {
    const item = sklItem.value;
    if (!item) return;
    const url = item === 'semua' ? route('admin.periode-wisuda.skl-massal', props.periode.id) : route('admin.wisuda.skl', item.id);
    router.post(url, {}, { preserveScroll: true, onFinish: () => (sklItem.value = null) });
};

const th = 'px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]';
</script>

<template>
    <Head :title="`Wisuda ${props.periode.nama}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Periode Wisuda', href: route('admin.periode-wisuda.index') },
            { title: props.periode.nama, href: route('admin.periode-wisuda.show', props.periode.id) },
        ]"
    >
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] text-black">Daftar Mahasiswa Wisuda</h1>
                        <p class="text-sm text-[#615d59]">
                            {{ props.periode.nama }} · {{ formatTanggal(props.periode.tanggal_acara)
                            }}<span v-if="props.periode.tempat"> · {{ props.periode.tempat }}</span> · {{ props.peserta.length
                            }}{{ props.periode.kuota ? ` / ${props.periode.kuota}` : '' }} peserta
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a :href="route('admin.periode-wisuda.cetak', props.periode.id)"
                            ><Button variant="outline" class="rounded-full bg-white"><Download class="size-4" /> Cetak Daftar</Button></a
                        >
                        <Button :disabled="!belumSkl" class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]" @click="sklItem = 'semua'"
                            >Generate SKL Semua ({{ belumSkl }})</Button
                        >
                    </div>
                </div>

                <div v-if="page.props.flash?.success" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]">
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]">
                    {{ page.props.flash.error }}
                </div>

                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="relative overflow-x-auto">
                        <table class="w-full min-w-[960px] text-left">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th :class="th">Mahasiswa</th>
                                    <th :class="th">Tugas Akhir</th>
                                    <th :class="th">Toga</th>
                                    <th :class="th">SKL</th>
                                    <th :class="[th, 'text-right']">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="p in props.peserta" :key="p.id" class="align-top hover:bg-[#f6f5f4]/60">
                                    <td class="px-4 py-3 text-[15px] text-black">
                                        <span class="block font-medium">{{ p.nama }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ p.nim }} · {{ p.prodi }}</span>
                                        <span class="block text-xs text-[#a39e98]">Status: {{ p.status_mahasiswa }}</span>
                                    </td>
                                    <td class="max-w-[360px] px-4 py-3 text-sm text-[#31302e]">{{ p.judul }}</td>
                                    <td class="px-4 py-3 text-sm">{{ p.ukuran_toga }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <template v-if="p.nomor_skl">
                                            <a
                                                :href="route('berkas.skl', p.id)"
                                                target="_blank"
                                                rel="noopener"
                                                class="inline-flex items-center gap-1 font-medium text-[#0075de] hover:underline"
                                                ><FileText class="size-3.5" /> {{ p.nomor_skl }}</a
                                            >
                                            <span class="block text-xs text-[#a39e98]">IPK {{ p.ipk?.toFixed(2) }} · {{ p.predikat }}</span>
                                        </template>
                                        <span v-else class="text-xs text-[#a39e98]">Belum terbit</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <Button
                                            v-if="!p.nomor_skl"
                                            class="h-8 rounded-lg bg-[#0075de] px-3 text-sm text-white hover:bg-[#005bab]"
                                            @click="sklItem = p"
                                            >Generate SKL</Button
                                        >
                                    </td>
                                </tr>
                                <tr v-if="!props.peserta.length">
                                    <td colspan="5" class="px-4 py-14 text-center text-sm text-[#615d59]">
                                        Belum ada peserta. Setujui pendaftaran di
                                        <Link
                                            :href="route('admin.pengajuan-akademik.index', { jenis: 'wisuda' })"
                                            class="text-[#0075de] hover:underline"
                                            >TA & Wisuda → Wisuda</Link
                                        >.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <AlertModal
            :open="!!sklItem"
            title="Generate SKL?"
            :description="
                sklItem === 'semua'
                    ? `SKL diterbitkan untuk ${belumSkl} peserta dan status mereka menjadi Lulus.`
                    : sklItem
                      ? `SKL ${sklItem.nama} diterbitkan dan statusnya menjadi Lulus. IPK dan predikat dibekukan saat ini.`
                      : ''
            "
            confirm-text="Generate"
            cancel-text="Batal"
            @update:open="!$event && (sklItem = null)"
            @confirm="terbitkan"
            @cancel="sklItem = null"
        />
    </AppLayout>
</template>
