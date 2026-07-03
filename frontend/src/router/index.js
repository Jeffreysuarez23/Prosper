import { createRouter, createWebHistory } from 'vue-router'
import LoginView from '../views/LoginView.vue'
import DashboardLayout from '../views/DashboardLayout.vue'
import DashboardView from '../views/DashboardView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { requiresGuest: true }
    },
    {
      path: '/',
      component: DashboardLayout,
      meta: { requiresAuth: true },
      children: [
        { path: '', name: 'dashboard', component: DashboardView },
        { path: 'movimientos', name: 'movimientos', component: () => import('../views/MovimientosView.vue') },
        { path: 'estadisticas', name: 'estadisticas', component: () => import('../views/EstadisticasView.vue') },
        { path: 'objetivos', name: 'objetivos', component: () => import('../views/ObjetivosView.vue') },
        { path: 'gastos-fijos', name: 'gastos-fijos', component: () => import('../views/GastosFijosView.vue') },
        { path: 'tarjetas-credito', name: 'tarjetas-credito', component: () => import('../views/TarjetasCreditoView.vue') },
        { path: 'notificaciones', name: 'notificaciones', component: () => import('../views/NotificacionesView.vue') },
        { path: 'perfil', name: 'perfil', component: () => import('../views/PerfilView.vue') },
      ]
    }
  ]
})

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token');
  
  if (to.meta.requiresAuth && !token) {
    next({ name: 'login' });
  } else if (to.meta.requiresGuest && token) {
    next({ name: 'dashboard' });
  } else {
    next();
  }
})

export default router
