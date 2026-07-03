<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../services/api'

const data = ref([])
const loading = ref(true)
const currentYear = ref(new Date().getFullYear())

onMounted(async () => {
  try {
    const res = await api.get('/movimientos')
    data.value = res.data
  } catch (error) {
    console.error("Error", error)
  } finally {
    loading.value = false
  }
})

const yearData = computed(() => {
  return data.value.filter(tx => tx.fecha.startsWith(currentYear.value.toString()))
})

const income = computed(() => yearData.value.filter(i => i.tipo === 'ingreso').reduce((sum, i) => sum + parseFloat(i.monto), 0))
const expense = computed(() => yearData.value.filter(i => i.tipo === 'gasto').reduce((sum, i) => sum + parseFloat(i.monto), 0))

const formatCurrency = (val) => {
  return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 2 }).format(val || 0)
}

// Chart Options for Bar Chart
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

// Donut Charts Logic
const expenseCategories = computed(() => {
  const cats = {}
  yearData.value.filter(tx => tx.tipo === 'gasto').forEach(tx => {
    cats[tx.categoria] = (cats[tx.categoria] || 0) + parseFloat(tx.monto)
  })
  return Object.values(cats)
})
const expenseLabels = computed(() => {
  const cats = {}
  yearData.value.filter(tx => tx.tipo === 'gasto').forEach(tx => {
    cats[tx.categoria] = (cats[tx.categoria] || 0) + parseFloat(tx.monto)
  })
  return Object.keys(cats)
})

const incomeCategories = computed(() => {
  const cats = {}
  yearData.value.filter(tx => tx.tipo === 'ingreso').forEach(tx => {
    cats[tx.categoria] = (cats[tx.categoria] || 0) + parseFloat(tx.monto)
  })
  return Object.values(cats)
})
const incomeLabels = computed(() => {
  const cats = {}
  yearData.value.filter(tx => tx.tipo === 'ingreso').forEach(tx => {
    cats[tx.categoria] = (cats[tx.categoria] || 0) + parseFloat(tx.monto)
  })
  return Object.keys(cats)
})

const donutOptions = (labels) => ({
  chart: { type: 'donut', background: 'transparent' },
  labels: labels,
  colors: ['#4fd3a8', '#3b82f6', '#f472b6', '#fbbf24', '#a78bfa'],
  stroke: { show: false },
  dataLabels: { enabled: false },
  legend: { position: 'bottom', labels: { colors: 'var(--text-muted)' } },
  plotOptions: {
    pie: {
      donut: { size: '65%', background: 'transparent' }
    }
  },
  theme: { mode: 'dark' },
  tooltip: {
    y: {
      formatter: function (val) {
        return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 2 }).format(val || 0)
      }
    }
  }
})
</script>

<template>
  <div v-if="loading" style="padding: 20px;">Cargando estadísticas...</div>
  
  <section v-else class="page-section">
    <!-- Year Navigator -->
    <div class="month-navigator" id="statsYearNav" style="margin-bottom:22px;">
      <button @click="currentYear--" class="mn-arrow" aria-label="Año anterior" style="background:none;border:none;cursor:pointer;color:inherit;">
        <svg viewBox="0 0 24 24" width="20" height="20"><path d="M15 18l-6-6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
      </button>
      <div class="mn-center">
        <span class="mn-month">{{ currentYear }}</span>
        <span class="mn-sub">Estadísticas anuales</span>
      </div>
      <button @click="currentYear++" class="mn-arrow" aria-label="Año siguiente" style="background:none;border:none;cursor:pointer;color:inherit;">
        <svg viewBox="0 0 24 24" width="20" height="20"><path d="M9 18l6-6-6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
      </button>
    </div>

    <!-- Period summary -->
    <div class="mini-stats" id="statsSummary" style="grid-template-columns: repeat(2, 1fr);">
      <div class="mini-stat ms-income">
        <span class="ms-label">Ingresos Anuales</span>
        <strong class="ms-value" style="color: var(--green);">COP {{ formatCurrency(income).replace('COP', '').trim() }}</strong>
      </div>
      <div class="mini-stat ms-expense">
        <span class="ms-label">Gastos Anuales</span>
        <strong class="ms-value" style="color: var(--red);">COP {{ formatCurrency(expense).replace('COP', '').trim() }}</strong>
      </div>
    </div>

    <div class="grid grid-2">
      <!-- Main Chart -->
      <article class="card card-chart card-span">
        <div class="card-head">
          <h2>Ingresos vs Gastos</h2>
          <span class="badge">{{ currentYear }}</span>
        </div>
        <div class="chart-wrap chart-tall" style="height: 300px;">
          <apexchart type="bar" height="100%" :options="chartOptions" :series="chartSeries"></apexchart>
        </div>
      </article>

      <!-- Donut Charts -->
      <article class="card">
        <div class="card-head">
          <h2>Gastos por Categoría</h2>
        </div>
        <div class="donut-stats-wrap" style="display:flex; justify-content:center; align-items:center; min-height: 250px;">
          <div v-if="expenseCategories.length > 0" style="width: 100%;">
            <apexchart type="donut" height="250" :options="donutOptions(expenseLabels)" :series="expenseCategories"></apexchart>
          </div>
          <div v-else style="color:var(--text-muted);">No hay gastos.</div>
        </div>
      </article>

      <article class="card">
        <div class="card-head">
          <h2>Ingresos por Categoría</h2>
        </div>
        <div class="donut-stats-wrap" style="display:flex; justify-content:center; align-items:center; min-height: 250px;">
          <div v-if="incomeCategories.length > 0" style="width: 100%;">
            <apexchart type="donut" height="250" :options="donutOptions(incomeLabels)" :series="incomeCategories"></apexchart>
          </div>
          <div v-else style="color:var(--text-muted);">No hay ingresos.</div>
        </div>
      </article>
    </div>
  </section>
</template>
