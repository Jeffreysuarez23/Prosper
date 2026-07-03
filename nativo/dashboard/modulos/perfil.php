<?php
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../login.php");
    exit;
}
?>
<style>
.password-wrapper { position: relative; }
.password-wrapper .form-control { padding-right: 40px; }
.password-toggle {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    background: none; border: none; color: var(--text-muted);
    cursor: pointer; padding: 0; display: flex; align-items: center; justify-content: center;
}
.password-toggle:hover { color: var(--text); }
</style>

<div class="content-section" style="max-width: 800px; margin: 0 auto; padding-top: 24px;">
    
    <!-- Toasts are handled via SweetAlert2 at the bottom -->

    <div class="card" style="margin-bottom: 24px;">
        <div class="card-head" style="margin-bottom: 24px;">
            <h2 class="card-title">Información Personal</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="acciones/procesar_perfil.php" autocomplete="off">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-row">
                    <div class="form-group form-half">
                        <label>Nombre</label>
                        <input type="text" name="nombre" class="form-control" required 
                               value="<?php echo htmlspecialchars($userData['nombre'] ?? ''); ?>"
                               pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+" title="Solo se permiten letras"
                               oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')">
                    </div>
                    <div class="form-group form-half">
                        <label>Apellidos</label>
                        <input type="text" name="apellido" class="form-control" required 
                               value="<?php echo htmlspecialchars($userData['apellido'] ?? ''); ?>"
                               pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+" title="Solo se permiten letras"
                               oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group form-half">
                        <label>Teléfono</label>
                        <input type="tel" name="telefono" class="form-control" required 
                               value="<?php echo htmlspecialchars($userData['telefono'] ?? ''); ?>"
                               pattern="[0-9+\-\s]+" title="Solo se permiten números y el signo +"
                               oninput="this.value = this.value.replace(/[^0-9+\-\s]/g, '')">
                    </div>
                    <div class="form-group form-half">
                        <label>Correo Electrónico</label>
                        <input type="email" name="correo" class="form-control" 
                               value="<?php echo htmlspecialchars($userData['correo'] ?? ''); ?>" disabled 
                               style="background: var(--surface-hover); cursor: not-allowed; opacity: 0.7;"
                               title="El correo no se puede cambiar">
                    </div>
                </div>

                <div class="form-actions" style="margin-top: 24px;">
                    <button type="submit" class="btn-accent">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-head" style="margin-bottom: 24px;">
            <h2 class="card-title">Seguridad</h2>
        </div>
        <div class="card-body">
            <form method="POST" action="acciones/procesar_perfil.php" id="formPassword" autocomplete="off">
                <input type="hidden" name="action" value="update_password">
                
                <div class="form-group" style="max-width: 400px;">
                    <label>Contraseña Actual</label>
                    <div class="password-wrapper">
                        <input type="password" name="contrasena_actual" id="contrasena_actual" class="form-control" required placeholder="••••••••">
                        <button type="button" class="password-toggle" onclick="togglePassword('contrasena_actual', 'eyeIconCurrent')">
                            <svg id="eyeIconCurrent" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 16px;">
                    <div class="form-group form-half">
                        <label>Nueva Contraseña</label>
                        <div class="password-wrapper">
                            <input type="password" name="nueva_contrasena" id="nueva_contrasena" class="form-control" required placeholder="••••••••" oninput="checkStrengthProfile()">
                            <button type="button" class="password-toggle" onclick="togglePassword('nueva_contrasena', 'eyeIconNew')">
                                <svg id="eyeIconNew" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                        <div class="password-strength" style="margin-top: 8px;">
                            <div id="strengthTextProfile" style="font-size: 0.8rem; color: var(--text-muted);">Seguridad de la contraseña</div>
                            <div class="strength-bar" style="height: 4px; background: var(--border); border-radius: 2px; margin-top: 4px; overflow: hidden;">
                                <div class="strength-progress" id="strengthProgressProfile" style="height: 100%; width: 0%; transition: width 0.3s, background-color 0.3s;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group form-half">
                        <label>Confirmar Nueva Contraseña</label>
                        <div class="password-wrapper">
                            <input type="password" name="confirmar_contrasena" id="confirmar_contrasena" class="form-control" required placeholder="••••••••">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirmar_contrasena', 'eyeIconConfirm')">
                                <svg id="eyeIconConfirm" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-actions" style="margin-top: 24px;">
                    <button type="submit" class="btn-accent" style="background: var(--blue-600); border-color: var(--blue-600);">Actualizar Contraseña</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toasts for success/error messages
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        background: 'var(--surface)',
        color: 'var(--text)'
    });

    <?php if (isset($_GET['success'])): ?>
        Toast.fire({
            icon: 'success',
            title: '<?php echo addslashes(htmlspecialchars($_GET['success'])); ?>'
        });
        window.history.replaceState(null, null, window.location.pathname + '?mod=perfil');
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        Toast.fire({
            icon: 'error',
            title: '<?php echo addslashes(htmlspecialchars($_GET['error'])); ?>'
        });
        window.history.replaceState(null, null, window.location.pathname + '?mod=perfil');
    <?php endif; ?>

    const pwdForm = document.getElementById('formPassword');
    if (pwdForm) {
        pwdForm.addEventListener('submit', function(e) {
            const pwd = document.getElementById('nueva_contrasena').value;
            const confirm = document.getElementById('confirmar_contrasena').value;
            
            if (pwd !== confirm) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Ups...',
                    text: 'Las contraseñas nuevas no coinciden.',
                    background: 'var(--surface)',
                    color: 'var(--text)',
                    confirmButtonColor: 'var(--accent)',
                    customClass: { popup: 'swal-custom-popup' }
                });
                return;
            }
            
            let strength = 0;
            if (pwd.length >= 8) strength += 25;
            if (pwd.match(/[A-Z]/)) strength += 25;
            if (pwd.match(/[0-9]/)) strength += 25;
            if (pwd.match(/[\W_]/)) strength += 25;
            
            if (strength < 100) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Contraseña débil',
                    text: 'Debe tener al menos 8 caracteres, una mayúscula, un número y un carácter especial.',
                    background: 'var(--surface)',
                    color: 'var(--text)',
                    confirmButtonColor: 'var(--accent)',
                    customClass: { popup: 'swal-custom-popup' }
                });
                return;
            }
        });
    }
});

function checkStrengthProfile() {
    const password = document.getElementById('nueva_contrasena').value;
    const text = document.getElementById('strengthTextProfile');
    const progress = document.getElementById('strengthProgressProfile');
    
    let strength = 0;
    if (password.length >= 8) strength += 25;
    if (password.match(/[A-Z]/)) strength += 25;
    if (password.match(/[0-9]/)) strength += 25;
    if (password.match(/[\W_]/)) strength += 25;

    progress.style.width = strength + '%';

    if (strength <= 25) {
        progress.style.backgroundColor = 'var(--error)';
        text.textContent = 'Contraseña débil (usa mayúsculas, números y símbolos)';
        text.style.color = 'var(--error)';
    } else if (strength <= 50) {
        progress.style.backgroundColor = '#f59e0b';
        text.textContent = 'Contraseña regular';
        text.style.color = '#f59e0b';
    } else if (strength <= 75) {
        progress.style.backgroundColor = '#3b82f6';
        text.textContent = 'Contraseña buena';
        text.style.color = '#3b82f6';
    } else {
        progress.style.backgroundColor = '#10b981';
        text.textContent = 'Contraseña fuerte';
        text.style.color = '#10b981';
    }
    
    if (password.length === 0) {
        text.textContent = 'Seguridad de la contraseña';
        text.style.color = 'var(--text-muted)';
        progress.style.width = '0%';
    }
}

function togglePassword(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
    } else {
        input.type = 'password';
        icon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
    }
}
</script>
