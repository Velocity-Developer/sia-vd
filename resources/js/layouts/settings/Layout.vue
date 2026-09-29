<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { KeyRound, UserRound } from 'lucide-vue-next';

const navItems = [
    { title: 'Profil', href: '/settings/profile', ikon: UserRound },
    { title: 'Kata Sandi', href: '/settings/password', ikon: KeyRound },
];

// Institusi, Email, dan pengaturan lain untuk seluruh sistem ada di menu Pengaturan Sistem.
const currentPath = window.location.pathname;
</script>

<template>
    <!-- Kerangka, judul, dan gaya tab sama dengan PengaturanSistemLayout.vue; halaman tab cukup mengisi kartu. -->
    <div class="halaman">
        <div class="konten">
            <div class="kepala-halaman">
                <div>
                    <h1 class="judul-halaman">Pengaturan Profil</h1>
                    <p class="deskripsi-halaman">Kelola profil dan kata sandi akun Anda.</p>
                </div>
            </div>

            <nav class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Tab pengaturan profil">
                <div class="flex w-max gap-1 rounded-lg border border-[#e6e6e6] bg-white p-1 text-sm font-medium dark:border-border dark:bg-card">
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        class="flex h-9 items-center gap-2 whitespace-nowrap rounded-md px-4"
                        :class="
                            currentPath === item.href
                                ? 'bg-[#0075de] text-white'
                                : 'text-[#615d59] hover:bg-[#f6f5f4] dark:text-muted-foreground dark:hover:bg-accent'
                        "
                        :aria-current="currentPath === item.href ? 'page' : undefined"
                    >
                        <component :is="item.ikon" class="size-4" /> {{ item.title }}
                    </Link>
                </div>
            </nav>

            <slot />
        </div>
    </div>
</template>
