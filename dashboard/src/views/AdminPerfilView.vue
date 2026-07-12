<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'
import Swal from 'sweetalert2'

const router = useRouter()
const user = ref({ name: '', email: '', telefono: '' })
const passwords = ref({ current_password: '', new_password: '', new_password_confirmation: '' })
const isEditing = ref(false)

onMounted(async () => {
  try {
    const res = await api.get('/user')
    user.value = res.data
  } catch (error) {
    console.error(error)
  }
})

const saveProfile = async () => {
  try {
    await api.put(`/admin/users/${user.value.id}`, {
      name: user.value.name,
      telefono: user.value.telefono,
      role_id: user.value.role_id,
      // Passwords
      ...(passwords.value.new_password ? { password: passwords.value.new_password } : {})
    })
    
    localStorage.setItem('user', JSON.stringify(user.value))
    
    Swal.fire({
      icon: 'success',
      title: '¡Actualizado!',
      text: 'Tu perfil ha sido actualizado correctamente.',
      background: 'var(--cc-surface)',
      color: 'white',
      confirmButtonColor: 'var(--cc-accent)'
    })
    
    passwords.value = { current_password: '', new_password: '', new_password_confirmation: '' }
    isEditing.value = false
    
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Hubo un error al actualizar el perfil.',
      background: 'var(--cc-surface)',
      color: 'white',
      confirmButtonColor: '#ef4444'
    })
  }
}
</script>

<template>
  <div class="dashboard-content">
    <div class="header-section">
      <h1 class="page-title">Mi Perfil Administrativo</h1>
      <p class="page-subtitle">Actualiza tu información personal y credenciales de acceso.</p>
    </div>

    <div class="profile-container">
      <div class="profile-card">
        <div class="card-header">
          <h2>Datos Personales</h2>
          <button @click="isEditing = !isEditing" class="action-btn outline-btn">
            {{ isEditing ? 'Cancelar' : 'Editar' }}
          </button>
        </div>
        
        <form @submit.prevent="saveProfile" class="profile-form">
          <div class="form-group">
            <label>Nombre Completo</label>
            <input type="text" v-model="user.name" class="form-control" :disabled="!isEditing" required />
          </div>
          <div class="form-group">
            <label>Correo Electrónico <span class="hint">(No se puede cambiar)</span></label>
            <input type="email" v-model="user.email" class="form-control" disabled />
          </div>
          <div class="form-group">
            <label>Teléfono</label>
            <input type="text" v-model="user.telefono" class="form-control" :disabled="!isEditing" />
          </div>
          
          <template v-if="isEditing">
            <h3 class="section-title">Cambiar Contraseña (Opcional)</h3>
            <div class="form-group">
              <label>Nueva Contraseña</label>
              <input type="password" v-model="passwords.new_password" class="form-control" placeholder="Dejar en blanco para no cambiar" />
            </div>
            
            <div class="form-actions">
              <button type="submit" class="action-btn primary-btn">Guardar Cambios</button>
            </div>
          </template>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dashboard-content {
  padding: 24px;
  max-width: 800px;
  margin: 0 auto;
}
.header-section {
  margin-bottom: 32px;
}
.page-title {
  font-size: 2rem;
  margin: 0 0 8px 0;
}
.page-subtitle {
  color: var(--cc-text-muted);
  margin: 0;
}
.profile-card {
  background: var(--cc-surface);
  border: 1px solid var(--cc-border);
  border-radius: 16px;
  padding: 32px;
}
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--cc-border);
}
.card-header h2 {
  margin: 0;
  font-size: 1.25rem;
}
.profile-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.form-group label {
  font-weight: 500;
  color: var(--cc-text-muted);
  font-size: 0.9rem;
}
.hint {
  font-size: 0.8rem;
  color: #ef4444;
  margin-left: 8px;
}
.form-control {
  width: 100%;
  padding: 12px 16px;
  background: rgba(0,0,0,0.2);
  border: 1px solid var(--cc-border);
  color: var(--cc-text);
  border-radius: 10px;
  font-size: 1rem;
  transition: all 0.2s;
}
.form-control:focus {
  border-color: var(--cc-accent);
  outline: none;
  box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.2);
}
.form-control:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}
.section-title {
  margin-top: 24px;
  margin-bottom: 8px;
  font-size: 1.1rem;
  color: var(--cc-gold);
}
.form-actions {
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}
.action-btn {
  padding: 10px 24px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 0.2s;
}
.outline-btn {
  background: transparent;
  border: 1px solid var(--cc-border);
  color: var(--cc-text);
}
.outline-btn:hover {
  background: rgba(255, 255, 255, 0.05);
}
.primary-btn {
  background: var(--cc-accent);
  color: white;
  border: none;
}
.primary-btn:hover {
  background: #3b82f6;
}
</style>
