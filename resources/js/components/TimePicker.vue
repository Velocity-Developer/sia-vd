<script setup lang="ts">
import { Input } from '@/components/ui/input';

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
        <Input :id="id" :model-value="modelValue" type="time" :class="props.class" :required="props.required" @update:model-value="update" />
    </div>
</template>
