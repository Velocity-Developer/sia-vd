<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ChevronRight, GraduationCap, Landmark, Users } from 'lucide-vue-next';
import type { Component } from 'vue';

type Pilihan = { jenis: string; label: string; keterangan: string; href: string };

const props = defineProps<{ pilihan: Pilihan[] }>();

const ikon: Record<string, Component> = { prodi: Landmark, dosen: GraduationCap, mahasiswa: Users };
</script>

<template>
    <Head title="Create User" />
    <AppLayout :breadcrumbs="[{ title: 'Create User', href: route('admin.pengguna.buat') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Create User</h1>
                        <p class="deskripsi-halaman">Pilih jenis akun yang akan dibuat. Semua akun bisa dilihat kembali di Data Pengguna.</p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <Link
                        v-for="item in props.pilihan"
                        :key="item.jenis"
                        :href="item.href"
                        class="kartu group flex items-center gap-4 p-5 transition-colors hover:border-[#0075de] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0075de]/30"
                    >
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-[#f2f9ff] text-[#0075de]">
                            <component :is="ikon[item.jenis] ?? Users" class="size-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold text-black dark:text-foreground">{{ item.label }}</span>
                            <span class="teks-bantu block">{{ item.keterangan }}</span>
                        </span>
                        <ChevronRight class="size-5 shrink-0 text-[#a39e98] transition-transform group-hover:translate-x-0.5" />
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
