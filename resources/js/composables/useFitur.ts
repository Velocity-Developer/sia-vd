import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';

/**
 * Fitur per klien yang sedang aktif (config/client.php), dibagikan dari server.
 */
export function useFitur() {
    const page = usePage<SharedData>();

    const aktif = (nama: keyof SharedData['fitur']): boolean => page.props.fitur?.[nama] ?? false;

    return { aktif };
}
