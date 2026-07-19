<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once "conexion.php";

$json = file_get_contents("php://input");
$datos = json_decode($json);

$usuarioInput = null;
$passInput = null;

if ($datos && isset($datos->usuario) && isset($datos->contrasena)) {
    $usuarioInput = trim($datos->usuario);
    $passInput = trim($datos->contrasena);
} 

else if (isset($_POST['usuario']) && isset($_POST['contrasena'])) {
    $usuarioInput = trim($_POST['usuario']);
    $passInput = trim($_POST['contrasena']);
}

if (empty($usuarioInput) || empty($passInput)) {
    echo json_encode([
        "status" => "error", 
        "message" => "Por favor, llene todos los campos del formulario."
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT nombre, usuario, pass, rol FROM personal WHERE usuario = ? AND estado = 'Activo' LIMIT 1");
    $stmt->execute([$usuarioInput]);
    $empleado = $stmt->fetch();

    if ($empleado) {
        if (password_verify($passInput, $empleado['pass'])) {
            echo json_encode([
                "status" => "success",
                "message" => "¡Bienvenido, " . $empleado['nombre'] . "!",
                "usuario" => [
                    "nombre" => $empleado['nombre'],
                    "usuario" => $empleado['usuario'],
                    "rol" => $empleado['rol']
                ]
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "La contraseña ingresada es incorrecta."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "El usuario no existe o se encuentra Inactivo."]);
    }
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Error de conexión en BD: " . $e->getMessage()]);
}
?>