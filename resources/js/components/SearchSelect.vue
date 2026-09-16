<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { ChevronDown } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    id: string;
    options?: { id: number; name: string }[];
    groups?: { label: string; options: { id: number; name: string }[] }[];
    modelValue: number | string | null;
    placeholder?: string;
    searchPlaceholder?: string;
    required?: boolean;
}>();

const emit = defineEmits<{ (e: 'update:modelValue', value: number): void }>();

const open = ref(false);
const search = ref('');
const rootRef = ref<HTMLElement | null>(null);

const allOptions = computed(() => (props.groups ? props.groups.flatMap((g) => g.options) : (props.options ?? [])));
const filtered = computed(() => (props.groups ? [] : (props.options ?? []).filter((option) => option.name.toLowerCase().includes(search.value.toLowerCase()))));
const filteredGroups = computed(() => {
    if (!props.groups) return [];
    const q = search.value.toLowerCase();
    return props.groups
        .map((g) => ({ label: g.label, options: g.options.filter((option) => option.name.toLowerCase().includes(q)) }))
        .filter((g) => g.options.length > 0);
});
const selected = computed(() => allOptions.value.find((option) => option.id === Number(props.modelValue)));

const toggle = () => {
    open.value = !open.value;
    if (open.value) search.value = '';
};

const select = (id: number) => {
    emit('update:modelValue', id);
    open.value = false;
    search.value = '';
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Escape') {
        open.value = false;
    }
};

const onClickOutside = (event: MouseEvent) => {
    if (!open.value || !rootRef.value) return;
    if (!rootRef.value.contains(event.target as Node)) {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));
</script>

<template>
    <div ref="rootRef" class="relative">
        <button
            :id="id"
            type="button"
            class="flex h-10 w-full items-center justify-between rounded-[4px] border border-[#dddddd] bg-white px-3 text-left text-[15px] text-black dark:border-border dark:bg-card dark:text-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]"
            role="combobox"
            :aria-expanded="open"
            :aria-controls="`${id}-options`"
            @click="toggle"
            @keydown="handleKeydown"
        >
            <span :class="{ 'text-[#a39e98]': !selected }">{{ selected?.name ?? placeholder ?? 'Pilih' }}</span>
            <ChevronDown class="size-4 shrink-0 text-[#a39e98] transition-transform" :class="open ? 'rotate-180' : ''" />
        </button>
        <div v-if="open" :id="`${id}-options`" class="absolute z-10 mt-1 w-full rounded-xl border border-[#e6e6e6] bg-white p-2 text-black shadow-[0_4px_18px_rgba(0,0,0,0.04),0_23px_52px_rgba(0,0,0,0.05)] dark:border-border dark:bg-popover dark:text-popover-foreground" role="listbox">
            <Input
                v-model="search"
                :placeholder="searchPlaceholder ?? 'Cari'"
                :aria-label="searchPlaceholder ?? 'Cari'"
                class="h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px] placeholder:text-[#a39e98] dark:border-border dark:bg-background dark:text-foreground dark:placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-[#0075de] focus-visible:ring-offset-0"
                autofocus
            />
            <div class="mt-1 max-h-48 overflow-y-auto">
                <template v-if="props.groups">
                    <template v-for="group in filteredGroups" :key="group.label">
                        <p class="px-2 pb-1 pt-2 text-xs font-semibold uppercase tracking-[0.06em] text-[#a39e98]">{{ group.label }}</p>
                        <button
                            v-for="option in group.options"
                            :key="option.id"
                            type="button"
                            class="block w-full rounded-lg px-2 py-2 text-left text-[15px] hover:bg-[#f6f5f4] dark:hover:bg-accent"
                            role="option"
                            :aria-selected="Number(props.modelValue) === option.id"
                            @click="select(option.id)"
                        >
                            {{ option.name }}
                        </button>
                    </template>
                    <p v-if="filteredGroups.length === 0" class="px-2 py-2 text-sm text-[#615d59]">Tidak ditemukan</p>
                </template>
                <template v-else>
                    <button
                        v-for="option in filtered"
                        :key="option.id"
                        type="button"
                        class="block w-full rounded-lg px-2 py-2 text-left text-[15px] hover:bg-[#f6f5f4] dark:hover:bg-accent"
                        role="option"
                        :aria-selected="Number(props.modelValue) === option.id"
                        @click="select(option.id)"
                    >
                        {{ option.name }}
                    </button>
                    <p v-if="filtered.length === 0" class="px-2 py-2 text-sm text-[#615d59]">Tidak ditemukan</p>
                </template>
            </div>
        </div>
        <input :value="modelValue ?? ''" type="hidden" :required="required" />
    </div>
</template>
