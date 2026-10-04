import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Hak akses user yang sedang login, dibagikan dari server (role & permission di database).
 */
export function usePermissions() {
    const page = usePage<SharedData>();
    const permissions = computed(() => page.props.auth?.permissions ?? []);

    const can = (permission?: string | null): boolean => !permission || permissions.value.includes(permission);
    // Akun Prodi: data dibatasi ke prodinya; data global (Tahun Akademik, Ruang, Predikat) hanya bisa dilihat.
    const prodi = computed(() => page.props.auth?.prodi ?? null);

    return { permissions, can, prodi };
}
