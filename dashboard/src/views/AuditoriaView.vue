<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '../services/api'

const route = useRoute()
const router = useRouter()
const userId = route.params.id

const data = ref(null)
const loading = ref(true)
const activeTab = ref('movimientos')

const fetchData = async () => {
  try {
    const res = await api.get(`/admin/users/${userId}/audit`)
    data.value = res.data
  } catch (error) {
    console.error(error)
    router.push('/usuarios')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchData()
})

const formatDate = (dateString) => {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString()
}
</script>

<template>
  <div class="dashboard-content">
    <button @click="router.push('/usuarios')" class="back-btn">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
      Volver al Directorio
    </button>

    <div v-if="loading" class="loading-state">
      <div class="spinner"></div>
      <p>Cargando perfil de auditoría...</p>
    </div>

    <div v-else-if="data" class="audit-container">
      <!-- User Profile Header -->
      <div class="profile-header">
        <div class="profile-avatar">
          {{ data.user.name.charAt(0).toUpperCase() }}
        </div>
        <div class="profile-info">
          <h2>{{ data.user.name }}</h2>
          <p class="text-muted">{{ data.user.email }} | Tel: {{ data.user.telefono || 'N/A' }}</p>
        </div>
        <div class="profile-plan">
          <span class="badge" :class="data.user.membresia?.plan === 'ultra' ? 'badge-ultra' : (data.user.membresia?.plan === 'pro' ? 'badge-pro' : 'badge-gratis')">
            {{ (data.user.membresia?.plan || 'GRATIS').toUpperCase() }}
          </span>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="audit-tabs">
        <button :class="{ active: activeTab === 'movimientos' }" @click="activeTab = 'movimientos'">Movimientos ({{ data.movimientos.length }})</button>
        <button :class="{ active: activeTab === 'objetivos' }" @click="activeTab = 'objetivos'">Objetivos ({{ data.objetivos.length }})</button>
        <button :class="{ active: activeTab === 'tarjetas' }" @click="activeTab = 'tarjetas'">Tarjetas ({{ data.tarjetas_credito.length }})</button>
        <button :class="{ active: activeTab === 'gastos' }" @click="activeTab = 'gastos'">Gastos Fijos ({{ data.gastos_fijos.length }})</button>
      </div>

      <!-- Tab Content -->
      <div class="tab-content">
        
        <!-- Movimientos -->
        <div v-if="activeTab === 'movimientos'" class="table-card">
          <table class="data-table">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Categoría</th>
                <th>Método de Pago</th>
                <th>Tipo</th>
                <th style="text-align: right;">Monto</th>
                <th>Detalles</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in data.movimientos" :key="m.id">
                <td>{{ formatDate(m.fecha) }}</td>
                <td>{{ m.categoria }}</td>
                <td style="text-transform: capitalize;">{{ m.metodo_pago }}</td>
                <td>
                  <span :class="m.tipo === 'ingreso' ? 'text-success' : 'text-danger'">
                    {{ m.tipo === 'ingreso' ? 'Ingreso' : 'Gasto' }}
                  </span>
                </td>
                <td style="text-align: right; color: var(--cc-text-muted); font-family: monospace;">{{ m.monto }}</td>
                <td style="color: var(--cc-text-muted);">
                  <span class="privacy-lock">🔒 {{ m.descripcion }}</span>
                </td>
              </tr>
              <tr v-if="data.movimientos.length === 0">
                <td colspan="6" style="text-align: center; padding: 40px; color: var(--cc-text-muted);">No hay movimientos registrados.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Objetivos -->
        <div v-if="activeTab === 'objetivos'" class="table-card">
          <table class="data-table">
            <thead>
              <tr>
                <th>Nombre</th>
                <th>Fecha Límite</th>
                <th>Estado</th>
                <th style="text-align: right;">Meta / Ahorrado</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="o in data.objetivos" :key="o.id">
                <td><strong>{{ o.nombre }}</strong></td>
                <td>{{ formatDate(o.fecha_limite) }}</td>
                <td>
                  <span class="badge" :class="o.completado ? 'badge-pro' : 'badge-gratis'">
                    {{ o.completado ? 'Completado' : 'En Progreso' }}
                  </span>
                </td>
                <td style="text-align: right; color: var(--cc-text-muted); font-family: monospace;">
                  🔒 {{ o.monto_ahorrado }} / {{ o.monto_objetivo }}
                </td>
              </tr>
              <tr v-if="data.objetivos.length === 0">
                <td colspan="4" style="text-align: center; padding: 40px; color: var(--cc-text-muted);">No hay objetivos registrados.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Tarjetas -->
        <div v-if="activeTab === 'tarjetas'" class="table-card">
          <table class="data-table">
            <thead>
              <tr>
                <th>Nombre / Banco</th>
                <th>Fechas</th>
                <th style="text-align: right;">Límite / Deuda</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in data.tarjetas_credito" :key="t.id">
                <td><strong>{{ t.nombre }}</strong></td>
                <td>
                  <div style="font-size: 0.85rem; color: var(--cc-text-muted);">
                    Corte: Día {{ t.dia_corte }} <br>
                    Pago: Día {{ t.dia_pago }}
                  </div>
                </td>
                <td style="text-align: right; color: var(--cc-text-muted); font-family: monospace;">
                  🔒 {{ t.deuda_actual }} / {{ t.limite_credito }}
                </td>
              </tr>
              <tr v-if="data.tarjetas_credito.length === 0">
                <td colspan="3" style="text-align: center; padding: 40px; color: var(--cc-text-muted);">No hay tarjetas registradas.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Gastos Fijos -->
        <div v-if="activeTab === 'gastos'" class="table-card">
          <table class="data-table">
            <thead>
              <tr>
                <th>Nombre / Servicio</th>
                <th>Día de Vencimiento</th>
                <th>Último Pago</th>
                <th style="text-align: right;">Monto</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="g in data.gastos_fijos" :key="g.id">
                <td><strong>{{ g.nombre }}</strong></td>
                <td>Día {{ g.dia_vencimiento }} de cada mes</td>
                <td>{{ formatDate(g.fecha_ultimo_pago) }}</td>
                <td style="text-align: right; color: var(--cc-text-muted); font-family: monospace;">
                  <span class="privacy-lock">🔒 {{ g.monto }}</span>
                </td>
              </tr>
              <tr v-if="data.gastos_fijos.length === 0">
                <td colspan="4" style="text-align: center; padding: 40px; color: var(--cc-text-muted);">No hay gastos fijos registrados.</td>
              </tr>
            </tbody>
          </table>
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
.back-btn {
  background: transparent;
  border: none;
  color: var(--cc-accent);
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.95rem;
  font-weight: 500;
  cursor: pointer;
  padding: 0;
  margin-bottom: 24px;
}
.back-btn:hover {
  text-decoration: underline;
}
.audit-container {
  display: flex;
  flex-direction: column;
  gap: 24px;
}
.profile-header {
  display: flex;
  align-items: center;
  gap: 24px;
  background: var(--cc-surface);
  border: 1px solid var(--cc-border);
  border-radius: 16px;
  padding: 32px;
}
.profile-avatar {
  width: 80px;
  height: 80px;
  border-radius: 20px;
  background: linear-gradient(135deg, #3b82f6, #a855f7);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
  font-weight: bold;
  color: white;
  box-shadow: 0 8px 24px rgba(168, 85, 247, 0.2);
}
.profile-info {
  flex: 1;
}
.profile-info h2 {
  margin: 0 0 4px 0;
  font-size: 1.8rem;
}
.profile-info p {
  margin: 0;
  font-size: 0.95rem;
}
.audit-tabs {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid var(--cc-border);
  padding-bottom: 8px;
}
.audit-tabs button {
  background: transparent;
  border: none;
  color: var(--cc-text-muted);
  padding: 12px 24px;
  font-weight: 600;
  font-size: 0.95rem;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;
}
.audit-tabs button:hover {
  background: rgba(255, 255, 255, 0.05);
  color: var(--cc-text);
}
.audit-tabs button.active {
  background: rgba(96, 165, 250, 0.15);
  color: var(--cc-accent);
}
.table-card {
  background: var(--cc-surface);
  border: 1px solid var(--cc-border);
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}
.data-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
  text-align: left;
}
.data-table th, .data-table td {
  padding: 18px 24px;
  border-bottom: 1px solid var(--cc-border);
}
.data-table th {
  background: rgba(0,0,0,0.4);
  font-weight: 700;
  color: #fff;
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 1px;
}
.data-table tbody tr {
  transition: all 0.2s ease;
}
.data-table tbody tr:hover {
  background: rgba(255,255,255,0.03);
}
.data-table tr:last-child td {
  border-bottom: none;
}
.text-success { color: #4fd3a8; font-weight: 600; text-shadow: 0 0 10px rgba(79, 211, 168, 0.2); }
.text-danger { color: #ef4444; font-weight: 600; text-shadow: 0 0 10px rgba(239, 68, 68, 0.2); }
.privacy-lock {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: rgba(255,255,255,0.08);
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 0.85rem;
  font-weight: 500;
  border: 1px solid rgba(255,255,255,0.1);
  box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
}
.badge {
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: bold;
}
.badge-gratis { background: rgba(255,255,255,0.1); color: var(--cc-text); }
.badge-pro { background: rgba(79, 211, 168, 0.15); color: #4fd3a8; }
.badge-ultra { background: rgba(168, 85, 247, 0.15); color: #a855f7; }

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
</style>
