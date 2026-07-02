<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../login.php");
    exit;
}

require_once '../config/database.php';
$dbClass = new Database();
$db = $dbClass->getConnection();
$id_usuario = (int)$_SESSION['id_usuario'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');

        // Validaciones backend
        if (empty($nombre) || empty($apellido) || empty($telefono)) {
            header("Location: ../index.php?mod=perfil&error=" . urlencode("Todos los campos son obligatorios."));
            exit;
        }

        if (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $nombre) || !preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/', $apellido)) {
            header("Location: ../index.php?mod=perfil&error=" . urlencode("El nombre y apellido solo deben contener letras."));
            exit;
        }

        if (!preg_match('/^[0-9+\-\s]+$/', $telefono)) {
            header("Location: ../index.php?mod=perfil&error=" . urlencode("El teléfono contiene caracteres no válidos."));
            exit;
        }

        $stmt = $db->prepare("UPDATE usuarios SET nombre = :nombre, apellido = :apellido, telefono = :telefono WHERE id_usuario = :id");
        if ($stmt->execute([
            ':nombre' => $nombre,
            ':apellido' => $apellido,
            ':telefono' => $telefono,
            ':id' => $id_usuario
        ])) {
            // Actualizar la sesión también por si se usa en otro lado
            $_SESSION['nombre_usuario'] = $nombre;
            header("Location: ../index.php?mod=perfil&success=" . urlencode("Información personal actualizada correctamente."));
            exit;
        } else {
            header("Location: ../index.php?mod=perfil&error=" . urlencode("Hubo un error al actualizar la información."));
            exit;
        }
    } 
    elseif ($action === 'update_password') {
        $contrasena_actual = trim($_POST['contrasena_actual'] ?? '');
        $nueva_contrasena = trim($_POST['nueva_contrasena'] ?? '');
        $confirmar_contrasena = trim($_POST['confirmar_contrasena'] ?? '');

        if (empty($contrasena_actual) || empty($nueva_contrasena) || empty($confirmar_contrasena)) {
            header("Location: ../index.php?mod=perfil&error=" . urlencode("Por favor completa todos los campos de contraseña."));
            exit;
        }

        if ($nueva_contrasena !== $confirmar_contrasena) {
            header("Location: ../index.php?mod=perfil&error=" . urlencode("Las contraseñas nuevas no coinciden."));
            exit;
        }

        // Obtener la contraseña actual de la BD
        $stmt = $db->prepare("SELECT contrasena FROM usuarios WHERE id_usuario = :id LIMIT 1");
        $stmt->execute([':id' => $id_usuario]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($contrasena_actual, $user['contrasena'])) {
            
            // Validar fuerza de nueva contraseña
            if (strlen($nueva_contrasena) < 8 || !preg_match('/[A-Z]/', $nueva_contrasena) || !preg_match('/[0-9]/', $nueva_contrasena) || !preg_match('/[\W_]/', $nueva_contrasena)) {
                header("Location: ../index.php?mod=perfil&error=" . urlencode("La nueva contraseña no es lo suficientemente fuerte."));
                exit;
            }

            $hashed_password = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
            $stmtUpdate = $db->prepare("UPDATE usuarios SET contrasena = :pass WHERE id_usuario = :id");
            if ($stmtUpdate->execute([':pass' => $hashed_password, ':id' => $id_usuario])) {
                header("Location: ../index.php?mod=perfil&success=" . urlencode("Contraseña actualizada exitosamente."));
                exit;
            } else {
                header("Location: ../index.php?mod=perfil&error=" . urlencode("Hubo un error al actualizar la contraseña."));
                exit;
            }
        } else {
            header("Location: ../index.php?mod=perfil&error=" . urlencode("La contraseña actual es incorrecta."));
            exit;
        }
    }
}
header("Location: ../index.php?mod=perfil");
exit;
