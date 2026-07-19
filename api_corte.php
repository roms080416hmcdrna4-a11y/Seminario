<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
error_reporting(E_ALL); ini_set('display_errors', 0);

require_once "conexion.php";
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'POST') {

    $fecha = isset($_POST['fecha']) ? trim($_POST['fecha']) : null;
    $sistema_total = isset($_POST['sistema_total']) ? floatval($_POST['sistema_total']) : 0;
    $efectivo_real = isset($_POST['efectivo_real']) ? floatval($_POST['efectivo_real']) : 0;
    $diferencia = isset($_POST['diferencia']) ? floatval($_POST['diferencia']) : 0;

    if (!$fecha) {
        echo json_encode(["status" => "error", "message" => "La fecha es obligatoria."]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO cortes_caja (fecha, efectivo_esperado, efectivo_real, diferencia) VALUES (?, ?, ?, ?)");
        $stmt->execute([$fecha, $sistema_total, $efectivo_real, $diferencia]);

        echo json_encode([
            "status" => "success",
            "message" => "¡El corte de caja del día $fecha ha sido guardado y cerrado con éxito!"
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            "status" => "error", 
            "message" => "Error interno en la Base de Datos al guardar: " . $e->getMessage()
        ]);
    }
    exit;
}

if ($metodo === 'GET') {
    if (isset($_GET['accion']) && $_GET['accion'] === 'buscar_corte') {
        $fechaBusqueda = trim($_GET['fecha']);
        try {
            //si hay registro 
            $stmt = $pdo->prepare("SELECT efectivo_esperado AS sistema_total, efectivo_real, diferencia FROM cortes_caja WHERE fecha = ? LIMIT 1");
            $stmt->execute([$fechaBusqueda]);
            $corte = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($corte) {
                echo json_encode(["status" => "success", "corte" => $corte]);
            } else {
                echo json_encode(["status" => "error", "message" => "No se encontró ningún corte definitivo para la fecha seleccionada."]);
            }
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
        exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
    try {
        // Consulta la tabla de movimientos
        $stmt = $pdo->prepare("SELECT hora, concepto, usuario, monto, tipo FROM movimientos_caja WHERE fecha = ? ORDER BY hora ASC");
        $stmt->execute([$fecha]);
        $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $ingresos = 0; $egresos = 0;
        foreach ($movimientos as $m) {
            if ($m['tipo'] === 'Ingreso') $ingresos += floatval($m['monto']);
            else $egresos += floatval($m['monto']);
        }

        echo json_encode([
            "status" => "success",
            "movimientos" => $movimientos,
            "resumen" => [
                "ingresos" => $ingresos,
                "egresos" => $egresos,
                "sistema_total" => ($ingresos - $egresos)
            ]
        ]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
}