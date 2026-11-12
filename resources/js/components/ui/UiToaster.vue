<script setup lang="ts">
import { useToast } from '../../composables/useToast'

const { state } = useToast()
</script>

<template>
    <div class="toast" :class="{ on: state.isVisible, 'bg-danger!': state.tone === 'error' }" :role="state.tone === 'error' ? 'alert' : 'status'">
        {{ state.message }}
    </div>
</template>
