<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '../services/api'

const user = ref(JSON.parse(localStorage.getItem('user') || '{}'))
const userPlan = computed(() => user.value.membresia?.plan || 'gratis')

const openMembershipModal = () => {
  window.dispatchEvent(new CustomEvent('open-membership-modal'))
}

const dashboardData = ref(null)
const loading = ref(true)
const recientes = ref([])
const allMovimientos = ref([])

const currentYear = new Date().getFullYear().toString()

onMounted(async () => {
  try {
    const res = await api.get('/dashboard')
    dashboardData.value = res.data
    
    // Simulate getting recent transactions from API
    // (You would actually create an endpoint or fetch them here)
    const movRes = await api.get('/movimientos')
    allMovimientos.value = movRes.data
    recientes.value = movRes.data.slice(0, 5) // Last 5
  } catch (error) {
    console.error("Error cargando dashboard", error)
  } finally {
    loading.value = false
  }
})

const formatCurrency = (val) => {
  return new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    minimumFractionDigits: 2
  }).format(val || 0)
}

const getCategoryIcon = (cat) => {
  const icons = {
    'Salario':'💰', 'Ventas':'📈', 'Regalos':'🎁',
    'Vivienda':'🏠', 'Comida':'🍔', 'Transporte':'🚗', 'Ocio':'🍿', 'Servicios':'⚡', 'Ropa':'👕',
    'Meta específica':'🎯', 'Fondo de emergencia':'🛡️'
  }
  return icons[cat] || '📎'
}

const yearData = computed(() => {
  return allMovimientos.value.filter(tx => tx.fecha.startsWith(currentYear))
})

const chartSeries = computed(() => {
  const months = ['01','02','03','04','05','06','07','08','09','10','11','12']
  const incData = months.map(m => {
    return yearData.value.filter(tx => tx.tipo === 'ingreso' && tx.fecha.substring(5,7) === m)
                         .reduce((s, tx) => s + parseFloat(tx.monto), 0)
  })
  const expData = months.map(m => {
    return yearData.value.filter(tx => tx.tipo === 'gasto' && tx.fecha.substring(5,7) === m)
                         .reduce((s, tx) => s + parseFloat(tx.monto), 0)
  })
  return [
    { name: 'Ingresos', data: incData },
    { name: 'Gastos', data: expData }
  ]
})

const chartOptions = {
  chart: { type: 'bar', toolbar: { show: false }, background: 'transparent' },
  colors: ['#4fd3a8', '#f9a8d4'],
  plotOptions: { bar: { horizontal: false, columnWidth: '40%', borderRadius: 4 } },
  dataLabels: { enabled: false },
  stroke: { show: true, width: 2, colors: ['transparent'] },
  xaxis: {
    categories: ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],
    labels: { style: { colors: 'var(--text-muted)' } },
    axisBorder: { show: false },
    axisTicks: { show: false }
  },
  yaxis: { labels: { show: false } },
  grid: {
    borderColor: 'var(--border)',
    strokeDashArray: 4,
    yaxis: { lines: { show: true } },
    xaxis: { lines: { show: false } }
  },
  legend: { position: 'top', horizontalAlign: 'right', labels: { colors: 'var(--text-muted)' } },
  fill: { opacity: 1 },
  theme: { mode: 'dark' },
  tooltip: {
    y: {
      formatter: function (val) {
        return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 2 }).format(val || 0)
      }
    }
  }
}
</script>

<template>
  <div v-if="loading" style="padding: 20px;">Cargando datos...</div>
  
  <section v-else class="page-section">
    <div class="stat-cards" id="statCards">
      <div class="stat-card sc-balance">
        <div class="sc-icon">
          <svg viewBox="0 0 24 24" width="22" height="22">
            <path d="M2 7a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7Z" fill="none" stroke="currentColor" stroke-width="1.6" />
            <circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.6" />
          </svg>
        </div>
        <span class="sc-label">Balance Disponible</span>
        <strong class="sc-value" id="statBalance">{{ formatCurrency(dashboardData.balance_global) }}</strong>
      </div>
      <div class="stat-card sc-income">
        <div class="sc-icon">
          <svg viewBox="0 0 24 24" width="22" height="22">
            <path d="M12 19V5M5 12l7-7 7 7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </div>
        <span class="sc-label">Ingresos Totales</span>
        <strong class="sc-value" id="statIncome">+{{ formatCurrency(dashboardData.ingresos_totales) }}</strong>
      </div>
      <div class="stat-card sc-expense">
        <div class="sc-icon">
          <svg viewBox="0 0 24 24" width="22" height="22">
            <path d="M12 5v14M5 12l7 7 7-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
        </div>
        <span class="sc-label">Gastos Totales</span>
        <strong class="sc-value" id="statExpense">-{{ formatCurrency(dashboardData.gastos_totales) }}</strong>
      </div>
    </div>

    <div class="grid grid-2">
      <article class="card card-chart">
        <div class="card-head" style="display: flex; justify-content: space-between; align-items: center;">
          <h2 style="margin: 0;">Ingresos vs Gastos {{ currentYear }}</h2>
          <span v-if="userPlan === 'gratis'" style="font-size: 0.7rem; font-weight: bold; padding: 2px 8px; border-radius: 12px; background: rgba(255,255,255,0.1); color: var(--text-muted); letter-spacing: 0.5px;">PRO</span>
        </div>
        <div class="chart-wrap" id="chartDashTrend" style="height: 250px; position: relative;">
          <template v-if="userPlan !== 'gratis'">
            <apexchart type="bar" height="100%" :options="chartOptions" :series="chartSeries"></apexchart>
          </template>
          <template v-else>
            <div style="position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 10; border-radius: 8px;">
              <svg viewBox="0 0 24 24" style="width: 32px !important; height: 32px !important; color: var(--text-muted); margin-bottom: 12px; opacity: 0.7;">
                <path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <h3 style="font-size: 1rem; margin-bottom: 8px; color: var(--text);">Estadísticas Bloqueadas</h3>
              <p style="font-size: 0.85rem; color: var(--text-muted); text-align: center; max-width: 80%; margin-bottom: 16px;">Sube a un plan Pro o Ultra para desbloquear el análisis visual de tus finanzas.</p>
              <button @click="openMembershipModal" class="btn-accent" style="padding: 6px 16px; font-size: 0.85rem;">Ver Membresías</button>
            </div>
            <!-- Obfuscated background chart with dummy data -->
            <apexchart style="opacity: 0.05; filter: blur(3px); pointer-events: none;" type="bar" height="100%" :options="chartOptions" :series="[{name:'Ingresos', data:[10, 20, 15, 30, 25, 40, 35, 50, 45, 60, 55, 70]}, {name:'Gastos', data:[5, 10, 8, 15, 12, 20, 18, 25, 22, 30, 28, 35]}]"></apexchart>
          </template>
        </div>
      </article>
      <div class="card card-recent">
        <div class="card-head">
          <h2>Movimientos Recientes</h2>
          <RouterLink to="/movimientos" class="btn-link">Ver todos</RouterLink>
        </div>
        
        <ul v-if="recientes.length > 0" class="tx-list" id="recentTxList">
          <li v-for="tx in recientes" :key="tx.id" class="tx-item">
            <div class="tx-item-icon">{{ getCategoryIcon(tx.categoria) }}</div>
            <div class="tx-item-info">
              <div class="tx-item-desc">{{ tx.descripcion || 'Movimiento' }}</div>
              <div class="tx-item-meta">{{ tx.categoria }} · {{ tx.fecha }}</div>
            </div>
            <div :class="['tx-item-amount', `is-${tx.tipo}`]">
              {{ tx.tipo === 'ingreso' ? '+' : (tx.tipo === 'gasto' ? '-' : '') }}{{ formatCurrency(tx.monto) }}
            </div>
          </li>
        </ul>
        <div v-else class="empty-state" id="recentEmpty">
          <div class="empty-icon">💸</div>
          <p>Aún no hay movimientos.</p>
        </div>
      </div>
    </div>
  </section>
</template>
