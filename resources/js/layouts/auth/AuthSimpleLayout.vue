<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, ClipboardList, GraduationCap } from 'lucide-vue-next';
import { computed } from 'vue';

defineProps<{
    title?: string;
    description?: string;
}>();

const page = usePage<SharedData>();
const institusi = computed(() => page.props.institusi);
// Judul, teks, gambar, dan daftar fitur panel merek diatur di Pengaturan Sistem → Tampilan.
const tampilan = computed(() => page.props.tampilan);
const tahun = new Date().getFullYear();

// Ditulis di panel merek supaya pengguna tahu sistem ini untuk apa sebelum masuk.
const sorotan = [
    { icon: ClipboardList, judul: 'Rencana Studi', teks: 'Isi KRS dan pantau batas SKS tiap semester.' },
    { icon: BookOpen, judul: 'Materi & Tugas', teks: 'Materi kuliah, tugas, dan quiz dalam satu tempat.' },
    { icon: GraduationCap, judul: 'Hasil Studi', teks: 'KHS, transkrip nilai, dan informasi biaya kuliah.' },
];
</script>

<template>
    <div class="min-h-svh bg-[#f6f5f4] lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,520px)]">
        <!-- Panel merek hanya tampil di layar lebar; di HP identitas cukup lewat kepala kartu. -->
        <aside
            class="relative hidden overflow-hidden bg-[#0075de] bg-cover bg-center p-10 text-white lg:flex lg:flex-col lg:justify-between xl:p-14"
            :style="tampilan?.login_gambar_url ? { backgroundImage: `url(${tampilan.login_gambar_url})` } : undefined"
        >
            <!-- Lapisan warna di atas gambar latar agar teks putih tetap terbaca. -->
            <div v-if="tampilan?.login_gambar_url" class="pointer-events-none absolute inset-0 bg-[#0075de]/80" aria-hidden="true" />
            <div class="pointer-events-none absolute -right-24 -top-24 size-[420px] rounded-full bg-white/10" aria-hidden="true" />
            <div class="pointer-events-none absolute -bottom-32 -left-20 size-[360px] rounded-full bg-black/10" aria-hidden="true" />

            <Link :href="route('home')" class="relative flex items-center gap-3">
                <div
                    class="flex size-11 items-center justify-center overflow-hidden rounded-xl"
                    :class="institusi?.logo_url ? 'bg-white' : 'bg-white/15'"
                >
                    <img v-if="institusi?.logo_url" :src="institusi.logo_url" :alt="institusi.nama_pt" class="size-full object-contain p-1.5" />
                    <AppLogoIcon v-else class="size-6 fill-current text-white" />
                </div>
                <div class="leading-tight">
                    <p class="text-[15px] font-semibold">{{ institusi?.nama_pt ?? page.props.name }}</p>
                    <p v-if="institusi?.singkatan" class="text-[11px] font-medium uppercase tracking-[0.12em] text-white/70">
                        {{ institusi.singkatan }}
                    </p>
                </div>
            </Link>

            <div class="relative max-w-[420px]">
                <h2 class="text-[32px] font-bold leading-[1.15] tracking-[-0.8px]">{{ tampilan?.login_judul ?? 'Sistem Informasi Akademik' }}</h2>
                <p class="mt-3 whitespace-pre-line text-[15px] leading-6 text-white/80">
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

            <p class="relative text-xs text-white/60">© {{ tahun }} {{ institusi?.nama_pt ?? page.props.name }}</p>
        </aside>

        <main class="flex min-h-svh flex-col justify-center px-4 py-10 sm:px-8 lg:min-h-0 lg:px-12">
            <div class="mx-auto w-full max-w-[400px]">
                <Link :href="route('home')" class="mb-6 flex items-center gap-3 lg:hidden">
                    <div
                        class="flex size-11 items-center justify-center overflow-hidden rounded-xl"
                        :class="institusi?.logo_url ? 'border border-[#e6e6e6] bg-white' : 'bg-[#0075de]'"
                    >
                        <img v-if="institusi?.logo_url" :src="institusi.logo_url" :alt="institusi.nama_pt" class="size-full object-contain p-1.5" />
                        <AppLogoIcon v-else class="size-6 fill-current text-white" />
                    </div>
                    <div class="leading-tight">
                        <p class="text-[15px] font-semibold text-black">{{ institusi?.nama_pt ?? page.props.name }}</p>
                        <p v-if="institusi?.singkatan" class="text-[11px] font-medium uppercase tracking-[0.1em] text-[#a39e98]">
                            {{ institusi.singkatan }}
                        </p>
                    </div>
                </Link>

                <div class="rounded-2xl border border-[#e6e6e6] bg-white p-6 shadow-sm sm:p-8">
                    <div class="mb-6 space-y-1.5">
                        <h1 class="text-[24px] font-bold leading-8 tracking-[-0.5px] text-black">{{ title }}</h1>
                        <p v-if="description" class="text-sm leading-5 text-[#615d59]">{{ description }}</p>
                    </div>
                    <slot />
                </div>

                <p class="mt-6 text-center text-xs text-[#a39e98] lg:hidden">© {{ tahun }} {{ institusi?.nama_pt ?? page.props.name }}</p>
            </div>
        </main>
    </div>
</template>
