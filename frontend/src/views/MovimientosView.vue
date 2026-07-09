<script setup>
import { ref, computed, onMounted, watch, inject } from 'vue'
import api from '../services/api'
import Swal from 'sweetalert2'

const loading = ref(true)

const movimientos = ref([])
const totalPages = ref(1)
const currentPage = ref(1)
const totalRecords = ref(0)

const income = ref(0)
const expense = ref(0)
const balance = ref(0)

const searchQuery = ref('')
const typeFilter = ref('')
const catFilter = ref('')

const openTxModal = inject('openTxModal')
const refreshHeaderBalance = inject('refreshHeaderBalance')

// Month formatting
const currentMonth = ref(new Date().toISOString().slice(0, 7)) // 'YYYY-MM'

const displayMonth = computed(() => {
  if (currentMonth.value === 'all') return 'Todos los meses'
  const [year, month] = currentMonth.value.split('-')
  const monthNames = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre']
  return `${monthNames[parseInt(month) - 1]} ${year}`
})

const prevMonth = computed(() => {
  if (currentMonth.value === 'all') return ''
  const date = new Date(currentMonth.value + '-01T00:00:00')
  date.setMonth(date.getMonth() - 1)
  return date.toISOString().slice(0, 7)
})

const nextMonth = computed(() => {
  if (currentMonth.value === 'all') return ''
  const date = new Date(currentMonth.value + '-01T00:00:00')
  date.setMonth(date.getMonth() + 1)
  return date.toISOString().slice(0, 7)
})

const setMonth = (val) => {
  currentMonth.value = val
  currentPage.value = 1
  fetchData()
}

const fetchData = async () => {
  loading.value = true
  try {
    const params = {
      paginate: true,
      page: currentPage.value,
      month: currentMonth.value,
      q: searchQuery.value,
      type: typeFilter.value,
      cat: catFilter.value
    }
    
    const res = await api.get('/movimientos', { params })
    movimientos.value = res.data.movimientos.data
    totalPages.value = res.data.movimientos.last_page
    totalRecords.value = res.data.movimientos.total
    
    income.value = res.data.stats.income
    expense.value = res.data.stats.expense
    balance.value = res.data.stats.balance
  } catch (error) {
    console.error("Error", error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchData()
})

// Watch filters (debounce search if needed, but for simplicity we watch and fetch)
let searchTimeout
watch([searchQuery, typeFilter, catFilter], () => {
  currentPage.value = 1
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    fetchData()
  }, 300)
})

const formatCurrency = (val) => {
  return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 2 }).format(val || 0)
}

const getCategoryIcon = (cat) => {
  const icons = {
    'Salario':'💼','Freelance':'💻','Inversiones':'📈','Ventas':'🛒','Regalos':'🎁',
    'Comida':'🍔','Transporte':'🚗','Vivienda':'🏠','Servicios':'⚡','Salud':'🏥','Ocio':'🎮','Educación':'📚','Ropa':'👕',
    'Fondo de emergencia':'🛡️','Inversión':'📊','Meta específica':'🎯','Ahorro general':'💰',
    'Entre cuentas':'🔄','A terceros':'👤','Pago de deuda':'💳','Otros':'📎'
  }
  return icons[cat] || '📎'
}

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  const dateObj = new Date(dateStr)
  // Ensure we don't have timezone shifts by parsing parts
  const [year, month, day] = dateStr.split('-')
  const monthNames = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic']
  return `${day.padStart(2, '0')} ${monthNames[parseInt(month) - 1]} ${year}`
}

const handleDelete = async (id) => {
  const result = await Swal.fire({
    title: '¿Eliminar Movimiento?',
    text: "Esta acción es permanente y no se puede deshacer.",
    icon: 'warning',
    showCancelButton: true,
    background: 'var(--surface)',
    color: 'var(--text)',
    customClass: { 
      popup: 'swal-custom-popup',
      title: 'swal-custom-title',
      htmlContainer: 'swal-custom-content',
      actions: 'swal-custom-actions',
      confirmButton: 'swal-custom-confirm',
      cancelButton: 'swal-custom-cancel'
    },
    buttonsStyling: false,
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar'
  })

  if (result.isConfirmed) {
    try {
      await api.delete(`/movimientos/${id}`)
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Movimiento eliminado',
        showConfirmButton: false,
        timer: 3000,
        background: 'var(--surface)',
        color: 'var(--text)'
      })
      fetchData()
      if (refreshHeaderBalance) refreshHeaderBalance()
    } catch (e) {
      console.error(e)
    }
  }
}

const setPage = (p) => {
  if (p < 1 || p > totalPages.value) return
  currentPage.value = p
  fetchData()
}
</script>

<template>
  <div v-if="loading && !movimientos.length" style="padding: 20px;">Cargando movimientos...</div>
  <section v-else class="page-section">
    <!-- Month Navigator -->
    <div class="month-navigator">
      <a v-if="currentMonth !== 'all'" href="#" @click.prevent="setMonth(prevMonth)" class="mn-arrow" aria-label="Mes anterior" style="display:flex;align-items:center;justify-content:center;text-decoration:none;color:inherit;">
        <svg viewBox="0 0 24 24" width="20" height="20">
          <path d="M15 18l-6-6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </a>
      <div class="mn-center">
        <span class="mn-month">{{ displayMonth }}</span>
        <span class="mn-sub">{{ currentMonth === 'all' ? 'Historial completo' : 'Todos los movimientos del mes' }}</span>
      </div>
      <a v-if="currentMonth !== 'all'" href="#" @click.prevent="setMonth(nextMonth)" class="mn-arrow" aria-label="Mes siguiente" style="display:flex;align-items:center;justify-content:center;text-decoration:none;color:inherit;">
        <svg viewBox="0 0 24 24" width="20" height="20">
          <path d="M9 18l6-6-6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </a>
      <a href="#" @click.prevent="setMonth('all')" class="mn-all-btn" style="text-decoration:none;color:inherit;padding:4px 8px;">Todos</a>
    </div>

    <!-- Summary mini-cards -->
    <div class="mini-stats">
      <div class="mini-stat ms-balance">
        <span class="ms-label">Balance del mes</span>
        <strong class="ms-value">{{ formatCurrency(balance) }}</strong>
      </div>
      <div class="mini-stat ms-income">
        <span class="ms-label">Ingresos</span>
        <strong class="ms-value" style="color: var(--green);">+{{ formatCurrency(income) }}</strong>
      </div>
      <div class="mini-stat ms-expense">
        <span class="ms-label">Gastos</span>
        <strong class="ms-value" style="color: var(--red);">-{{ formatCurrency(expense) }}</strong>
      </div>
    </div>

    <!-- Filters -->
    <div class="filters-bar" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:24px;">
      <div class="search-wrap" style="position:relative; flex:1; min-width:0;">
        <svg class="search-icon" viewBox="0 0 24 24" width="16" height="16" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);">
          <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.8" />
          <path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        </svg>
        <input type="search" v-model="searchQuery" placeholder="Buscar movimientos..." style="width:100%; padding-left:36px; height:40px; border-radius:8px; border:1px solid var(--border);">
      </div>
      <select v-model="typeFilter" style="height:40px; border-radius:8px; border:1px solid var(--border); padding:0 12px; background:var(--surface);">
        <option value="">Todos los tipos</option>
        <option value="ingreso">Ingresos</option>
        <option value="gasto">Gastos</option>
      </select>
      <select v-model="catFilter" style="height:40px; border-radius:8px; border:1px solid var(--border); padding:0 12px; background:var(--surface);">
        <option value="">Todas las categorías</option>
        <optgroup label="Ingresos">
          <option value="Salario">💼 Salario</option>
          <option value="Freelance">💻 Freelance</option>
          <option value="Inversiones">📈 Inversiones</option>
          <option value="Ventas">🛒 Ventas</option>
          <option value="Regalos">🎁 Regalos</option>
          <option value="Otros">📎 Otros</option>
        </optgroup>
        <optgroup label="Gastos">
          <option value="Comida">🍔 Comida</option>
          <option value="Transporte">🚗 Transporte</option>
          <option value="Vivienda">🏠 Vivienda</option>
          <option value="Servicios">⚡ Servicios</option>
          <option value="Salud">🏥 Salud</option>
          <option value="Ocio">🎮 Ocio</option>
          <option value="Educación">📚 Educación</option>
          <option value="Ropa">👕 Ropa</option>
          <option value="Otros">📎 Otros</option>
        </optgroup>
      </select>
      <button @click="searchQuery=''; typeFilter=''; catFilter=''" class="btn-outline btn-sm" style="height:40px;">Limpiar</button>
    </div>

    <!-- Transaction list -->
    <article class="card">
      <div class="card-head" style="justify-content:center;">
        <h2>Historial de Movimientos</h2>
        <span class="badge">{{ totalRecords }} registros</span>
      </div>
      
      <div v-if="movimientos.length > 0" class="table-responsive">
        <table class="tx-table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Descripción</th>
              <th>Categoría</th>
              <th>Monto</th>
              <th style="text-align:right;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="tx in movimientos" :key="tx.id">
              <td style="white-space:nowrap; color:var(--text-muted); font-size:0.85rem;">{{ formatDate(tx.fecha) }}</td>
              <td>
                <div class="tx-item-desc" style="font-size:0.95rem;">{{ tx.descripcion || 'Movimiento' }}</div>
              </td>
              <td>
                <div style="display:flex; align-items:center; gap:8px; color:var(--text-muted); font-size:0.85rem;">
                  <div class="tx-item-icon" style="width:28px; height:28px; font-size:0.9rem;">{{ getCategoryIcon(tx.categoria) }}</div>
                  {{ tx.categoria }}
                </div>
              </td>
              <td :class="['tx-item-amount', `is-${tx.tipo}`]" style="text-align:left;">
                {{ tx.tipo === 'ingreso' ? '+' : (tx.tipo === 'gasto' ? '-' : '') }}{{ formatCurrency(tx.monto) }}
              </td>
              <td style="text-align:right;">
                <div class="tx-item-actions" style="justify-content:flex-end;">
                  <button @click="openTxModal(tx)" class="tx-action edit" title="Editar" style="background:none;border:none;cursor:pointer;">
                    <svg viewBox="0 0 24 24" width="14" height="14"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 0 1 3.536 3.536L6.5 21.036H3v-3.572L16.732 3.732Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                  <button @click="handleDelete(tx.id)" class="tx-action del" title="Eliminar" style="background:none;border:none;cursor:pointer;">
                    <svg viewBox="0 0 24 24" width="14" height="14"><path d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
        
        <!-- Paginación -->
        <div class="pagination" style="display:flex; justify-content:center; gap:8px; margin-top:20px; padding-top:20px; border-top:1px solid var(--border);">
          <button class="btn-ghost btn-sm" :disabled="currentPage === 1" @click="setPage(currentPage - 1)">Anterior</button>
          
          <div style="display:flex; align-items:center; gap:4px;">
            <button 
              v-for="p in totalPages" :key="p"
              @click="setPage(p)"
              :class="['btn-sm', currentPage === p ? 'btn-accent' : 'btn-ghost']"
              style="min-width:32px; height:32px; padding:0; border-radius:6px; display:flex; align-items:center; justify-content:center;"
            >
              {{ p }}
            </button>
          </div>
          
          <button class="btn-ghost btn-sm" :disabled="currentPage === totalPages" @click="setPage(currentPage + 1)">Siguiente</button>
        </div>
        
      </div>
      
      <div v-else class="empty-state">
        <div class="empty-icon">📊</div>
        <p>No se encontraron movimientos.</p>
        <small>Usa el botón "Nuevo" para agregar uno o cambia los filtros.</small>
      </div>
    </article>
  </section>
</template>
