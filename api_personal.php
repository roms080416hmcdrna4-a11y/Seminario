<?php

header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 0); 

require_once "conexion.php";

$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {
    case 'GET': // Ver empleados
        try {
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("SELECT id, nombre, usuario, rol, estado FROM personal WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $resultado = $stmt->fetch();
                echo json_encode($resultado ? $resultado : []);
            } else {
                $stmt = $pdo->query("SELECT id, nombre, usuario, rol, estado FROM personal ORDER BY id DESC");
                echo json_encode($stmt->fetchAll());
            }
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        break;

    case 'POST': // Crear o editar
        $datos = json_decode(file_get_contents("php://input"));
        
        if (!$datos || empty($datos->nombre) || empty($datos->usuario) || empty($datos->rol) || empty($datos->estado)) {
            echo json_encode(["status" => "error", "message" => "Faltan campos mandatorios o el JSON es inválido."]);
            exit;
        }

        try {
            if (!empty($datos->id)) {
                // editar
                if (!empty($datos->pass)) {
                    $pass_hash = password_hash($datos->pass, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE personal SET nombre = ?, usuario = ?, pass = ?, rol = ?, estado = ? WHERE id = ?");
                    $stmt->execute([$datos->nombre, $datos->usuario, $pass_hash, $datos->rol, $datos->estado, $datos->id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE personal SET nombre = ?, usuario = ?, rol = ?, estado = ? WHERE id = ?");
                    $stmt->execute([$datos->nombre, $datos->usuario, $datos->rol, $datos->estado, $datos->id]);
                }
                echo json_encode(["status" => "success", "message" => "Empleado actualizado."]);
            } else {
                // registar
                if (empty($datos->pass)) {
                    echo json_encode(["status" => "error", "message" => "La contraseña es obligatoria para nuevos usuarios."]);
                    exit;
                }
                $pass_hash = password_hash($datos->pass, PASSWORD_BCRYPT);
                
                $stmt = $pdo->prepare("INSERT INTO personal (nombre, usuario, pass, rol, estado) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$datos->nombre, $datos->usuario, $pass_hash, $datos->rol, $datos->estado]);
                echo json_encode(["status" => "success", "message" => "Nuevo colaborador dado de alta."]);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                echo json_encode(["status" => "error", "message" => "El nombre de usuario ya está ocupado."]);
            } else {
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        }
        break;

    case 'PUT': // cambiar estado
        $datos = json_decode(file_get_contents("php://input"));
        if (!empty($datos->id) && !empty($datos->estado)) {
            try {
                $stmt = $pdo->prepare("UPDATE personal SET estado = ? WHERE id = ?");
                $stmt->execute([$datos->estado, $datos->id]);
                echo json_encode(["status" => "success", "message" => "Estado modificado exitosamente."]);
            } catch (PDOException $e) {
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        }
        break;
}
?>