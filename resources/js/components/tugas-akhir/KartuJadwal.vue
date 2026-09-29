<script setup lang="ts">
import { formatTanggal } from '@/lib/presensi';
import type { JadwalPendadaran } from '@/lib/tugasAkhir';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { FileText } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{ jadwal: JadwalPendadaran }>();

const page = usePage<SharedData>();
const zona = computed(() => page.props.institusi?.zona_singkatan ?? 'WIB');
</script>

<template>
    <dl class="grid gap-4 text-sm sm:grid-cols-3">
        <div>
            <dt class="teks-bantu">Waktu</dt>
            <dd class="mt-1 text-black dark:text-foreground">{{ formatTanggal(jadwal.tanggal) }}</dd>
            <dd class="text-black dark:text-foreground">{{ jadwal.jam_mulai }}–{{ jadwal.jam_akhir }} {{ zona }}</dd>
            <dd v-if="jadwal.nomor_surat" class="mt-2">
                <a
                    :href="route('berkas.surat-pendadaran', jadwal.id)"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-1 text-sm font-medium text-[#0075de] hover:underline"
                    ><FileText class="size-4" /> Surat pendadaran</a
                >
                <span class="teks-bantu block">No. {{ jadwal.nomor_surat }}</span>
            </dd>
        </div>
        <div>
            <dt class="teks-bantu">Ruang</dt>
            <dd class="mt-1 text-black dark:text-foreground">{{ jadwal.ruang ?? '-' }}</dd>
        </div>
        <div>
            <dt class="teks-bantu">Penguji</dt>
            <dd v-for="p in jadwal.penguji" :key="p.peran" class="mt-1 text-black dark:text-foreground">
                {{ p.nama }} <span class="teks-bantu">· {{ p.peran }}</span>
            </dd>
        </div>
    </dl>
</template>
