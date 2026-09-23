<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Clock3 } from 'lucide-vue-next';

const props = withDefaults(defineProps<{ modelValue?: string; id?: string; class?: string; required?: boolean }>(), {
    modelValue: '',
    id: undefined,
    class: '',
    required: false,
});
const emit = defineEmits<{ (event: 'update:modelValue', value: string): void }>();
const update = (value: string | number) => emit('update:modelValue', String(value));
</script>

<template>
    <div class="relative">
        <Clock3 class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#615d59]" />
        <Input
            :id="id"
            :model-value="modelValue"
            type="time"
            :class="`pl-10 ${props.class}`"
            :required="props.required"
            @update:model-value="update"
        />
    </div>
</template>
