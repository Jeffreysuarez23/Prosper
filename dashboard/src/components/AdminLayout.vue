<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '../services/api'

const router = useRouter()
const route = useRoute()
const user = ref(JSON.parse(localStorage.getItem('user') || '{}'))

const handleLogout = async () => {
  try {
    await api.post('/logout')
  } catch (e) {
    console.error(e)
  }
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  window.location.href = 'https://prosper-frontend-pi.vercel.app/login'
}

onMounted(async () => {
  try {
    const userRes = await api.get('/user')
    user.value = userRes.data
    localStorage.setItem('user', JSON.stringify(userRes.data))
  } catch (error) {
    console.error('Error fetching user data:', error)
  }
})
</script>

<template>
  <div class="command-center">
    <!-- Top Navigation -->
    <header class="top-nav">
      <div class="brand">
        <div class="brand-logo">
          <svg viewBox="0 0 24 24" width="24" height="24">
            <path d="M4 18 L9 10 L13 14 L20 4" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </div>
        <span class="brand-text">Prosper <span>Panel</span></span>
      </div>
      
      <nav class="nav-links">
        <RouterLink to="/" exact-active-class="active-link" class="nav-btn">
          <svg viewBox="0 0 24 24" width="18" height="18"><path d="M4 12 L12 4 L20 12 M6 10 V20 H18 V10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
          Resumen
        </RouterLink>
        <RouterLink to="/usuarios" active-class="active-link" class="nav-btn">
          <svg viewBox="0 0 24 24" width="18" height="18"><circle cx="9" cy="7" r="4" fill="none" stroke="currentColor" stroke-width="2"></circle><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" fill="none" stroke="currentColor" stroke-width="2"></path><path d="M16 3.13a4 4 0 0 1 0 7.75" fill="none" stroke="currentColor" stroke-width="2"></path></svg>
          Directorio
        </RouterLink>
      </nav>

      <div class="top-actions">
        <div class="user-info">
          <span class="user-role">Admin</span>
          <span class="user-name">{{ user.name }}</span>
        </div>
        <RouterLink to="/perfil" class="action-btn outline-btn" title="Editar Perfil">
          Perfil
        </RouterLink>
        <a href="https://prosper-frontend-pi.vercel.app/" class="action-btn outline-btn" title="Ir a App Principal">
          App Principal
        </a>
        <button @click="handleLogout" class="action-btn danger-btn" title="Cerrar Sesión">
          Cerrar Sesión
        </button>
      </div>
    </header>

    <!-- Main Content Workspace -->
    <main class="workspace">
      <div class="workspace-inner">
        <RouterView />
      </div>
    </main>
  </div>
</template>

<style>
/* Command Center Global Overrides */
:root {
  --cc-bg: #090e17;
  --cc-surface: rgba(20, 27, 45, 0.7);
  --cc-border: rgba(65, 84, 126, 0.3);
  --cc-accent: #60a5fa;
  --cc-gold: #fbbf24;
  --cc-text: #e2e8f0;
  --cc-text-muted: #94a3b8;
}

body {
  background-color: var(--cc-bg) !important;
  color: var(--cc-text) !important;
  background-image: 
    radial-gradient(circle at 15% 50%, rgba(96, 165, 250, 0.04) 0%, transparent 50%),
    radial-gradient(circle at 85% 30%, rgba(168, 85, 247, 0.04) 0%, transparent 50%);
  background-attachment: fixed;
}
</style>

<style scoped>
.command-center {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

/* TOP NAV */
.top-nav {
  height: 70px;
  background: rgba(9, 14, 23, 0.8);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid var(--cc-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 32px;
  position: sticky;
  top: 0;
  z-index: 50;
}

.brand {
  display: flex;
  align-items: center;
  height: 100%;
  gap: 12px;
}
.brand-logo {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #3b82f6, #8b5cf6);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}
.brand-text {
  font-size: 1.25rem;
  font-weight: 700;
  color: white;
  letter-spacing: -0.5px;
  line-height: 1;
  margin-top: 4px;
}
.brand-text span {
  font-weight: 400;
  color: var(--cc-accent);
}

.nav-links {
  display: flex;
  gap: 8px;
  position: absolute;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(20, 27, 45, 0.5);
  padding: 4px;
  border-radius: 12px;
  border: 1px solid var(--cc-border);
}
.nav-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 8px;
  color: var(--cc-text-muted);
  text-decoration: none;
  font-weight: 500;
  font-size: 0.95rem;
  transition: all 0.2s ease;
}
.nav-btn:hover {
  color: white;
  background: rgba(255, 255, 255, 0.05);
}
.active-link {
  background: rgba(96, 165, 250, 0.15) !important;
  color: var(--cc-accent) !important;
}

.top-actions {
  display: flex;
  align-items: center;
  gap: 16px;
}
.user-info {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  margin-right: 12px;
}
.user-role {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 1px;
  color: var(--cc-gold);
  font-weight: 700;
}
.user-name {
  font-size: 0.9rem;
  font-weight: 500;
}

.action-btn {
  padding: 8px 16px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
  transition: all 0.2s;
  text-decoration: none;
}
.outline-btn {
  background: transparent;
  border: 1px solid var(--cc-border);
  color: var(--cc-text);
}
.outline-btn:hover {
  background: rgba(255, 255, 255, 0.05);
}
.danger-btn {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: #ef4444;
}
.danger-btn:hover {
  background: rgba(239, 68, 68, 0.2);
}

/* WORKSPACE */
.workspace {
  flex: 1;
  padding: 32px;
  overflow-y: auto;
}
.workspace-inner {
  max-width: 1400px;
  margin: 0 auto;
}

@media (max-width: 1024px) {
  .nav-links {
    position: static;
    transform: none;
  }
}
@media (max-width: 768px) {
  .top-nav {
    flex-direction: column;
    height: auto;
    padding: 16px;
    gap: 16px;
  }
  .user-info {
    display: none;
  }
}
</style>
