<script setup lang="ts">
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { type NavEntry, type NavGroup, type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        entries: NavEntry[];
        label?: string;
    }>(),
    { label: 'Platform' },
);

const page = usePage<SharedData>();
const { state, isMobile } = useSidebar();

// Saat sidebar diciutkan jadi ikon, submenu tidak muat; seksi dibuka sebagai dropdown melayang.
const modeIkon = computed(() => state.value === 'collapsed' && !isMobile.value);

const adalahSeksi = (entry: NavEntry): entry is NavGroup => 'items' in entry;

const currentPath = () => page.url.split('?')[0].replace(/\/$/, '') || '/';
const normalizedPath = (href: string) => new URL(href, window.location.origin).pathname.replace(/\/$/, '') || '/';
const isActive = (href: string, allowChildren = true) => {
    const path = currentPath();
    const target = normalizedPath(href);

    return allowChildren ? path === target || path.startsWith(`${target}/`) : path === target;
};
const isItemActive = (item: NavItem) => {
    const target = normalizedPath(item.href);
    // Beranda peran (mis. /admin) cocok dengan semua halaman di bawahnya, jadi hanya dicocokkan persis.
    const isRoleHome = target.split('/').filter(Boolean).length === 1;

    return isActive(item.href, !isRoleHome);
};
const seksiAktif = (seksi: NavGroup) => seksi.items.some((item) => isItemActive(item));

// Seksi yang dibuka/ditutup pengguna diingat per peramban; seksi yang memuat halaman aktif
// tetap terbuka sendiri agar posisi pengguna selalu terlihat.
const KUNCI_SIMPANAN = 'sia-vd.seksi-menu';
const bacaSimpanan = (): Record<string, boolean> => {
    try {
        const isi = JSON.parse(localStorage.getItem(KUNCI_SIMPANAN) ?? '{}');

        return typeof isi === 'object' && isi !== null ? isi : {};
    } catch {
        return {};
    }
};

const pilihanSeksi = ref<Record<string, boolean>>(bacaSimpanan());

const seksiTerbuka = (seksi: NavGroup) => seksiAktif(seksi) || (pilihanSeksi.value[seksi.title] ?? false);

const aturSeksi = (seksi: NavGroup, terbuka: boolean) => {
    pilihanSeksi.value = { ...pilihanSeksi.value, [seksi.title]: terbuka };

    try {
        localStorage.setItem(KUNCI_SIMPANAN, JSON.stringify(pilihanSeksi.value));
    } catch {
        // Penyimpanan peramban bisa ditolak (mode privat); cukup abaikan.
    }
};
</script>

<template>
    <SidebarGroup class="px-0 py-0">
        <SidebarGroupLabel class="px-2 text-[11px]">{{ props.label }}</SidebarGroupLabel>
        <SidebarMenu class="gap-0.5">
            <template v-for="entry in props.entries" :key="entry.title">
                <SidebarMenuItem v-if="!adalahSeksi(entry)">
                    <SidebarMenuButton as-child :is-active="isItemActive(entry)" :tooltip="entry.title">
                        <Link :href="entry.href">
                            <component
                                :is="entry.icon"
                                v-if="entry.icon"
                                class="text-muted-foreground transition-colors group-data-[active=true]/menu-button:text-sidebar-primary"
                            />
                            <span>{{ entry.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>

                <SidebarMenuItem v-else-if="modeIkon">
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <SidebarMenuButton :is-active="seksiAktif(entry)" :tooltip="entry.title">
                                <component
                                    :is="entry.icon"
                                    v-if="entry.icon"
                                    class="text-muted-foreground transition-colors group-data-[active=true]/menu-button:text-sidebar-primary"
                                />
                                <span>{{ entry.title }}</span>
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent side="right" align="start" class="min-w-52">
                            <DropdownMenuLabel class="text-[11px] uppercase tracking-[0.08em] text-[#a39e98]">{{ entry.title }}</DropdownMenuLabel>
                            <DropdownMenuItem v-for="sub in entry.items" :key="sub.href" as-child>
                                <Link :href="sub.href" class="flex w-full items-center gap-2" :data-active="isItemActive(sub)">
                                    <component :is="sub.icon" v-if="sub.icon" class="size-4 text-muted-foreground" />
                                    <span>{{ sub.title }}</span>
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>

                <Collapsible
                    v-else
                    as-child
                    class="group/seksi"
                    :open="seksiTerbuka(entry)"
                    @update:open="(nilai: boolean) => aturSeksi(entry, nilai)"
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton :is-active="seksiAktif(entry) && !seksiTerbuka(entry)">
                                <component
                                    :is="entry.icon"
                                    v-if="entry.icon"
                                    class="text-muted-foreground transition-colors group-data-[active=true]/menu-button:text-sidebar-primary"
                                />
                                <span>{{ entry.title }}</span>
                                <ChevronRight
                                    class="ml-auto size-4 text-muted-foreground transition-transform duration-200 group-data-[state=open]/seksi:rotate-90"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub class="ml-3.5 border-l border-[#e6e6e6] pl-2">
                                <SidebarMenuSubItem v-for="sub in entry.items" :key="sub.href">
                                    <SidebarMenuSubButton as-child :is-active="isItemActive(sub)" class="rounded-[5px] text-[14px] leading-5">
                                        <Link :href="sub.href">
                                            <component :is="sub.icon" v-if="sub.icon" class="text-muted-foreground" />
                                            <span>{{ sub.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
