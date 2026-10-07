<script setup lang="ts">
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { BookOpen, ClipboardList, GraduationCap } from 'lucide-vue-next';
import { computed } from 'vue';

// Judul, teks, dan daftar fitur diatur di Pengaturan Sistem → Tampilan. Dipakai di panel merek halaman auth
// dan di kotak deskripsi halaman depan; teks putih, jadi pemanggil menyediakan latar gelap/berwarna.
const page = usePage<SharedData>();
const tampilan = computed(() => page.props.tampilan);

const sorotan = [
    { icon: ClipboardList, judul: 'Rencana Studi', teks: 'Isi KRS dan pantau batas SKS tiap semester.' },
    { icon: BookOpen, judul: 'Materi & Tugas', teks: 'Materi kuliah, tugas, dan quiz dalam satu tempat.' },
    {
        icon: GraduationCap,
        judul: 'Hasil Studi',
        teks: page.props.fitur?.keuangan ? 'KHS, transkrip nilai, dan informasi biaya kuliah.' : 'KHS dan transkrip nilai.',
    },
];
</script>

<template>
    <div>
        <h2 class="text-[28px] font-bold leading-[1.15] tracking-[-0.6px] sm:text-[32px]">
            {{ tampilan?.login_judul ?? 'Sistem Informasi Akademik' }}
        </h2>
        <p class="mt-3 whitespace-pre-line text-base leading-6 text-white/80">
            {{ tampilan?.login_teks ?? 'Satu akun untuk rencana studi, perkuliahan, nilai, dan administrasi Anda.' }}
        </p>

        <ul v-if="tampilan?.login_sorotan ?? true" class="mt-8 space-y-4">
            <li v-for="item in sorotan" :key="item.judul" class="flex gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white/15">
                    <component :is="item.icon" class="size-[18px]" />
                </div>
                <div>
                    <p class="text-sm font-semibold">{{ item.judul }}</p>
                    <p class="text-sm leading-5 text-white/70">{{ item.teks }}</p>
                </div>
            </li>
        </ul>
    </div>
</template>
