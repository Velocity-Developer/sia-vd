<script setup lang="ts">
import { computed, ref } from 'vue';
import { CalendarIcon } from 'lucide-vue-next';
import DatePicker from '@/components/DatePicker.vue';
import TimePicker from '@/components/TimePicker.vue';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

const props = withDefaults(defineProps<{ modelValue: string; placeholder?: string; minDate?: string }>(), { placeholder: 'Pilih tanggal', minDate: '' });
const emit = defineEmits<{ (event: 'update:modelValue', value: string): void }>();
const open = ref(false);
const date = computed(() => props.modelValue.slice(0, 10));
const time = computed(() => props.modelValue.slice(11, 16));
const display = computed(() => {
    if (!date.value) return props.placeholder;

    const selected = new Date(`${date.value}T${time.value || '00:00'}`);
    const formattedDate = selected.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    return `${formattedDate}${time.value ? ` ${time.value}` : ''}`;
});
const update = (nextDate = date.value, nextTime = time.value) => emit('update:modelValue', nextDate ? `${nextDate}T${nextTime || '00:00'}` : '');
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button type="button" variant="outline" class="h-10 w-full justify-start gap-2 rounded-[4px] border-[#dddddd] bg-white text-left font-normal text-black">
                <CalendarIcon class="size-4" />{{ display }}
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto p-3" align="start">
            <DatePicker :model-value="date" :min-value="props.minDate" :placeholder="props.placeholder" :required="false" @update:model-value="(value) => update(value)" />
            <TimePicker :model-value="time" class="mt-3 h-10" @update:model-value="(value) => update(date, String(value))" />
            <Button type="button" class="mt-3 w-full" @click="open = false">Selesai</Button>
        </PopoverContent>
    </Popover>
</template>
