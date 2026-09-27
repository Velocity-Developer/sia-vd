<script setup lang="ts">
import { formatTanggal } from '@/lib/presensi';
import type { JadwalPendadaran } from '@/lib/tugasAkhir';
import { FileText } from 'lucide-vue-next';

defineProps<{ jadwal: JadwalPendadaran }>();
</script>

<template>
    <dl class="grid gap-4 text-sm sm:grid-cols-3">
        <div>
            <dt class="text-xs uppercase tracking-[0.04em] text-[#a39e98]">Waktu</dt>
            <dd class="mt-1 text-black">{{ formatTanggal(jadwal.tanggal) }}</dd>
            <dd class="text-black">{{ jadwal.jam_mulai }}–{{ jadwal.jam_akhir }} WIB</dd>
            <dd v-if="jadwal.nomor_surat" class="mt-2">
                <a
                    :href="route('berkas.surat-pendadaran', jadwal.id)"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-1 text-sm font-medium text-[#0075de] hover:underline"
                    ><FileText class="size-4" /> Surat pendadaran</a
                >
                <span class="block text-xs text-[#a39e98]">No. {{ jadwal.nomor_surat }}</span>
            </dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-[0.04em] text-[#a39e98]">Ruang</dt>
            <dd class="mt-1 text-black">{{ jadwal.ruang ?? '-' }}</dd>
        </div>
        <div>
            <dt class="text-xs uppercase tracking-[0.04em] text-[#a39e98]">Penguji</dt>
            <dd v-for="p in jadwal.penguji" :key="p.peran" class="mt-1 text-black">
                {{ p.nama }} <span class="text-xs text-[#a39e98]">· {{ p.peran }}</span>
            </dd>
        </div>
    </dl>
</template>
