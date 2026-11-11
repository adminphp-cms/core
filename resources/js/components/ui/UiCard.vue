<script setup lang="ts">
withDefaults(defineProps<{ title?: string; description?: string; padded?: boolean }>(), { padded: true })
</script>

<template>
    <section class="ui-card">
        <header v-if="title || $slots.actions" class="ui-card-header">
            <div class="ui-card-heading">
                <h2 v-if="title">{{ title }}</h2>
                <p v-if="description">{{ description }}</p>
            </div>
            <div v-if="$slots.actions" class="ui-card-actions"><slot name="actions" /></div>
        </header>

        <div :class="{ 'ui-card-body': padded }"><slot /></div>

        <footer v-if="$slots.footer" class="ui-card-footer"><slot name="footer" /></footer>
    </section>
</template>
