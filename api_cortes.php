<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once "conexion.php";

$metodo = $_SERVER['REQUEST_METHOD'];

switch ($metodo) {
    case 'GET': // OBTENER FLUJO ACTUAL Y TOTALES ACUMULADOS
        try {
            // 1. Aquí definimos los movimientos del turno actual de forma estática 
            // (En el futuro, estos vendrán de una tabla 'movimientos_caja' vinculada a las ventas)
            $movimientos = [
                ["hora" => "09:00 AM", "concepto" => "Fondo Inicial de Caja", "usuario" => "Sistema", "monto" => 1000.00, "tipo" => "entrada"],
                ["hora" => "11:15 AM", "concepto" => "Venta Mostrador #1002", "usuario" => "Vendedor_01", "monto" => 250.00, "tipo" => "entrada"],
                ["hora" => "01:30 PM", "concepto" => "Venta Mostrador #1003", "usuario" => "Vendedor_01", "monto" => 300.00, "tipo" => "entrada"],
                ["hora" => "03:00 PM", "concepto" => "Pago de flete / Salida de Efectivo", "usuario" => "Gerente", "monto" => -150.00, "tipo" => "salida"]
            ];

            // 2. Procesamos las matemáticas en el servidor
            $fondo_inicial = 1000.00;
            $ventas_totales = 550.00;
            $salidas_efectivo = 150.00;
            $efectivo_esperado = ($fondo_inicial + $ventas_totales) - $salidas_efectivo;

            echo json_encode([
                "movimientos" => $movimientos,
                "resumen" => [
                    "fondo_inicial" => $fondo_inicial,
                    "ventas_totales" => $ventas_totales,
                    "salidas_efectivo" => $salidas_efectivo,
                    "efectivo_esperado" => $efectivo_esperado
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        break;

    case 'POST': // CONSOLIDAR Y CERRAR LA CAJA EN MARIADB
        $datos = json_decode(file_get_contents("php://input"));
        
        if (!$datos || !isset($datos->efectivo_real)) {
            echo json_encode(["status" => "error", "message" => "El monto de efectivo real es obligatorio."]);
            exit;
        }

        $fecha = date('Y-m-d');
        $fondo = floatval($datos->fondo_inicial);
        $ventas = floatval($datos->ventas_totales);
        $salidas = floatval($datos->salidas_efectivo);
        $esperado = ($fondo + $ventas) - $salidas;
        $real = floatval($datos->efectivo_real);
        $diferencia = $real - $esperado;

        try {
            $stmt = $pdo->prepare("INSERT INTO cortes_caja (fecha, fondo_inicial, ventas_totales, salidas_efectivo, efectivo_esperado, efectivo_real, diferencia) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$fecha, $fondo, $ventas, $salidas, $esperado, $real, $diferencia]);
            
            echo json_encode([
                "status" => "success", 
                "message" => "¡Corte de caja guardado con éxito en MariaDB! Turno cerrado formalmente."
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                echo json_encode(["status" => "error", "message" => "Error: Ya se generó y guardó el corte de caja para el día de hoy."]);
            } else {
                echo json_encode(["status" => "error", "message" => "Error en Base de Datos: " . $e->getMessage()]);
            }
        }
        break;
}
?>