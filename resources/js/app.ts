/// <reference types="vite/client" />
import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initializeTheme } from './composables/useAppearance';

// Extend ImportMeta interface for Vite...

// Nama di tab browser dan favicon diatur di Pengaturan Sistem → Tampilan (prop bersama "tampilan").
let namaAplikasi = import.meta.env.VITE_APP_NAME || 'Laravel';
type PropsTampilan = { tampilan?: { nama_aplikasi?: string; favicon_url?: string } };
const terapkanTampilan = (props: PropsTampilan) => {
    const baru = props.tampilan?.nama_aplikasi;
    if (baru && baru !== namaAplikasi) {
        // Event navigate datang sesudah judul halaman dirender, jadi judul yang sedang tampil ikut diganti.
        if (document.title.endsWith(namaAplikasi)) document.title = document.title.slice(0, -namaAplikasi.length) + baru;
        namaAplikasi = baru;
    }
    const ikon = document.querySelector<HTMLLinkElement>('link[rel="icon"]');
    if (ikon && props.tampilan?.favicon_url && ikon.getAttribute('href') !== props.tampilan.favicon_url) ikon.href = props.tampilan.favicon_url;
};
router.on('navigate', (event) => terapkanTampilan(event.detail.page.props as PropsTampilan));
// Simpan form di halaman yang sama tidak memicu navigate, jadi hasil kunjungan yang berhasil juga diperiksa.
router.on('success', (event) => terapkanTampilan(event.detail.page.props as PropsTampilan));

createInertiaApp({
    title: (title) => (title ? `${title} - ${namaAplikasi}` : namaAplikasi),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        terapkanTampilan(props.initialPage.props as PropsTampilan);
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
