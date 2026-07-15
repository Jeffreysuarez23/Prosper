import { createRouter, createWebHistory } from 'vue-router'
import api from '../services/api'
import DashboardView from '../views/DashboardView.vue'
import UsuariosView from '../views/UsuariosView.vue'
import AuditoriaView from '../views/AuditoriaView.vue'
import AdminPerfilView from '../views/AdminPerfilView.vue'
import AccessDeniedView from '../views/AccessDeniedView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'dashboard',
      component: DashboardView,
      meta: { requiresAdmin: true }
    },
    {
      path: '/usuarios',
      name: 'usuarios',
      component: UsuariosView,
      meta: { requiresAdmin: true }
    },
    {
      path: '/usuarios/:id/auditar',
      name: 'auditoria',
      component: AuditoriaView,
      meta: { requiresAdmin: true }
    },
    {
      path: '/perfil',
      name: 'perfil',
      component: AdminPerfilView,
      meta: { requiresAdmin: true }
    },
    {
      path: '/denied',
      name: 'denied',
      component: AccessDeniedView
    }
  ]
})


router.beforeEach(async (to, from, next) => {
  // Check if token is passed in URL
  const urlToken = to.query.token
  if (urlToken) {
    localStorage.setItem('token', urlToken)
    // Clean URL
    const cleanUrl = window.location.pathname
    window.history.replaceState({}, document.title, cleanUrl)
  }

  const token = localStorage.getItem('token')

  if (to.meta.requiresAdmin) {
    if (!token) {
      // If no token, kick back to main app
      window.location.href = 'https://prosper-frontend-pi.vercel.app/login'
      return
    }

    try {
      // Verify token and role
      const res = await api.get('/user')
      const user = res.data
      
      if (user.role_id === 1) {
        next() // Allow access
      } else {
        // HIPER SEGURIDAD: Si no es admin, destruir token local y redirigir con alerta
        localStorage.removeItem('token')
        localStorage.removeItem('user')
        window.location.href = 'https://prosper-frontend-pi.vercel.app/login?security_breach=true'
      }
    } catch (err) {
      // Token invalid or expired
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      window.location.href = 'https://prosper-frontend-pi.vercel.app/login'
    }
  } else {
    next()
  }
})

export default router
