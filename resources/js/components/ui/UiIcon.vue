<script setup lang="ts">
import { computed } from 'vue'
import { icons } from '../../icons'

const props = withDefaults(defineProps<{ name: string; size?: number }>(), { size: 16 })

const component = computed(() => icons[props.name] ?? icons['file-text'])
</script>

<template>
    <component :is="component" class="ui-icon" :size="size" :stroke-width="1.75" aria-hidden="true" />
</template>
