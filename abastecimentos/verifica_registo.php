<?php
include("../php/config.php");

if (!isset($_GET['data'], $_GET['hora'], $_GET['numero_reg'], $_GET['motorista'], $_GET['unidade'])) {
    echo json_encode(['erro' => 'Parâmetros em falta']);
    exit;
}

$data = $_GET['data'];
$hora = $_GET['hora'];
$numero_reg = trim($_GET['numero_reg']);
$motorista = trim($_GET['motorista']);
$unidade = trim($_GET['unidade']);

// Converter data para formato Y-m-d
$dataObj = DateTime::createFromFormat('d/m/Y', $data);
$dataFormatada = $dataObj ? $dataObj->format('Y-m-d') : null;

// Obter ID do motorista
$stmt = $con->prepare("SELECT id FROM motoristas WHERE nome = ?");
$stmt->bind_param("s", $motorista);
$stmt->execute();
$stmt->bind_result($idMotorista);
$idMotorista = $stmt->fetch() ? $idMotorista : null;
$stmt->close();

// Obter ID do veículo
$stmt = $con->prepare("SELECT id FROM veiculos WHERE matricula = ?");
$stmt->bind_param("s", $unidade);
$stmt->execute();
$stmt->bind_result($idVeiculo);
$idVeiculo = $stmt->fetch() ? $idVeiculo : null;
$stmt->close();

// Se não encontrou motorista ou veículo, marcar como não existente
if (!$idMotorista || !$idVeiculo) {
    echo json_encode(['existe' => false]);
    exit;
}

// Verificar se já existe na base de dados
$sql = "SELECT 1 FROM tabela_combustivel WHERE data = ? AND hora = ? AND numero_reg = ? AND motorista = ? AND id_veiculo = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param("sssii", $dataFormatada, $hora, $numero_reg, $idMotorista, $idVeiculo);
$stmt->execute();
$stmt->store_result();

echo json_encode(['existe' => $stmt->num_rows > 0]);
?>
