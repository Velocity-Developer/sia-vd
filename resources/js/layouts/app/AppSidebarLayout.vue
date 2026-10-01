<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { usePermissions } from '@/composables/usePermissions';
import type { BreadcrumbItemType, SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { Construction } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

// Pengingat bagi pengguna yang lolos (admin) selama maintenance aktif, supaya tidak lupa dimatikan.
const page = usePage<SharedData>();
const { can } = usePermissions();
// Dosen/mahasiswa yang tidak terkena (mis. maintenance hanya untuk dosen) tidak perlu melihatnya.
const maintenance = computed(() =>
    page.props.maintenance?.aktif && (page.props.auth.role?.user_type === 'admin' || can('admin.pengaturan-maintenance'))
        ? page.props.maintenance
        : null,
);
const labelUntuk = computed(() => (maintenance.value?.untuk ?? []).map((jenis) => (jenis === 'dosen' ? 'Dosen' : 'Mahasiswa')).join(' & '));
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div
                v-if="maintenance?.aktif"
                class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200 md:px-6"
                role="status"
            >
                <span class="flex items-center gap-2 font-medium"
                    ><Construction class="size-4 shrink-0" /> Mode maintenance aktif untuk {{ labelUntuk }}.</span
                >
                <Link v-if="can('admin.pengaturan-maintenance')" :href="route('pengaturan-sistem.maintenance')" class="underline hover:no-underline">
                    Ubah pengaturan
                </Link>
            </div>
            <slot />
        </AppContent>
    </AppShell>
</template>
