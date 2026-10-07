<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useInitials } from '@/composables/useInitials';
import { type SharedData, type User } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed } from 'vue';
import UserMenuContent from './UserMenuContent.vue';

// Profil pengguna di pojok kanan header, sejajar breadcrumb; di layar sempit hanya avatar.
const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user as User);
const namaRole = computed(() => page.props.auth.role?.name ?? null);
const { getInitials } = useInitials();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="flex items-center gap-2.5 rounded-lg py-1 pl-1 pr-1 outline-none transition-colors hover:bg-[#f6f5f4] focus-visible:ring-2 focus-visible:ring-ring data-[state=open]:bg-[#f6f5f4] dark:data-[state=open]:bg-accent sm:pr-2"
            :aria-label="`Menu akun ${user.name}`"
        >
            <Avatar class="size-8 overflow-hidden rounded-lg">
                <AvatarImage v-if="user.avatar" :src="user.avatar" :alt="user.name" />
                <AvatarFallback class="rounded-lg text-xs text-black dark:text-white">{{ getInitials(user.name) }}</AvatarFallback>
            </Avatar>
            <span class="hidden max-w-48 flex-col text-left leading-tight sm:flex">
                <span class="truncate text-sm font-medium">{{ user.name }}</span>
                <span v-if="namaRole" class="truncate text-xs text-[#a39e98] dark:text-muted-foreground">{{ namaRole }}</span>
            </span>
            <ChevronDown class="hidden size-4 text-[#a39e98] sm:block" />
        </DropdownMenuTrigger>
        <DropdownMenuContent class="min-w-60 rounded-lg" side="bottom" align="end" :side-offset="6">
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
