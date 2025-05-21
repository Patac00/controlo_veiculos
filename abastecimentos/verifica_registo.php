<?php
include("../php/config.php");

if (!isset($_GET['data'], $_GET['hora'], $_GET['numero_reg'])) {
    echo json_encode(['erro' => 'Parâmetros em falta']);
    exit;
}

$data = $_GET['data'];
$hora = $_GET['hora'];
$numero_reg = trim($_GET['numero_reg']);

// Converte a data para Y-m-d
$dataObj = DateTime::createFromFormat('d/m/Y', $data);
$dataFormatada = $dataObj ? $dataObj->format('Y-m-d') : null;

$sql = "SELECT 1 FROM tabela_combustivel WHERE data = ? AND hora = ? AND numero_reg = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param("sss", $dataFormatada, $hora, $numero_reg);
$stmt->execute();
$stmt->store_result();

echo json_encode(['existe' => $stmt->num_rows > 0]);
?>
