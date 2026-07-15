<script setup>
import { ref, computed, onMounted, inject } from 'vue'
import api from '../services/api'
import Swal from 'sweetalert2'

const refreshHeaderBalance = inject('refreshHeaderBalance')
const headerBalance = inject('headerBalance')

const data = ref([])
const loading = ref(true)

const searchQuery = ref('')
const statusFilter = ref('')

const fetchData = async () => {
  loading.value = true
  try {
    const params = {
      q: searchQuery.value,
      status: statusFilter.value
    }
    const res = await api.get('/gastos-fijos', { params })
    data.value = res.data
  } catch (error) {
    console.error("Error cargando gastos fijos", error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchData()
})

const getStatusInfo = (g) => {
  const currentMonth = new Date().toISOString().substring(0, 7)
  const lastPaidMonth = g.fecha_ultimo_pago ? g.fecha_ultimo_pago.substring(0, 7) : ''
  const pagado = lastPaidMonth === currentMonth ? parseFloat(g.monto_pagado_mes || 0) : 0
  const isPaid = pagado >= parseFloat(g.monto)
  
  const todayDay = new Date().getDate()
  const diff = parseInt(g.dia_vencimiento) - todayDay
  const pct = Math.min(100, Math.round((pagado / parseFloat(g.monto)) * 100) || 0)

  let statusClass = 'is-pending'
  let statusBadgeClass = 'status-pending'
  let statusText = '➖ Pendiente'
  let statusCode = 'pending'

  if (isPaid) {
    statusClass = 'is-paid'
    statusBadgeClass = 'status-paid'
    statusText = '✅ Pagado'
    statusCode = 'paid'
  } else {
    if (diff < 0) {
      statusClass = 'is-urgent'
      statusBadgeClass = 'status-urgent'
      statusText = diff === -1 ? '⚠️ Venció ayer' : '⚠️ Pasado/Atrasado'
      statusCode = 'urgent'
    } else if (diff <= 3) {
      statusClass = 'is-urgent'
      statusBadgeClass = 'status-urgent'
      if (diff === 0) {
        statusText = '⚠️ Vence hoy'
      } else if (diff === 1) {
        statusText = '⚠️ Vence mañana'
      } else {
        statusText = '⚠️ Vence pronto'
      }
      statusCode = 'urgent'
    }
  }

  let dueDateText = `Vence el día ${g.dia_vencimiento}`
  if (diff === 0) {
    dueDateText = `Vence hoy ${g.dia_vencimiento}`
  } else if (diff === 1) {
    dueDateText = `Vence mañana ${g.dia_vencimiento}`
  } else if (diff === -1) {
    dueDateText = `Venció ayer ${g.dia_vencimiento}`
  } else if (diff < -1) {
    dueDateText = `Venció el día ${g.dia_vencimiento}`
  }

  return { pagado, isPaid, pct, statusClass, statusBadgeClass, statusText, statusCode, dueDateText }
}

const filteredData = computed(() => {
  return data.value.filter(item => {
    let match = true
    if (statusFilter.value) {
      const info = getStatusInfo(item)
      match = match && info.statusCode === statusFilter.value
    }
    return match
  })
})

const totalGastos = computed(() => data.value.reduce((sum, item) => sum + parseFloat(item.monto), 0))
const totalRegistrados = computed(() => data.value.length)
const todayString = computed(() => {
  const d = new Date()
  return `${d.getDate().toString().padStart(2,'0')}/${(d.getMonth()+1).toString().padStart(2,'0')}/${d.getFullYear()}`
})

const formatCurrency = (val) => {
  return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 2 }).format(val || 0)
}

const isSubmitting = ref(false)

// Modals State
const showExpenseModal = ref(false)
const expenseEditId = ref(null)
const expenseData = ref({
  nombre: '',
  monto: '',
  dia_vencimiento: 1,
  icono: '🏠'
})
const displayExpenseMonto = ref('')

const showDepositModal = ref(false)
const depositData = ref({
  id: null,
  nombre: '',
  monto: 0,
  monto_pagado_mes: 0,
  abono: ''
})
const displayDepositAbono = ref('')

const emojis = ['🏠', '💧', '⚡', '📶', '📱', '📺', '🚗', '🛒', '💳', '🎓', '🏥', '🛡️', '🐾', '⚽', '🎯']

// Formatters
const formatExpenseMonto = (e) => {
  let rawValue = e.target.value.replace(/\D/g, '')
  if (!rawValue) {
    displayExpenseMonto.value = ''
    expenseData.value.monto = ''
    return
  }
  displayExpenseMonto.value = new Intl.NumberFormat('es-CO').format(parseInt(rawValue))
  expenseData.value.monto = rawValue
  e.target.value = displayExpenseMonto.value
}

const formatInputDeposit = (e) => {
  let rawValue = e.target.value.replace(/\D/g, '')
  if (!rawValue) {
    displayDepositAbono.value = ''
    depositData.value.abono = ''
    return
  }
  
  let numericValue = parseInt(rawValue)
  const remaining = depositData.value.monto - depositData.value.monto_pagado_mes
  const maxAllowed = Math.min(remaining, headerBalance.value)
  
  if (numericValue > maxAllowed) {
    numericValue = maxAllowed
    Swal.fire({
      toast: true,
      position: 'bottom-end',
      icon: 'error',
      title: numericValue === remaining ? 'Supera el monto del gasto' : 'Saldo insuficiente',
      showConfirmButton: false,
      timer: 3000,
      background: 'var(--surface)',
      color: 'var(--text)',
      customClass: { popup: 'swal-custom-popup', title: 'swal-custom-title' }
    })
  }

  displayDepositAbono.value = new Intl.NumberFormat('es-CO').format(numericValue)
  depositData.value.abono = numericValue
  e.target.value = displayDepositAbono.value
}

// Actions
const openNewExpense = () => {
  expenseEditId.value = null
  expenseData.value = { nombre: '', monto: '', dia_vencimiento: 1, icono: '🏠' }
  displayExpenseMonto.value = ''
  showExpenseModal.value = true
}

const openEditExpense = (g) => {
  expenseEditId.value = g.id
  expenseData.value = { 
    nombre: g.nombre, 
    monto: g.monto, 
    dia_vencimiento: g.dia_vencimiento,
    icono: g.icono || '🏠'
  }
  displayExpenseMonto.value = new Intl.NumberFormat('es-CO').format(g.monto)
  showExpenseModal.value = true
}

const openExpenseDeposit = (g) => {
  const info = getStatusInfo(g)
  depositData.value = {
    id: g.id,
    nombre: g.nombre,
    monto: parseFloat(g.monto),
    monto_pagado_mes: info.pagado,
    abono: ''
  }
  displayDepositAbono.value = ''
  showDepositModal.value = true
}

const saveExpense = async () => {
  if (!expenseData.value.nombre || !expenseData.value.monto || !expenseData.value.dia_vencimiento) return
  
  isSubmitting.value = true
  try {
    if (expenseEditId.value) {
      await api.put(`/gastos-fijos/${expenseEditId.value}`, expenseData.value)
    } else {
      await api.post('/gastos-fijos', expenseData.value)
    }
    showExpenseModal.value = false
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: 'Gasto guardado exitosamente',
      showConfirmButton: false,
      timer: 3000,
      background: 'var(--surface)',
      color: 'var(--text)'
    })
    fetchData()
    if (refreshHeaderBalance) refreshHeaderBalance()
  } catch (error) {
    console.error(error)
    if (error.response && error.response.status === 403 && error.response.data.message) {
      // The global api.js interceptor will handle this and show the alert

    } else {
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'error',
        title: 'Error al guardar el gasto',
        showConfirmButton: false,
        timer: 3000,
        background: 'var(--surface)',
        color: 'var(--text)'
      })
    }
  } finally {
    isSubmitting.value = false
  }
}

const saveDeposit = async () => {
  const abono = parseFloat(depositData.value.abono) || 0
  if (abono <= 0) return

  if (abono > headerBalance.value) {
    Swal.fire({
      icon: 'warning',
      title: 'Balance insuficiente',
      text: 'No tienes suficiente balance para este abono.',
      confirmButtonText: 'Entendido',
      confirmButtonColor: 'var(--accent)',
      customClass: { popup: 'swal-custom-popup', title: 'swal-custom-title', htmlContainer: 'swal-custom-content', confirmButton: 'swal-custom-confirm' }
    })
    return
  }

  const restante = depositData.value.monto - depositData.value.monto_pagado_mes
  if (abono > restante) return

  const newMontoActual = depositData.value.monto_pagado_mes + abono

  isSubmitting.value = true
  try {
    await api.post(`/gastos-fijos/${depositData.value.id}/abono`, { abono })
    showDepositModal.value = false
    
    // Alerta especial si completó el pago
    if (newMontoActual >= depositData.value.monto) {
      setTimeout(() => {
        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'success',
          title: `¡Pago completado! 🎉`,
          showConfirmButton: false,
          timer: 3000,
          background: 'var(--surface)',
          color: 'var(--text)'
        })
      }, 500)
    } else {
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Abono registrado',
        showConfirmButton: false,
        timer: 3000,
        background: 'var(--surface)',
        color: 'var(--text)'
      })
    }
    
    fetchData()
    if (refreshHeaderBalance) refreshHeaderBalance()
  } catch (error) {
    console.error(error)
  } finally {
    isSubmitting.value = false
  }
}

const deleteExpense = async (id) => {
  const result = await Swal.fire({
    title: '¿Eliminar Gasto Fijo?',
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
    cancelButtonText: 'Cancelar',
  })

  if (result.isConfirmed) {
    try {
      await api.delete(`/gastos-fijos/${id}`)
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Gasto eliminado',
        showConfirmButton: false,
        timer: 3000,
        background: 'var(--surface)',
        color: 'var(--text)'
      })
      fetchData()
      if (refreshHeaderBalance) refreshHeaderBalance()
    } catch (error) {
      console.error(error)
    }
  }
}
</script>

<template>
  <div v-if="loading" style="padding: 20px;">Cargando gastos fijos...</div>
  
  <section v-else class="page-section">
    <!-- Summary -->
    <div class="mini-stats">
      <div class="mini-stat ms-income">
        <span class="ms-label">Fecha</span>
        <strong class="ms-value" style="color: var(--green);">{{ todayString }}</strong>
      </div>
      <div class="mini-stat ms-balance">
        <span class="ms-label">Gastos Registrados</span>
        <strong class="ms-value" style="color: #3b82f6;">{{ totalRegistrados }}</strong>
      </div>
      <div class="mini-stat ms-expense">
        <span class="ms-label">Total Gastos Fijos</span>
        <strong class="ms-value" style="color: var(--red);">{{ formatCurrency(totalGastos).replace('COP', '').trim() }}</strong>
      </div>
    </div>

    <div class="goals-header" style="flex-wrap: wrap; gap: 16px;">
      <form @submit.prevent="fetchData" style="display:flex; gap:12px; align-items:center; flex:1; min-width: 0;">
        <div class="search-wrap" style="position:relative; flex:1;">
          <svg class="search-icon" viewBox="0 0 24 24" width="16" height="16" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted);">
            <circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="1.8" />
            <path d="M21 21l-4.3-4.3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
          </svg>
          <input type="search" v-model="searchQuery" @input="fetchData" placeholder="Buscar gastos..." style="width:100%; padding-left:36px; height:40px; border-radius:8px; border:1px solid var(--border);">
        </div>
        <select v-model="statusFilter" style="height:40px; border-radius:8px; border:1px solid var(--border); padding:0 12px; background:var(--surface); color: white;">
          <option value="">Todos los estados</option>
          <option value="pending">Pendientes / Atrasados</option>
          <option value="paid">Pagados</option>
        </select>
      </form>

      <button class="btn-accent" @click="openNewExpense">
        <svg viewBox="0 0 24 24" width="16" height="16">
          <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
        <span>Nuevo Gasto Fijo</span>
      </button>
    </div>

    <div v-if="filteredData.length > 0" class="goals-grid" id="expensesGrid">
      <div v-for="g in filteredData" :key="g.id" :class="['expense-card', getStatusInfo(g).statusClass]">
        <div :class="['expense-status-badge', getStatusInfo(g).statusBadgeClass]">{{ getStatusInfo(g).statusText }}</div>
        
        <div style="display:flex; gap:16px; align-items:center; margin-bottom:16px;">
          <div class="expense-emoji" style="color:var(--text); border-color:var(--border);">{{ g.icono || '🏠' }}</div>
          <div>
            <h3 style="font-size:1.05rem; font-weight:600; color:var(--text);">{{ g.nombre }}</h3>
            <p style="font-size:0.85rem; color:var(--text-muted);">{{ getStatusInfo(g).dueDateText }}</p>
          </div>
        </div>
        
        <div class="progress"><span :style="{ width: getStatusInfo(g).pct + '%' }"></span></div>
        
        <div class="goal-numbers" style="align-items: flex-end; margin-top: 12px; margin-bottom: 16px;">
          <div style="display: flex; flex-direction: column; gap: 4px;">
            <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Dinero pagado</span>
            <span class="goal-saved">{{ formatCurrency(getStatusInfo(g).pagado).replace('COP', '').trim() }}</span>
          </div>
          <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
            <span style="font-size: 0.72rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px;">Cantidad Total</span>
            <span class="goal-target">{{ formatCurrency(g.monto).replace('COP', '').trim() }}</span>
          </div>
        </div>
        
        <div class="goal-actions">
          <button v-if="!getStatusInfo(g).isPaid" class="goal-btn primary" @click="openExpenseDeposit(g)" style="background:var(--accent); color:white; border:none;">Abonar Gasto</button>
          <button v-else class="goal-btn" style="color:var(--green); border-color:var(--green); font-weight:600; pointer-events:none;">Pagado este mes</button>
          <button class="goal-btn" @click="openEditExpense(g)">Editar</button>
          <button class="goal-btn danger" @click="deleteExpense(g.id)">Eliminar</button>
        </div>
      </div>
    </div>
    
    <div v-else class="empty-state">
      <div class="empty-icon">🏠</div>
      <p>No se encontraron gastos fijos.</p>
      <small>Usa el botón "Nuevo" para agregar uno.</small>
    </div>
  </section>

  <!-- Modal Nuevo/Editar Gasto -->
  <div v-if="showExpenseModal" class="modal" style="display: flex;">
    <div class="modal-content">
      <div class="modal-head premium-head">
        <div class="head-icon">🏠</div>
        <div class="head-text">
          <h2>{{ expenseEditId ? 'Editar Gasto Fijo' : 'Nuevo Gasto Fijo' }}</h2>
          <p>{{ expenseEditId ? 'Actualiza los detalles del gasto' : 'Registra un pago recurrente' }}</p>
        </div>
        <button class="modal-close" @click="showExpenseModal = false" aria-label="Cerrar">
          <svg viewBox="0 0 24 24" width="20" height="20">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
        </button>
      </div>
      <form @submit.prevent="saveExpense" autocomplete="off">
        <div class="form-group">
          <label>Nombre del Servicio/Gasto</label>
          <input type="text" v-model="expenseData.nombre" required placeholder="Ej: Internet, Alquiler...">
        </div>
        <div class="form-row">
          <div class="form-group form-half">
            <label>Monto a pagar</label>
            <input type="text" class="form-control" :value="displayExpenseMonto" @input="formatExpenseMonto" required placeholder="0.00">
          </div>
          <div class="form-group form-half">
            <label>Día de vencimiento</label>
            <input type="number" class="form-control" v-model.number="expenseData.dia_vencimiento" min="1" max="31" required placeholder="Ej: 15">
          </div>
        </div>
        <div class="form-group">
          <label>Icono</label>
          <div class="emoji-grid">
            <button 
              type="button" 
              v-for="emoji in emojis" 
              :key="emoji" 
              class="emoji-btn" 
              :class="{'is-active': expenseData.icono === emoji}"
              @click="expenseData.icono = emoji"
            >
              {{ emoji }}
            </button>
          </div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn-ghost" @click="showExpenseModal = false">Cancelar</button>
          <button type="submit" class="btn-accent" :disabled="isSubmitting">Guardar Gasto</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal Abonar/Pagar -->
  <div v-if="showDepositModal" class="modal" style="display: flex;">
    <div class="modal-content" style="max-width:400px; text-align:center; padding-top:32px;">
      <div class="modal-head premium-head" style="margin-bottom:16px;">
        <div class="head-icon" style="color:var(--accent);">💵</div>
        <div class="head-text" style="text-align:left;">
          <h2>Abonar Gasto</h2>
          <p>
            {{ depositData.nombre }} — Pagado: {{ formatCurrency(depositData.monto_pagado_mes).replace('COP', '').trim() }} de {{ formatCurrency(depositData.monto).replace('COP', '').trim() }}
          </p>
        </div>
        <button class="modal-close" @click="showDepositModal = false" aria-label="Cerrar">
          <svg viewBox="0 0 24 24" width="20" height="20">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
          </svg>
        </button>
      </div>
      <form @submit.prevent="saveDeposit" autocomplete="off">
        <div class="form-group" style="text-align:left;">
          <label>Monto a abonar</label>
          <input type="text" class="form-control" :value="displayDepositAbono" @input="formatInputDeposit" required placeholder="0.00">
        </div>
        <div class="form-actions" style="margin-top:24px;">
          <button type="button" class="btn-ghost" @click="showDepositModal = false">Cancelar</button>
          <button type="submit" class="btn-accent" style="background:var(--accent);" :disabled="isSubmitting">Pagar</button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.expense-card {
  background:var(--surface); border:1px solid var(--border);
  border-radius:var(--radius-lg); padding:22px;
  box-shadow:var(--shadow-card); transition:transform .2s, box-shadow .2s;
  position:relative; overflow:hidden;
}
.expense-card:hover { transform:translateY(-2px); box-shadow:0 8px 24px rgba(0,0,0,.12); }
.expense-card.is-paid { border-left: 4px solid var(--green); background: linear-gradient(90deg, rgba(34,197,94,0.08) 0%, var(--surface) 100%); }
.expense-card.is-pending { border-left: 4px solid var(--amber-500); background: linear-gradient(90deg, rgba(245,158,11,0.08) 0%, var(--surface) 100%); }
.expense-card.is-urgent { border-left: 4px solid var(--red); background: linear-gradient(90deg, rgba(239,68,68,0.08) 0%, var(--surface) 100%); }

.expense-emoji {
  width:48px; height:48px; border-radius:14px; flex-shrink:0;
  background:var(--surface-2); display:grid; place-items:center;
  font-size:1.5rem; border:1px solid var(--border);
}
.expense-status-badge {
  position:absolute; top:16px; right:18px;
  font-family:var(--font-mono); font-weight:700; font-size:.75rem;
  padding:4px 10px; border-radius:999px;
}
.status-paid { background: rgba(34,197,94,.12); color: var(--green); }
.status-pending { background: rgba(245,158,11,.12); color: var(--amber-500); }
.status-urgent { background: rgba(239,68,68,.12); color: var(--red); }

.goal-actions { margin-top:16px; display:flex; gap:8px; }
.goal-btn { flex:1; padding:8px 0; border-radius:var(--radius-md); font-size:.8rem; font-weight:600; cursor:pointer; background:transparent; border:1px solid var(--border); color:var(--text); transition:all .2s; }
.goal-btn:hover { background:var(--surface-2); }
.goal-btn.danger { color:var(--red); border-color:var(--red); }
.goal-btn.danger:hover { background:var(--red); color:white; }

/* ── Responsive Expense Cards ── */
@media (max-width: 920px) {
  .expense-card { padding: 18px; }
  .expense-emoji { width: 42px; height: 42px; font-size: 1.3rem; border-radius: 12px; }
  .expense-status-badge { font-size: .7rem; top: 12px; right: 14px; padding: 3px 8px; }
  .goal-actions { flex-wrap: wrap; }
  .goal-btn { min-width: calc(50% - 4px); }
}

@media (max-width: 560px) {
  .expense-card { padding: 14px; }
  .expense-emoji { width: 36px; height: 36px; font-size: 1.1rem; border-radius: 10px; }
  .expense-status-badge { font-size: .65rem; top: 10px; right: 12px; }
  .goal-btn { font-size: .75rem; padding: 6px 0; }
}

</style>
