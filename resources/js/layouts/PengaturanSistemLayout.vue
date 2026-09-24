<script setup lang="ts">
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Building2, GraduationCap, Mail, Palette } from 'lucide-vue-next';
import { computed } from 'vue';

// Satu halaman Pengaturan Sistem dengan tab; tiap tab punya alamat sendiri dan hanya tampil bila diizinkan.
const semuaTab = [
    { nama: 'institusi', judul: 'Institusi', izin: 'admin.institusi', ikon: Building2 },
    { nama: 'email', judul: 'Email', izin: 'admin.pengaturan-email', ikon: Mail },
    { nama: 'akademik', judul: 'Akademik', izin: 'admin.pengaturan-akademik', ikon: GraduationCap },
    { nama: 'tampilan', judul: 'Tampilan', izin: 'admin.pengaturan-tampilan', ikon: Palette },
] as const;

const { can } = usePermissions();
const page = usePage<{ flash?: { success?: string; error?: string } }>();
const tab = computed(() => semuaTab.filter((item) => can(item.izin)));
const aktif = computed(() => semuaTab.find((item) => page.url.startsWith(`/pengaturan-sistem/${item.nama}`)));
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Pengaturan Sistem', href: route('pengaturan-sistem.index') },
            ...(aktif ? [{ title: aktif.judul, href: route(`pengaturan-sistem.${aktif.nama}`) }] : []),
        ]"
    >
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-5 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] text-black">Pengaturan Sistem</h1>
                    <p class="text-sm text-[#615d59]">Pengaturan yang berlaku untuk seluruh pengguna aplikasi.</p>
                </div>

                <!-- Di layar sempit, tab bisa digeser ke samping. -->
                <nav class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0" aria-label="Tab pengaturan sistem">
                    <div class="flex w-max gap-1 rounded-lg border border-[#e6e6e6] bg-white p-1 text-sm font-medium">
                        <Link
                            v-for="item in tab"
                            :key="item.nama"
                            :href="route(`pengaturan-sistem.${item.nama}`)"
                            class="flex items-center gap-2 whitespace-nowrap rounded-md px-4 py-2"
                            :class="aktif?.nama === item.nama ? 'bg-[#0075de] text-white' : 'text-[#615d59] hover:bg-[#f6f5f4]'"
                            :aria-current="aktif?.nama === item.nama ? 'page' : undefined"
                        >
                            <component :is="item.ikon" class="size-4" /> {{ item.judul }}
                        </Link>
                    </div>
                </nav>

                <div
                    v-if="page.props.flash?.success"
                    class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]"
                    role="alert"
                >
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <slot />
            </div>
        </div>
    </AppLayout>
</template>
