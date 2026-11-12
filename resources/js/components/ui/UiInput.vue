<script setup lang="ts">
import { inject } from 'vue'
import { controlClasses } from './controlClasses'
import { fieldContextKey } from './UiField.vue'

const model = defineModel<string>()

/** Inside a UiField the input spans the field's width. */
const isInField = inject(fieldContextKey, false)
</script>

<template>
    <input v-model="model" :class="[controlClasses, isInField && 'w-full']" />
</template>
