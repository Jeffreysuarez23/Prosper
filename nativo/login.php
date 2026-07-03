<?php
session_start();
require_once 'conexion.php';

// Si el usuario ya está logueado, redirigir al dashboard
if (isset($_SESSION['id_usuario'])) {
    header("Location: dashboard/index.php");
    exit;
}

$error = '';
$success = '';
$action = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'login') {
        $correo = trim($_POST['correo'] ?? '');
        $contrasena = trim($_POST['contrasena'] ?? '');
        
        if (!empty($correo) && !empty($contrasena)) {
            $stmt = $db->prepare("SELECT * FROM usuarios WHERE correo = :correo LIMIT 1");
            $stmt->execute([':correo' => $correo]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                if (password_verify($contrasena, $user['contrasena'])) {
                    $_SESSION['id_usuario'] = $user['id_usuario'];
                    $_SESSION['nombre_usuario'] = $user['nombre'];
                    header("Location: dashboard/index.php");
                    exit;
                } else {
                    $error = 'La contraseña es incorrecta.';
                }
            } else {
                $error = 'Esta cuenta no existe.';
            }
        } else {
            $error = 'Por favor completa todos los campos.';
        }
    } elseif ($action === 'register') {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $correo = trim($_POST['correo'] ?? '');
        $contrasena = trim($_POST['contrasena'] ?? '');
        $contrasena_confirmar = trim($_POST['contrasena_confirmar'] ?? '');
        
        if (!empty($nombre) && !empty($apellido) && !empty($telefono) && !empty($correo) && !empty($contrasena) && !empty($contrasena_confirmar)) {
            if ($contrasena !== $contrasena_confirmar) {
                $error = 'Las contraseñas no coinciden.';
            } elseif (strlen($contrasena) < 8 || !preg_match('/[A-Z]/', $contrasena) || !preg_match('/[0-9]/', $contrasena) || !preg_match('/[\W_]/', $contrasena)) {
                $error = 'La contraseña es demasiado débil. Debe tener al menos 8 caracteres, una mayúscula, un número y un carácter especial.';
            } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $nombre) || !preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $apellido)) {
                $error = 'El nombre y apellido solo deben contener letras.';
            } elseif (!preg_match('/^[0-9+\-\s]+$/', $telefono)) {
                $error = 'El teléfono contiene caracteres no válidos.';
            } else {
                // Verificar si el correo ya existe
                $stmt = $db->prepare("SELECT id_usuario FROM usuarios WHERE correo = :correo");
                $stmt->execute([':correo' => $correo]);
                if ($stmt->fetch()) {
                    $error = 'El correo ya está registrado.';
                } else {
                    $hashed_password = password_hash($contrasena, PASSWORD_DEFAULT);
                    $id_rol = 2; // User role
                    
                    $stmt = $db->prepare("INSERT INTO usuarios (id_rol, nombre, apellido, correo, telefono, contrasena) VALUES (:id_rol, :nombre, :apellido, :correo, :telefono, :contrasena)");
                    if ($stmt->execute([
                        ':id_rol' => $id_rol,
                        ':nombre' => $nombre,
                        ':apellido' => $apellido,
                        ':correo' => $correo,
                        ':telefono' => $telefono,
                        ':contrasena' => $hashed_password
                    ])) {
                        $success = 'Cuenta creada exitosamente. Ya puedes iniciar sesión.';
                        $action = 'login'; // Switch back to login view
                    } else {
                        $error = 'Hubo un error al crear la cuenta.';
                    }
                }
            }
        } else {
            $error = 'Por favor completa todos los campos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prosper - Inicio de sesión</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg: #0f172a;
            --surface: #1e293b;
            --surface-hover: #334155;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --border: #334155;
            --error: #ef4444;
            --error-bg: rgba(239, 68, 68, 0.1);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
            overflow-y: auto;
            position: relative;
            padding: 40px 20px;
        }

        /* Abstract shapes */
        .shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.5;
            z-index: -1;
        }
        .shape-1 {
            width: 400px;
            height: 400px;
            background: #3b82f6;
            top: -100px;
            left: -100px;
        }
        .shape-2 {
            width: 300px;
            height: 300px;
            background: #10b981;
            bottom: -50px;
            right: -50px;
        }

        .auth-container {
            width: 100%;
            max-width: 420px;
            position: relative;
            perspective: 1000px;
            margin: auto;
            transition: max-width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .auth-container.show-register {
            max-width: 550px;
        }

        .auth-box {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
            width: 100%;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.6s ease;
            transform-style: preserve-3d;
        }

        #loginBox {
            position: relative;
        }

        /* Hide register by default using position absolute */
        #registerBox {
            position: absolute;
            top: 0;
            left: 0;
            opacity: 0;
            visibility: hidden;
            transform: translateX(50px);
        }

        .show-register #loginBox {
            position: absolute;
            top: 0;
            left: 0;
            opacity: 0;
            visibility: hidden;
            transform: translateX(-50px);
        }

        .show-register #registerBox {
            position: relative;
            opacity: 1;
            visibility: visible;
            transform: translateX(0);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 32px;
            justify-content: center;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: var(--accent);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.75rem;
            margin-bottom: 8px;
            text-align: center;
        }

        .subtitle {
            color: var(--text-muted);
            text-align: center;
            margin-bottom: 32px;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
        }

        .form-control {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--border);
            color: var(--text);
            padding: 12px 16px;
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        .btn-primary {
            width: 100%;
            background: var(--accent);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            margin-top: 8px;
        }

        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
        }

        .toggle-text {
            text-align: center;
            margin-top: 24px;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .toggle-text a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: color 0.2s;
        }

        .toggle-text a:hover {
            color: white;
        }

        .error-message {
            background: var(--error-bg);
            color: var(--error);
            padding: 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .success-message {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            padding: 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .password-wrapper {
            position: relative;
        }

        .password-wrapper .form-control {
            padding-right: 40px;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle:hover {
            color: var(--text);
        }

        .password-strength {
            margin-top: 8px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .strength-bar {
            height: 4px;
            background: var(--border);
            border-radius: 2px;
            margin-top: 4px;
            overflow: hidden;
        }

        .strength-progress {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background-color 0.3s;
        }

        /* SweetAlert Customization */
        .swal-custom-popup {
            border: 1px solid var(--border) !important;
            border-radius: 24px !important;
            font-family: 'Inter', sans-serif !important;
        }
        .swal2-title {
            font-family: 'Space Grotesk', sans-serif !important;
        }
    </style>
</head>
<body>
    <div class="shape shape-1"></div>
    <div class="shape shape-2"></div>

    <div class="auth-container" id="authContainer">
        
        <!-- LOGIN BOX -->
        <div class="auth-box" id="loginBox">
            <div class="logo">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                </div>
                Prosper
            </div>
            
            <h1 class="title">Bienvenido de nuevo</h1>
            <p class="subtitle">Ingresa tus datos para continuar.</p>

            <?php if (!empty($success)): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (!empty($error) && $action === 'login'): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php" autocomplete="off">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>Correo Electrónico</label>
                    <input type="email" name="correo" class="form-control" required placeholder="tu@correo.com" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <div class="password-wrapper">
                        <input type="password" name="contrasena" id="loginPassword" class="form-control" required placeholder="••••••••" autocomplete="new-password">
                        <button type="button" class="password-toggle" onclick="togglePassword('loginPassword', 'eyeIconLogin')">
                            <svg id="eyeIconLogin" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Iniciar Sesión</button>
            </form>

            <div class="toggle-text">
                ¿No tienes cuenta? <a onclick="toggleAuth()">Regístrate aquí</a>
            </div>
        </div>

        <!-- REGISTER BOX -->
        <div class="auth-box" id="registerBox">
            <div class="logo">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                </div>
                Prosper
            </div>
            
            <h1 class="title">Crea tu cuenta</h1>
            <p class="subtitle">Empieza a tomar el control de tus finanzas.</p>

            <?php if (!empty($error) && $action === 'register'): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php" id="registerForm" autocomplete="off">
                <input type="hidden" name="action" value="register">
                <div style="display: flex; gap: 10px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Nombre</label>
                        <input type="text" name="nombre" class="form-control" required placeholder="Tu Nombre" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+" title="Solo se permiten letras" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')" autocomplete="off">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Apellidos</label>
                        <input type="text" name="apellido" class="form-control" required placeholder="Tus Apellidos" pattern="[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+" title="Solo se permiten letras" oninput="this.value = this.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '')" autocomplete="off">
                    </div>
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input type="tel" name="telefono" class="form-control" required placeholder="Tu Teléfono" pattern="[0-9+\-\s]+" title="Solo se permiten números y el signo +" oninput="this.value = this.value.replace(/[^0-9+\-\s]/g, '')" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Correo Electrónico</label>
                    <input type="email" name="correo" class="form-control" required placeholder="tu@correo.com" autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <div class="password-wrapper">
                        <input type="password" name="contrasena" id="regPassword" class="form-control" required placeholder="••••••••" oninput="checkStrength()" autocomplete="new-password">
                        <button type="button" class="password-toggle" onclick="togglePassword('regPassword', 'eyeIconReg')">
                            <svg id="eyeIconReg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <div class="password-strength">
                        <div id="strengthText">Seguridad de la contraseña</div>
                        <div class="strength-bar">
                            <div class="strength-progress" id="strengthProgress"></div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirmar Contraseña</label>
                    <div class="password-wrapper">
                        <input type="password" name="contrasena_confirmar" id="regPasswordConfirm" class="form-control" required placeholder="••••••••" autocomplete="new-password">
                        <button type="button" class="password-toggle" onclick="togglePassword('regPasswordConfirm', 'eyeIconRegConfirm')">
                            <svg id="eyeIconRegConfirm" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Registrarse</button>
            </form>

            <div class="toggle-text">
                ¿Ya tienes cuenta? <a onclick="toggleAuth()">Inicia Sesión</a>
            </div>
        </div>

    </div>

    <script>
        function toggleAuth() {
            document.getElementById('authContainer').classList.toggle('show-register');
        }

        // If there was an error in registration, show register box on load
        <?php if ($action === 'register' && !empty($error)): ?>
            toggleAuth();
        <?php endif; ?>

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

        function checkStrength() {
            const password = document.getElementById('regPassword').value;
            const text = document.getElementById('strengthText');
            const progress = document.getElementById('strengthProgress');
            
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

        const registerForm = document.getElementById('registerForm');
        if (registerForm) {
            registerForm.addEventListener('submit', function(e) {
                const pwd = document.getElementById('regPassword').value;
                const confirm = document.getElementById('regPasswordConfirm').value;
                
                if (pwd !== confirm) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Ups...',
                        text: 'Las contraseñas no coinciden.',
                        background: 'var(--surface)',
                        color: 'var(--text)',
                        confirmButtonColor: 'var(--accent)',
                        customClass: {
                            popup: 'swal-custom-popup'
                        }
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
                        customClass: {
                            popup: 'swal-custom-popup'
                        }
                    });
                    return;
                }
            });
        }
    </script>
</body>
</html>
