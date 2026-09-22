<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem, SidebarMenuSub, SidebarMenuSubButton, SidebarMenuSubItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        items: NavItem[];
        label?: string;
    }>(),
    { label: 'Platform' },
);

const page = usePage<SharedData>();

const currentPath = () => page.url.split('?')[0].replace(/\/$/, '') || '/';
const normalizedPath = (href: string) => new URL(href, window.location.origin).pathname.replace(/\/$/, '') || '/';
const isActive = (href: string, allowChildren = true) => {
    const path = currentPath();
    const target = normalizedPath(href);

    return allowChildren ? path === target || path.startsWith(`${target}/`) : path === target;
};
const isItemActive = (item: NavItem) => {
    const target = normalizedPath(item.href);
    const isRoleHome = target.split('/').filter(Boolean).length === 1;

    return Boolean(item.items?.some((subItem) => isActive(subItem.href)) || isActive(item.href, !isRoleHome));
};
</script>

<template>
    <SidebarGroup class="px-0 py-0">
        <SidebarGroupLabel class="px-2 text-[11px]">{{ label }}</SidebarGroupLabel>
        <SidebarMenu class="gap-0.5">
            <SidebarMenuItem v-for="item in items" :key="item.href + item.title">
                <SidebarMenuButton as-child :is-active="isItemActive(item)">
                    <Link :href="item.href">
                        <component v-if="item.icon" :is="item.icon" class="text-muted-foreground transition-colors group-data-[active=true]/menu-button:text-sidebar-primary" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuSub v-if="item.items" class="ml-3.5 border-l border-[#e6e6e6] pl-2">
                    <SidebarMenuSubItem v-for="subItem in item.items" :key="subItem.href">
                        <SidebarMenuSubButton as-child :is-active="isActive(subItem.href)" class="rounded-[5px] text-[14px] leading-5">
                            <Link :href="subItem.href"><span>{{ subItem.title }}</span></Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
