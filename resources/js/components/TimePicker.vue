<script setup lang="ts">
import { Input } from '@/components/ui/input';

const props = withDefaults(defineProps<{ modelValue?: string; id?: string; class?: string; required?: boolean }>(), { modelValue: '', id: undefined, class: '', required: false });
const emit = defineEmits<{ (event: 'update:modelValue', value: string): void }>();
const update = (value: string | number) => emit('update:modelValue', String(value));
</script>

<template>
    <Input
        :id="props.id"
        :model-value="props.modelValue"
        type="time"
        :class="`rounded-[4px] border-[#dddddd] bg-white px-3 text-[15px] font-medium tabular-nums text-black shadow-sm transition-[border-color,box-shadow] hover:border-[#b8b4af] focus-visible:border-[#0075de] focus-visible:ring-1 focus-visible:ring-[#0075de] focus-visible:ring-offset-0 ${props.class}`"
        :required="props.required"
        @update:model-value="update"
    />
</template>
