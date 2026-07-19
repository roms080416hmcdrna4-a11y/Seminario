<?php
header("Content-Type: application/json");
require_once "conexion.php";

$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {
    case 'GET':
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode($stmt->fetch());
        } else {
            $stmt = $pdo->query("SELECT * FROM productos ORDER BY id DESC");
            echo json_encode($stmt->fetchAll());
        }
        break;

    case 'POST':
        $datos = json_decode(file_get_contents("php://input"), true);
        
        if (empty($datos['codigo']) || empty($datos['nombre']) || !isset($datos['precio'])) {
            echo json_encode(["message" => "Datos incompletos en el servidor."]);
            exit;
        }

        $id = !empty($datos['id']) ? intval($datos['id']) : null;
        $codigo = trim($datos['codigo']);
        $nombre = trim($datos['nombre']);
        $categoria = !empty($datos['categoria']) ? $datos['categoria'] : 'Perfumes';
        $precio = floatval($datos['precio']);
        $stock = isset($datos['stock']) ? intval($datos['stock']) : 0;

        if ($id) {
            $stmt = $pdo->prepare("UPDATE productos SET codigo = ?, nombre = ?, categoria = ?, precio = ?, stock = ? WHERE id = ?");
            $stmt->execute([$codigo, $nombre, $categoria, $precio, $stock, $id]);
            echo json_encode(["message" => "¡Producto modificado con éxito!"]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO productos (codigo, nombre, categoria, precio, stock) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$codigo, $nombre, $categoria, $precio, $stock]);
            echo json_encode(["message" => "¡Producto creado con éxito!"]);
        }
        break;

    case 'DELETE':
        $datos = json_decode(file_get_contents("php://input"), true);
        if (!empty($datos['id'])) {
            $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
            $stmt->execute([$datos['id']]);
            echo json_encode(["message" => "¡Producto eliminado con éxito!"]);
        } else {
            echo json_encode(["message" => "ID no recibido para eliminar."]);
        }
        break;
}
?>