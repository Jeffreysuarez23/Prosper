<script setup>
import { ref, onMounted } from 'vue'
import api from '../services/api'
import VueApexCharts from 'vue3-apexcharts'

const stats = ref({
  total_users: 0,
  gratis_users: 0,
  pro_users: 0,
  ultra_users: 0,
  total_movimientos: 0
})

const loading = ref(true)

const chartOptions = ref({
  chart: {
    type: 'donut',
    background: 'transparent',
    foreColor: 'var(--cc-text)'
  },
  labels: ['Gratis', 'Pro', 'Ultra'],
  colors: ['#64748b', '#4fd3a8', '#a855f7'],
  stroke: { show: false },
  dataLabels: { enabled: false },
  legend: { position: 'bottom' },
  theme: { mode: localStorage.getItem('prosper-theme') || 'dark' }
})

const chartSeries = ref([0, 0, 0])

onMounted(async () => {
  try {
    const res = await api.get('/admin/stats')
    stats.value = res.data
    chartSeries.value = [res.data.gratis_users, res.data.pro_users, res.data.ultra_users]
  } catch (error) {
    console.error(error)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="dashboard-content">
    <div class="header-section">
      <h2>Resumen de Plataforma</h2>
      <p class="text-muted">Métricas clave de adopción y uso</p>
    </div>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Cargando métricas...</p>
    </div>

    <div v-else>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon" style="color: var(--cc-accent); background: rgba(59, 130, 246, 0.1);">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
          </div>
          <div class="stat-info">
            <h3>{{ stats.total_users }}</h3>
            <p>Total Usuarios</p>
          </div>
        </div>

        <div class="stat-card premium-card">
          <div class="stat-icon" style="color: #a855f7; background: rgba(168, 85, 247, 0.1);">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
          </div>
          <div class="stat-info">
            <h3 style="background: -webkit-linear-gradient(45deg, #a855f7, #ec4899); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
              {{ stats.pro_users + stats.ultra_users }}
            </h3>
            <p>Suscripciones Activas</p>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon" style="color: #10b981; background: rgba(16, 185, 129, 0.1);">
            <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
          </div>
          <div class="stat-info">
            <h3>{{ stats.total_movimientos }}</h3>
            <p>Movimientos Globales</p>
          </div>
        </div>
      </div>

      <div class="charts-container" style="margin-top: 32px; display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
        <div class="chart-card">
          <h3>Distribución de Planes</h3>
          <div class="chart-wrapper">
            <VueApexCharts type="donut" height="300" :options="chartOptions" :series="chartSeries" />
          </div>
        </div>
        
        <div class="chart-card">
          <h3>Estado de la Plataforma</h3>
          <div style="padding: 20px; color: var(--cc-text-muted); line-height: 1.6;">
            <p><strong>Privacidad activada:</strong> Los montos y descripciones de las transacciones están encriptados o bloqueados por seguridad.</p>
            <p style="margin-top: 12px;">Actualmente la plataforma cuenta con <strong>{{ stats.total_users }}</strong> usuarios, de los cuales el <strong>{{ stats.total_users > 0 ? Math.round(((stats.pro_users + stats.ultra_users) / stats.total_users) * 100) : 0 }}%</strong> tienen una suscripción activa.</p>
            <div style="margin-top: 24px; display: flex; gap: 16px;">
              <div style="flex: 1; padding: 16px; background: var(--cc-surface); border-radius: 12px; text-align: center;">
                <h4 style="color: #64748b; font-size: 1.5rem; margin-bottom: 4px;">{{ stats.gratis_users }}</h4>
                <span style="font-size: 0.8rem;">Usuarios Gratis</span>
              </div>
              <div style="flex: 1; padding: 16px; background: var(--cc-surface); border-radius: 12px; text-align: center;">
                <h4 style="color: #4fd3a8; font-size: 1.5rem; margin-bottom: 4px;">{{ stats.pro_users }}</h4>
                <span style="font-size: 0.8rem;">Usuarios Pro</span>
              </div>
              <div style="flex: 1; padding: 16px; background: var(--cc-surface); border-radius: 12px; text-align: center;">
                <h4 style="color: #a855f7; font-size: 1.5rem; margin-bottom: 4px;">{{ stats.ultra_users }}</h4>
                <span style="font-size: 0.8rem;">Usuarios Ultra</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dashboard-content {
  padding: 24px;
  max-width: 1200px;
  margin: 0 auto;
}
.header-section {
  margin-bottom: 32px;
}
.header-section h2 {
  font-size: 1.8rem;
  margin-bottom: 8px;
}
.text-muted {
  color: var(--cc-text-muted);
}
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 24px;
}
.stat-card {
  background: var(--cc-surface);
  border: 1px solid var(--cc-border);
  border-radius: 16px;
  padding: 24px;
  display: flex;
  align-items: center;
  gap: 20px;
  transition: transform 0.2s;
}
.stat-card:hover {
  transform: translateY(-2px);
}
.premium-card {
  background: linear-gradient(145deg, var(--cc-surface) 0%, rgba(168, 85, 247, 0.05) 100%);
  border-color: rgba(168, 85, 247, 0.3);
}
.stat-icon {
  width: 56px;
  height: 56px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.stat-info h3 {
  font-size: 2rem;
  margin-bottom: 4px;
}
.stat-info p {
  color: var(--cc-text-muted);
  font-size: 0.9rem;
  margin: 0;
}
.chart-card {
  background: var(--cc-surface);
  border: 1px solid var(--cc-border);
  border-radius: 16px;
  padding: 24px;
}
.chart-card h3 {
  margin-bottom: 20px;
  font-size: 1.1rem;
}
.chart-wrapper {
  display: flex;
  justify-content: center;
}
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px;
  color: var(--cc-text-muted);
}
.spinner {
  width: 40px;
  height: 40px;
  border: 3px solid rgba(255,255,255,0.1);
  border-radius: 50%;
  border-top-color: var(--cc-accent);
  animation: spin 1s ease-in-out infinite;
  margin-bottom: 16px;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
@media (max-width: 768px) {
  .charts-container {
    grid-template-columns: 1fr !important;
  }
}
</style>
