<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verifica se os campos existem antes de usar
    $data = $_POST['data'] ?? null;
    $tipo_combustivel = $_POST['tipo_combustivel'] ?? null;
    //$litros = $_POST['litros'] ?? null;
    $preco_litro = $_POST['preco_litro'] ?? null;
    $localizacao = $_POST['localizacao'] ?? null;
    $fatura = $_POST['fatura'] ?? null;
    $litros = floatval($_POST['litros']);


    if ($data && $tipo_combustivel && $litros && $preco_litro && $localizacao && $fatura) {
        // Prepara e executa o insert
        $stmt = $con->prepare("INSERT INTO stocks_combustivel (data, tipo_combustivel, litros, preco_litro, localizacao, fatura) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssddss", $data, $tipo_combustivel, $litros, $preco_litro, $localizacao, $fatura);

        if ($stmt->execute()) {
            echo "<p>Abastecimento registado com sucesso!</p>";
        } else {
            echo "<p>Erro ao registar abastecimento: " . $stmt->error . "</p>";
        }

        $stmt->close();
    } else {
        echo "<p>Por favor, preenche todos os campos.</p>";
    }
    if ($litros > 10000) {
        echo "Erro: não podes inserir mais que 10000 litros.";
        exit;
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

        <label for="localizacao">Localização:</label>
        <select name="localizacao" required>
            <option value="CIVTRIHI">CIVTRIHI</option>
            <option value="Dep móvel">Dep móvel</option>
            <option value="Redinha">Redinha</option>
            <option value="Ribtejo">Ribtejo</option>
            <option value="Venda Cruz">Venda Cruz</option>
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
