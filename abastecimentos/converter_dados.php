<?php
session_start();
include("../php/config.php");

$dadosConvertidos = $_SESSION['dados_convertidos'] ?? [];

foreach ($dadosConvertidos as &$linha) {
    $numero_reg = trim($linha['numero_reg']);

    if ($numero_reg === '') {
        $linha['id_veiculo'] = null;
        continue;
    }

    // Procura veículo com essa matrícula
    $stmt = $con->prepare("SELECT id_veiculo FROM veiculos WHERE Matricula = ?");
    $stmt->bind_param("s", $numero_reg);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $linha['id_veiculo'] = $row['id_veiculo'];
    } else {
        // Cria novo veículo sem matrícula (vazia)
        $descricao = "Criado automaticamente";
        $tipo = "Desconhecido";
        $grupo = "Sem grupo";
        $km = 0;
        $estado = "Ativo";
        $matricula = ''; // vazio

        $stmt_insert = $con->prepare("INSERT INTO veiculos (Matricula, Descricao, Tipo, Grupo, km_atual, estado) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_insert->bind_param("ssssis", $matricula, $descricao, $tipo, $grupo, $km, $estado);
        $stmt_insert->execute();

        $linha['id_veiculo'] = $stmt_insert->insert_id;
    }
}
$_SESSION['dados_convertidos'] = $dadosConvertidos;
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8" />
    <title>Converter Dados</title>
</head>
<body>
<h2>Dados convertidos com sucesso!</h2>
<form method="post" action="guardar_dados.php">
    <button type="submit">Guardar na Base de Dados</button>
</form>
</body>
</html>
