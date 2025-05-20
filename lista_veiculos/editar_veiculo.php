<?php
include("../php/config.php");

$msg = "";
$matricula_antiga = "";

if (isset($_GET['matricula'])) {
    $matricula_antiga = mysqli_real_escape_string($con, $_GET['matricula']);
    $sql = "SELECT * FROM veiculos WHERE matricula = '$matricula_antiga'";
    $result = mysqli_query($con, $sql);

    if (mysqli_num_rows($result) === 1) {
        $veiculo = mysqli_fetch_assoc($result);
    } else {
        $msg = "Veículo não encontrado.";
    }
} else {
    $msg = "Matrícula não especificada.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matricula_nova = mysqli_real_escape_string($con, $_POST['matricula']);
    $marca = mysqli_real_escape_string($con, $_POST['marca']);
    $modelo = mysqli_real_escape_string($con, $_POST['modelo']);
    $tipo_veiculo = mysqli_real_escape_string($con, $_POST['tipo_veiculo']);
    $empresa_atual_id = (int)$_POST['empresa_atual_id'];
    $km_atual = (int)$_POST['km_atual'];
    $estado = mysqli_real_escape_string($con, $_POST['estado']);
    $matricula_antiga_post = mysqli_real_escape_string($con, $_POST['matricula_antiga']);

    // Se a matrícula nova for diferente da antiga, verificar se já existe
    if ($matricula_nova !== $matricula_antiga_post) {
        $check_sql = "SELECT * FROM veiculos WHERE matricula = '$matricula_nova'";
        $check_result = mysqli_query($con, $check_sql);
        if (mysqli_num_rows($check_result) > 0) {
            $msg = "Erro: Já existe um veículo com essa matrícula.";
        }
    }

    if (!$msg) {
        $update_sql = "UPDATE veiculos SET 
            matricula = '$matricula_nova',
            marca = '$marca',
            modelo = '$modelo',
            tipo_veiculo = '$tipo_veiculo',
            empresa_atual_id = $empresa_atual_id,
            km_atual = $km_atual,
            estado = '$estado'
            WHERE matricula = '$matricula_antiga_post'";

        if (mysqli_query($con, $update_sql)) {
            $msg = "Veículo atualizado com sucesso.";
            // Atualizar a matrícula antiga para a nova (útil para re-carregar o form)
            $matricula_antiga = $matricula_nova;
            // Recarregar dados atualizados
            $sql = "SELECT * FROM veiculos WHERE matricula = '$matricula_nova'";
            $result = mysqli_query($con, $sql);
            $veiculo = mysqli_fetch_assoc($result);
        } else {
            $msg = "Erro na atualização: " . mysqli_error($con);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8" />
    <title>Editar Veículo</title>
    <style>
        /* Mantém o estilo semelhante ao inserir_veiculo.php */
        body {
            font-family: Arial, sans-serif;
            background-color: #f1f3f4;
            margin: 0; padding: 0;
        }
        .container {
            max-width: 500px;
            margin: 80px auto;
            background-color: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        h2 {
            color: #00693e;
            text-align: center;
        }
        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }
        input[type=text], input[type=number], select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 8px;
        }
        button {
            background-color: #00693e;
            color: white;
            padding: 10px 20px;
            margin-top: 20px;
            border: none;
            border-radius: 8px;
            width: 100%;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #005632;
        }
        .msg {
            text-align: center;
            margin-top: 10px;
            font-weight: bold;
            color: red;
        }
        .success {
            color: green;
        }
        .back-btn {
            display: block;
            text-align: center;
            margin-top: 20px;
            text-decoration: none;
            color: #00693e;
            font-weight: bold;
        }
        .back-btn:hover {
            text-decoration: underline;
        }
    </style>
    <script>
    window.onload = function() {
        const matriculaInput = document.querySelector('input[name="matricula"]');
        const avisoDiv = document.createElement('div');
        avisoDiv.style.color = 'orange';
        avisoDiv.style.marginBottom = '10px';
        avisoDiv.style.fontWeight = 'bold';
        avisoDiv.style.display = 'none';  // escondido inicialmente
        avisoDiv.textContent = "Atenção: Alterar a matrícula pode causar problemas se não for feito com cuidado.";

        matriculaInput.parentNode.insertBefore(avisoDiv, matriculaInput);

        const matriculaAntiga = "<?= addslashes($matricula_antiga) ?>";

        matriculaInput.addEventListener('input', function() {
        if (matriculaInput.value !== matriculaAntiga) {
            avisoDiv.style.display = 'block';
        } else {
            avisoDiv.style.display = 'none';
        }
        });
    }
    </script>

</head>
<body>

<div class="container">
    <h2>Editar Veículo</h2>

    <?php if ($msg): ?>
        <div class="msg <?= strpos($msg, 'sucesso') !== false ? 'success' : '' ?>"><?= $msg ?></div>
    <?php endif; ?>

    <?php if (isset($veiculo)): ?>
    <form action="" method="POST">
        <input type="hidden" name="matricula_antiga" value="<?= htmlspecialchars($matricula_antiga) ?>" />

        <label>Matrícula:</label>
        <input type="text" name="matricula" required maxlength="15" value="<?= htmlspecialchars($veiculo['matricula']) ?>" />

        <label>Marca:</label>
        <input type="text" name="marca" required value="<?= htmlspecialchars($veiculo['marca']) ?>" />

        <label>Modelo:</label>
        <input type="text" name="modelo" required value="<?= htmlspecialchars($veiculo['modelo']) ?>" />

        <label>Tipo de Veículo:</label>
        <select name="tipo_veiculo" required>
            <option value="">-- Selecionar --</option>
            <option value="Carro" <?= $veiculo['tipo_veiculo'] == 'Carro' ? 'selected' : '' ?>>Carro</option>
            <option value="Camião" <?= $veiculo['tipo_veiculo'] == 'Camião' ? 'selected' : '' ?>>Camião</option>
            <option value="Mota" <?= $veiculo['tipo_veiculo'] == 'Mota' ? 'selected' : '' ?>>Mota</option>
            <option value="Outro" <?= $veiculo['tipo_veiculo'] == 'Outro' ? 'selected' : '' ?>>Outro</option>
        </select>

        <label>ID da Empresa Atual:</label>
        <input type="number" name="empresa_atual_id" required min="1" value="<?= htmlspecialchars($veiculo['empresa_atual_id']) ?>" />

        <label>KM Atual:</label>
        <input type="number" name="km_atual" required min="0" value="<?= htmlspecialchars($veiculo['km_atual']) ?>" />

        <label>Estado:</label>
        <select name="estado" required>
            <option value="">-- Selecionar --</option>
            <option value="Ativo" <?= $veiculo['estado'] == 'Ativo' ? 'selected' : '' ?>>Ativo</option>
            <option value="Inativo" <?= $veiculo['estado'] == 'Inativo' ? 'selected' : '' ?>>Inativo</option>
            <option value="Manutenção" <?= $veiculo['estado'] == 'Manutenção' ? 'selected' : '' ?>>Manutenção</option>
        </select>

        <button type="submit">Guardar Alterações</button>
    </form>
    <?php endif; ?>

    <a class="back-btn" href="ver_lista_veiculos.php">← Voltar à lista</a>
</div>

</body>
</html>
