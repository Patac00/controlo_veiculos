<?php 
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}
include("../php/config.php");

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matricula = mysqli_real_escape_string($con, $_POST['matricula']);
    $marca = mysqli_real_escape_string($con, $_POST['marca']);
    $modelo = mysqli_real_escape_string($con, $_POST['modelo']);
    $tipo_veiculo = mysqli_real_escape_string($con, $_POST['tipo_veiculo']);
    $empresa_atual_id = (int)$_POST['empresa_atual_id'];
    $km_atual = (int)$_POST['km_atual'];
    $estado = mysqli_real_escape_string($con, $_POST['estado']);

    // Verificar se a matrícula já existe
    $check_sql = "SELECT * FROM veiculos WHERE matricula = '$matricula'";
    $check_result = mysqli_query($con, $check_sql);

    if (mysqli_num_rows($check_result) > 0) {
        $msg = "Erro: Veículo com esta matrícula já existe.";
    } else {
        $sql = "INSERT INTO veiculos (matricula, marca, modelo, tipo_veiculo, empresa_atual_id, km_atual, estado) 
                VALUES ('$matricula', '$marca', '$modelo', '$tipo_veiculo', $empresa_atual_id, $km_atual, '$estado')";

        if (mysqli_query($con, $sql)) {
            $msg = "Veículo inserido com sucesso.";
        } else {
            $msg = "Erro: " . mysqli_error($con);
        }
    }
}
    // Buscar empresas
    $empresas = [];
    $empresas_sql = "SELECT id_empresa, nome_empresa FROM empresas ORDER BY nome_empresa ASC";
    $empresas_result = mysqli_query($con, $empresas_sql);
    if ($empresas_result) {
        while ($row = mysqli_fetch_assoc($empresas_result)) {
            $empresas[] = $row;
        }
    }

?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8" />
    <title>Inserir Veículo</title>
    <style>
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
</head>
<body>

<div class="container">
    <h2>Inserir Veículo</h2>

    <?php if ($msg): ?>
        <div class="msg <?= strpos($msg, 'sucesso') !== false ? 'success' : '' ?>"><?= $msg ?></div>
    <?php endif; ?>

    <form action="" method="POST">
        <label>Matrícula:</label>
        <input type="text" name="matricula" required maxlength="15" />

        <label>Marca:</label>
        <input type="text" name="marca" required />

        <label>Modelo:</label>
        <input type="text" name="modelo" required />

        <label>Tipo de Veículo:</label>
        <select name="tipo_veiculo" required>
            <option value="">-- Selecionar --</option>
            <option value="Carro">Carro</option>
            <option value="Camião">Camião</option>
            <option value="Mota">Mota</option>
            <option value="Outro">Outro</option>
        </select>

        <label>Empresa Atual:</label>
        <select name="empresa_atual_id" required>
            <option value="">-- Selecionar Empresa --</option>
            <?php foreach ($empresas as $empresa): ?>
                <option value="<?= $empresa['id_empresa'] ?>">
                    <?= htmlspecialchars($empresa['nome_empresa']) ?>
                </option>
            <?php endforeach; ?>
        </select>


        <label>KM Atual:</label>
        <input type="number" name="km_atual" required min="0" />

        <label>Estado:</label>
        <select name="estado" required>
            <option value="">-- Selecionar --</option>
            <option value="Ativo">Ativo</option>
            <option value="Inativo">Inativo</option>
            <option value="Manutenção">Manutenção</option>
        </select>

        <button type="submit">Guardar Veículo</button>
    </form>

    <a class="back-btn" href="ver_lista_veiculos.php">← Ver Lista de Veículos</a>
    <a class="back-btn" href="../html/index.php">← Voltar ao Início</a>
</div>

</body>
</html>
