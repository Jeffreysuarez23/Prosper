<script setup>
import { ref, onMounted } from 'vue'
import api from '../services/api'
import Swal from 'sweetalert2'

const users = ref([])
const loading = ref(true)

const fetchUsers = async () => {
  try {
    const res = await api.get('/admin/users')
    users.value = res.data
  } catch (error) {
    console.error(error)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchUsers()
})

const getPlanName = (user) => {
  return user.membresia ? user.membresia.plan : 'gratis'
}

const getPlanBadgeClass = (plan) => {
  if (plan === 'ultra') return 'badge-ultra'
  if (plan === 'pro') return 'badge-pro'
  return 'badge-gratis'
}

const formatDate = (dateString) => {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleDateString()
}

// Edit User
const showEditModal = ref(false)
const editData = ref({ id: null, name: '', telefono: '', role_id: 2, password: '' })
const editEmail = ref('')

const openEdit = (user) => {
  editData.value = {
    id: user.id,
    name: user.name,
    telefono: user.telefono || '',
    role_id: user.role_id,
    password: ''
  }
  editEmail.value = user.email
  showEditModal.value = true
}

const saveUser = async () => {
  try {
    const payload = {
      name: editData.value.name,
      telefono: editData.value.telefono,
      role_id: parseInt(editData.value.role_id)
    }
    if (editData.value.password) {
      payload.password = editData.value.password
    }
    
    await api.put(`/admin/users/${editData.value.id}`, payload)
    
    showEditModal.value = false
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: 'Usuario actualizado',
      showConfirmButton: false,
      timer: 3000,
      background: 'var(--cc-surface)',
      color: 'var(--cc-text)'
    })
    fetchUsers()
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'No se pudo actualizar el usuario',
      background: 'var(--cc-surface)',
      color: 'var(--cc-text)'
    })
  }
}

// Create User
const showCreateModal = ref(false)
const createData = ref({ name: '', email: '', telefono: '', role_id: 2, password: '' })

const openCreate = () => {
  createData.value = { name: '', email: '', telefono: '', role_id: 2, password: '' }
  showCreateModal.value = true
}

const handleCreateUser = async () => {
  try {
    await api.post(`/admin/users`, createData.value)
    showCreateModal.value = false
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: 'success',
      title: 'Usuario creado',
      showConfirmButton: false,
      timer: 3000,
      background: 'var(--cc-surface)',
      color: 'var(--cc-text)'
    })
    fetchUsers()
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'No se pudo crear el usuario',
      background: 'var(--cc-surface)',
      color: 'var(--cc-text)'
    })
  }
}

// Delete User
const deleteUser = (user) => {
  Swal.fire({
    title: '¿Estás seguro?',
    html: `Eliminarás al usuario <b>${user.name}</b> permanentemente.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: 'transparent',
    confirmButtonText: 'Sí, eliminar',
    cancelButtonText: 'Cancelar',
    background: 'var(--cc-surface)',
    color: 'var(--cc-text)',
    customClass: {
      cancelButton: 'btn-ghost'
    }
  }).then(async (result) => {
    if (result.isConfirmed) {
      try {
        await api.delete(`/admin/users/${user.id}`)
        Swal.fire({
          toast: true,
          position: 'top-end',
          icon: 'success',
          title: 'Usuario eliminado',
          showConfirmButton: false,
          timer: 3000,
          background: 'var(--cc-surface)',
          color: 'var(--cc-text)'
        })
        fetchUsers()
      } catch (error) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: error.response?.data?.error || 'No se pudo eliminar el usuario',
          background: 'var(--cc-surface)',
          color: 'var(--cc-text)'
        })
      }
    }
  })
}
</script>

<template>
  <div class="dashboard-content">
    <div class="header-section" style="display: flex; justify-content: space-between; align-items: center;">
      <div>
        <h2>Gestión de Usuarios</h2>
        <p class="text-muted">Consulta y administra las cuentas registradas.</p>
      </div>
      <button class="btn-primary" @click="openCreate">
        + Crear Usuario
      </button>
    </div>

    <div class="table-card">
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Cargando usuarios...</p>
      </div>

      <div v-else class="table-responsive">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Usuario</th>
              <th>Teléfono</th>
              <th>Plan</th>
              <th>Rol</th>
              <th>Registro</th>
              <th style="text-align: right;">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="u in users" :key="u.id">
              <td class="text-muted">#{{ u.id }}</td>
              <td>
                <div style="font-weight: 500;">{{ u.name }}</div>
                <div class="text-muted" style="font-size: 0.85rem;">{{ u.email }}</div>
              </td>
              <td>{{ u.telefono || '-' }}</td>
              <td>
                <span class="badge" :class="getPlanBadgeClass(getPlanName(u))">
                  {{ getPlanName(u).toUpperCase() }}
                </span>
              </td>
              <td>
                <span class="badge" :class="u.role_id === 1 ? 'badge-admin' : 'badge-user'">
                  {{ u.role_id === 1 ? 'Admin' : 'Usuario' }}
                </span>
              </td>
              <td class="text-muted">{{ formatDate(u.created_at) }}</td>
              <td style="text-align: right;">
                <div style="display: flex; gap: 8px; justify-content: flex-end;">
                  <button class="btn-action" @click="openEdit(u)" title="Editar">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                  </button>
                  <RouterLink :to="'/usuarios/' + u.id + '/auditar'" class="btn-action btn-audit" title="Auditar Perfil">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                  </RouterLink>
                  <button class="btn-action btn-delete" @click="deleteUser(u)" title="Eliminar">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="users.length === 0">
              <td colspan="7" style="text-align: center; padding: 40px; color: var(--cc-text-muted);">No hay usuarios registrados.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- MODAL EDITAR USUARIO -->
    <div class="modal" :class="{ 'is-active': showEditModal }">
      <div class="modal-content">
        <div class="modal-head">
          <div class="head-text">
            <h2>Editar Usuario</h2>
            <p>Modifica la información del usuario</p>
          </div>
          <button class="modal-close" @click="showEditModal = false" aria-label="Cerrar">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
          </button>
        </div>
        <form @submit.prevent="saveUser">
          <div class="form-group">
            <label>Correo Electrónico (No editable)</label>
            <input type="email" class="form-control" v-model="editEmail" disabled style="opacity: 0.6; cursor: not-allowed;">
          </div>
          <div class="form-group">
            <label>Nombre Completo</label>
            <input type="text" class="form-control" v-model="editData.name" required>
          </div>
          <div class="form-group">
            <label>Teléfono</label>
            <input type="text" class="form-control" v-model="editData.telefono">
          </div>
          <div class="form-group">
            <label>Rol del Sistema</label>
            <select class="form-control" v-model="editData.role_id">
              <option :value="2">Usuario Normal</option>
              <option :value="1">Administrador</option>
            </select>
          </div>
          <div class="form-group">
            <label>Nueva Contraseña (Dejar en blanco para no cambiar)</label>
            <input type="password" class="form-control" v-model="editData.password" placeholder="••••••••">
          </div>
          <div class="form-actions" style="margin-top: 24px;">
            <button type="button" class="btn-ghost" @click="showEditModal = false">Cancelar</button>
            <button type="submit" class="btn-primary">Guardar Cambios</button>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL CREAR USUARIO -->
    <div class="modal" :class="{ 'is-active': showCreateModal }">
      <div class="modal-content">
        <div class="modal-head">
          <div class="head-text">
            <h2>Crear Usuario</h2>
            <p>Añade un nuevo usuario al sistema</p>
          </div>
          <button class="modal-close" @click="showCreateModal = false" aria-label="Cerrar">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
          </button>
        </div>
        <form @submit.prevent="handleCreateUser">
          <div class="form-group">
            <label>Correo Electrónico</label>
            <input type="email" class="form-control" v-model="createData.email" required>
          </div>
          <div class="form-group">
            <label>Nombre Completo</label>
            <input type="text" class="form-control" v-model="createData.name" required>
          </div>
          <div class="form-group">
            <label>Teléfono</label>
            <input type="text" class="form-control" v-model="createData.telefono">
          </div>
          <div class="form-group">
            <label>Rol del Sistema</label>
            <select class="form-control" v-model="createData.role_id">
              <option :value="2">Usuario Normal</option>
              <option :value="1">Administrador</option>
            </select>
          </div>
          <div class="form-group">
            <label>Contraseña</label>
            <input type="password" class="form-control" v-model="createData.password" required placeholder="••••••••">
          </div>
          <div class="form-actions" style="margin-top: 24px;">
            <button type="button" class="btn-ghost" @click="showCreateModal = false">Cancelar</button>
            <button type="submit" class="btn-primary">Crear Usuario</button>
          </div>
        </form>
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
.table-card {
  background: var(--cc-surface);
  border: 1px solid var(--cc-border);
  border-radius: 16px;
  overflow: hidden;
}
.table-responsive {
  overflow-x: auto;
}
.data-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}
.data-table th, .data-table td {
  padding: 16px 24px;
  border-bottom: 1px solid var(--cc-border);
}
.data-table th {
  background: rgba(0,0,0,0.1);
  font-weight: 600;
  color: var(--cc-text-muted);
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.data-table tr:last-child td {
  border-bottom: none;
}
.badge {
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: bold;
}
.badge-gratis {
  background: var(--cc-surface);
  color: var(--cc-text-muted);
  border: 1px solid var(--cc-border);
}
.badge-pro {
  background: rgba(79, 211, 168, 0.15);
  color: #4fd3a8;
  border: 1px solid rgba(79, 211, 168, 0.3);
}
.badge-ultra {
  background: rgba(168, 85, 247, 0.15);
  color: #a855f7;
  border: 1px solid rgba(168, 85, 247, 0.3);
}
.badge-admin {
  background: rgba(239, 68, 68, 0.15);
  color: #ef4444;
  border: 1px solid rgba(239, 68, 68, 0.3);
}
.badge-user {
  background: var(--cc-surface);
  color: var(--cc-text);
  border: 1px solid var(--cc-border);
}
.btn-action {
  background: var(--cc-surface);
  border: 1px solid var(--cc-border);
  color: var(--cc-text);
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s;
}
.btn-action:hover {
  background: var(--cc-accent);
  color: white;
  border-color: var(--cc-accent);
}
.btn-audit:hover {
  background: #a855f7;
  border-color: #a855f7;
}
.btn-delete:hover {
  background: #ef4444;
  border-color: #ef4444;
}

/* Modal styles (similar to main app) */
.modal {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.6);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  opacity: 0;
  visibility: hidden;
  transition: all 0.3s ease;
}
.modal.is-active {
  opacity: 1;
  visibility: visible;
}
.modal-content {
  background: var(--cc-bg);
  border: 1px solid var(--cc-border);
  border-radius: 20px;
  width: 100%;
  max-width: 500px;
  padding: 30px;
  transform: translateY(20px);
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
}
.modal.is-active .modal-content {
  transform: translateY(0);
}
.modal-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
}
.modal-head h2 { margin: 0 0 4px 0; font-size: 1.4rem; }
.modal-head p { margin: 0; color: var(--cc-text-muted); font-size: 0.9rem; }
.modal-close {
  background: var(--cc-surface);
  border: none;
  color: var(--cc-text-muted);
  width: 32px; height: 32px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
}
.modal-close:hover { color: var(--cc-text); background: rgba(255,255,255,0.1); }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; margin-bottom: 8px; font-size: 0.85rem; color: var(--cc-text-muted); font-weight: 500; }
.form-control { width: 100%; padding: 12px 16px; background: var(--cc-surface); border: 1px solid var(--cc-border); color: var(--cc-text); border-radius: 10px; font-size: 0.95rem; }
.form-control:focus { outline: none; border-color: var(--cc-accent); }
.form-actions { display: flex; gap: 12px; justify-content: flex-end; }
.btn-primary { background: var(--cc-accent); color: white; border: none; padding: 12px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; }
.btn-ghost { background: transparent; border: 1px solid var(--cc-border); color: var(--cc-text); padding: 12px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; }

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
