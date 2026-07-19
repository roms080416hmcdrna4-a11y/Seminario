<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
error_reporting(E_ALL); ini_set('display_errors', 0);

require_once "conexion.php";
$metodo = $_SERVER['REQUEST_METHOD'];
$fecha_hoy = date('Y-m-d');
$hora_actual = date('H:i:s');

switch ($metodo) {
    case 'GET':
        if (isset($_GET['buscar'])) {
            $busqueda = trim($_GET['buscar']);
            try {
                $stmt = $pdo->prepare("SELECT id, codigo, nombre, precio, stock FROM productos WHERE codigo = ? OR nombre LIKE ? LIMIT 1");
                $stmt->execute([$busqueda, "%$busqueda%"]);
                $producto = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($producto) {
                    echo json_encode(["status" => "success", "producto" => $producto]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Producto no registrado en inventario."]);
                }
            } catch (PDOException $e) {
                echo json_encode(["status" => "error", "message" => $e->getMessage()]);
            }
        }
        break;

    case 'POST':
        $datos = json_decode(file_get_contents("php://input"));
        if (!$datos || empty($datos->productos)) {
            echo json_encode(["status" => "error", "message" => "El carrito está vacío."]);
            exit;
        }

        $vendedor = !empty($datos->vendedor) ? trim($datos->vendedor) : "Vendedor Caja";
        $fecha = !empty($datos->fecha) ? $datos->fecha : $fecha_hoy;
        $total = floatval($datos->total);

        try {
            $pdo->beginTransaction();

            // Insertar la venta
            $stmt = $pdo->prepare("INSERT INTO ventas (fecha, total, vendedor) VALUES (?, ?, ?)");
            $stmt->execute([$fecha, $total, $vendedor]);
            $idVenta = $pdo->lastInsertId();

            // Insertar detalles y descontar stock
            foreach ($datos->productos as $p) {
                $stmtD = $pdo->prepare("INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
                $stmtD->execute([$idVenta, $p->id_db, $p->cantidad, $p->precioUnitario, $p->subtotal]);

                $stmtStock = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
                $stmtStock->execute([intval($p->cantidad), intval($p->id_db)]);
            }

            // Insertar movimiento
            $conceptoFlujo = "Venta Mostrador #" . $idVenta;
            $stmtFlujo = $pdo->prepare("INSERT INTO movimientos_caja (hora, concepto, usuario, monto, tipo, fecha) VALUES (?, ?, ?, ?, 'Ingreso', ?)");
            $stmtFlujo->execute([$hora_actual, $conceptoFlujo, $vendedor, $total, $fecha]);

            $pdo->commit();
            echo json_encode(["status" => "success", "message" => "¡Venta Mostrador #$idVenta cobrada con éxito!"]);
        } catch (PDOException $e) {
            $pdo->rollBack();
            echo json_encode(["status" => "error", "message" => "Error en BD: " . $e->getMessage()]);
        }
        break;
}