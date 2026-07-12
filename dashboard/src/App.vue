<script setup>
import AdminLayout from './components/AdminLayout.vue'
import { useRoute } from 'vue-router'

const route = useRoute()
</script>

<template>
  <AdminLayout v-if="route.path !== '/denied'" />
  <router-view v-if="route.path === '/denied'"></router-view>
</template>

