<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $data = $_POST['data'] ?? null;
    $tipo_combustivel = $_POST['tipo_combustivel'] ?? null;
    $preco_litro = $_POST['preco_litro'] ?? null;
    $id_posto = $_POST['id_posto'] ?? null;
    $fatura = $_POST['fatura'] ?? null;
    $litros = floatval($_POST['litros']);
    
    // Exemplo: valores fixos para empresa, matricula, motorista (ajusta conforme precisares)
    $empresa_id = 1;  
    $matricula = 'ABC1234';  
    $motorista = 'Motorista Exemplo';

    if ($data && $tipo_combustivel && $litros && $preco_litro && $id_posto && $fatura) {
        $stmt = $con->prepare("INSERT INTO abastecimentos (data_abastecimento, tipo_combustivel, litros, preco_litro, id_posto, fatura, empresa_id, matricula, motorista) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssddissss", $data, $tipo_combustivel, $litros, $preco_litro, $id_posto, $fatura, $empresa_id, $matricula, $motorista);

        if ($stmt->execute()) {
            echo "Abastecimento registado com sucesso!";
        } else {
            echo "Erro ao inserir: " . $stmt->error;
        }

        $stmt->close();
    } else {
        echo "Por favor, preencha todos os campos.";
    }

    $con->close();
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Registar Abastecimento na Bomba</title>
</head>
<body>
    <form action="" method="post">
        <label for="data">Data:</label>
        <input type="date" name="data" required><br>

        <label for="tipo_combustivel">Tipo de Combustível:</label>
        <select name="tipo_combustivel" required>
            <option value="Diesel">Diesel</option>
        </select><br>

        <label for="litros">Litros:</label>
        <input type="number" step="0.01" name="litros" max="10000" required><br>

        <label for="preco_litro">Preço por Litro (€):</label>
        <input type="number" step="0.01" name="preco_litro" required><br>

        <label for="id_posto">Posto:</label>
        <select name="id_posto" required>
            <?php
            // Puxar lista de postos da base de dados para preencher o select
            $res_postos = $con->query("SELECT id_posto, nome FROM lista_postos ORDER BY nome");
            if ($res_postos) {
                while ($row = $res_postos->fetch_assoc()) {
                    echo '<option value="' . $row['id_posto'] . '">' . htmlspecialchars($row['nome']) . '</option>';
                }
            }
            ?>
        </select><br>

        <label for="fatura">Fatura?</label>
        <select name="fatura" required>
            <option value="Sim">Sim</option>
            <option value="Não">Não</option>
        </select><br>

        <button type="submit">Registar Abastecimento</button>
    </form>
</body>
</html>
