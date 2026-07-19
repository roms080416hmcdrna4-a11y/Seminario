<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");
error_reporting(E_ALL); ini_set('display_errors', 0);

require_once "conexion.php";
$fecha_hoy = date('Y-m-d');
$hora_actual = date('H:i:s');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datos = json_decode(file_get_contents("php://input"));
    if (!$datos || empty($datos->productos)) {
        echo json_encode(["status" => "error", "message" => "Carrito de envío vacío."]);
        exit;
    }

    $vendedor = !empty($datos->vendedor) ? trim($datos->vendedor) : "Caja Envíos";
    $total = floatval($datos->total);

    try {
        $pdo->beginTransaction();
        $prodJson = json_encode($datos->productos, JSON_UNESCAPED_UNICODE);

        // Registrar el Envío
        $stmt = $pdo->prepare("INSERT INTO envios (nombre_destinatario, telefono, correo, calle_numero, colonia, codigo_postal, metodo_paqueteria, fecha_entrega, notas, productos_desglose, total_pagado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            trim($datos->nombre), trim($datos->telefono), trim($datos->correo),
            trim($datos->calle), trim($datos->colonia), trim($datos->cp),
            trim($datos->metodo), $datos->fecha_entrega, trim($datos->notas),
            $prodJson, $total
        ]);
        $idEnvio = $pdo->lastInsertId();

        // Descontar Stock de Inventario
        foreach ($datos->productos as $p) {
            $stmtStock = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
            $stmtStock->execute([intval($p->cantidad), intval($p->id_db)]);
        }

        // ¡Insertar movimiento como orden de envío
        $conceptoFlujo = "Orden de Envío #" . $idEnvio . " - " . trim($datos->nombre);
        $stmtFlujo = $pdo->prepare("INSERT INTO movimientos_caja (hora, concepto, usuario, monto, tipo, fecha) VALUES (?, ?, ?, ?, ?, 'Ingreso')");
        $stmtFlujo->execute([$hora_actual, $conceptoFlujo, $vendedor, $total, $fecha_hoy]);

        $pdo->commit();
        echo json_encode(["status" => "success", "message" => "¡Orden de Envío #$idEnvio registrada correctamente!"]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(["status" => "error", "message" => "Error en BD: " . $e->getMessage()]);
    }
}