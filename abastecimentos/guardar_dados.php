<?php
session_start();
include("../php/config.php");

function converteData($dataExcel) {
    $timestamp = strtotime($dataExcel);
    if ($timestamp === false) return null;
    return date('Y-m-d', $timestamp);
}

$dadosConvertidos = $_SESSION['dados_convertidos'] ?? [];

if (empty($dadosConvertidos)) {
    echo "Não há dados para guardar.";
    exit;
}

foreach ($dadosConvertidos as $linha) {
    $data = converteData($linha['data']);
    $hora = $linha['hora'];
    $id_veiculo = isset($linha['id_veiculo']) && is_numeric($linha['id_veiculo']) ? (int)$linha['id_veiculo'] : 0;
    $numero_reg = $linha['numero_reg'] ?? '';
    $odometro = is_numeric($linha['odometro']) ? (int)$linha['odometro'] : 0;
    $motorista = $linha['motorista'] ?? '';
    $quantidade = isset($linha['quantidade']) && is_numeric($linha['quantidade']) ? (float)$linha['quantidade'] : 0.0;

    $stmt = $con->prepare("INSERT INTO bomba_redinha (data, hora, id_veiculo, numero_reg, odometro, motorista, quantidade) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssisssd", $data, $hora, $id_veiculo, $numero_reg, $odometro, $motorista, $quantidade);
    $stmt->execute();
}

unset($_SESSION['dados_convertidos']);

echo "Dados guardados com sucesso!";
?>
