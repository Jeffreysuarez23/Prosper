<script setup>
import { onMounted } from 'vue'
import { RouterView } from 'vue-router'

onMounted(() => {
  const savedTheme = localStorage.getItem('prosper-theme') || 'dark'
  document.documentElement.setAttribute('data-theme', savedTheme)
})
</script>

<template>
  <RouterView />
</template>

<style>
/* Los estilos globales vienen de src/assets/main.css */
</style>
