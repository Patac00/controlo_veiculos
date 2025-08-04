<?php 

session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");
$msg = "";
$matricula_antiga = "";
$empresas = [];
$tipos_combustivel = [];

// Buscar empresas para dropdown
$empresa_result = mysqli_query($con, "SELECT empresa_id, nome FROM empresas ORDER BY nome");
while ($row = mysqli_fetch_assoc($empresa_result)) {
    $empresas[] = $row;
}

// Buscar tipos de combustível para dropdown
$combustivel_result = mysqli_query($con, "SELECT id, nome FROM tipo_combustivel ORDER BY nome");
while ($row = mysqli_fetch_assoc($combustivel_result)) {
    $tipos_combustivel[] = $row;
}

if (isset($_GET['matricula'])) {
    $matricula_antiga = mysqli_real_escape_string($con, $_GET['matricula']);
    
    $sql = "SELECT v.*, g.nome AS grupo_nome, g.tipo 
            FROM veiculos v 
            LEFT JOIN grupos g ON v.Grupo = g.nome 
            WHERE v.matricula = ?";

    
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "s", $matricula_antiga);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 1) {
        $veiculo = mysqli_fetch_assoc($result);
        $grupo_nome = $veiculo['grupo_nome'];
        $tipo = $veiculo['Tipo'];  // por exemplo, se o campo for 'Tipo' no veículo
    } else {
        $msg = "Veículo não encontrado.";
    }
} else {
    $msg = "Matrícula não especificada.";
}


$sql = "SELECT v.*, g.nome AS grupo_nome, g.tipo 
        FROM veiculos v 
        LEFT JOIN grupos g ON v.grupo_id = g.id 
        WHERE v.matricula = ?";


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        isset($_POST['Matricula'], $_POST['Descricao'], $_POST['empresa_atual_id'], $_POST['Tipo'], 
              $_POST['Grupo'], $_POST['km_atual'], $_POST['estado'], $_POST['matricula_antiga'], 
              $_POST['relatorio'], $_POST['criterio'], $_POST['abastecimentos'], $_POST['medida'], 
              $_POST['horas_atual'], $_POST['capacidade_tanque'], $_POST['id_tipo_combustivel'])
    ) {
        $matricula_nova = strtoupper(preg_replace('/[^A-Z0-9]/', '', trim($_POST['Matricula'])));
        if (strlen($matricula_nova) === 6) {
            $matricula_nova = substr($matricula_nova, 0, 2) . '-' . substr($matricula_nova, 2, 2) . '-' . substr($matricula_nova, 4, 2);
        }

        $descricao = mysqli_real_escape_string($con, $_POST['Descricao']);
        $empresa_atual_id = (int)$_POST['empresa_atual_id'];
        $tipo = mysqli_real_escape_string($con, $_POST['Tipo']);
        $grupo = mysqli_real_escape_string($con, $_POST['Grupo']);
        $km_atual = (int)$_POST['km_atual'];
        $estado = mysqli_real_escape_string($con, $_POST['estado']);
        $matricula_antiga_post = mysqli_real_escape_string($con, $_POST['matricula_antiga']);
        $relatorio = ($_POST['relatorio'] === 'Sim') ? 'Sim' : 'Não';
        $criterio = mysqli_real_escape_string($con, $_POST['criterio']);
        $abastecimentos = (int)$_POST['abastecimentos'];
        $medida = in_array($_POST['medida'], ['Horas','Km']) ? $_POST['medida'] : null;
        $horas_atual = (int)$_POST['horas_atual'];
        $capacidade_tanque = (int)$_POST['capacidade_tanque'];
        $id_tipo_combustivel = (int)$_POST['id_tipo_combustivel'];

        if (empty($matricula_nova)) {
            $msg = "Erro: Matrícula não pode estar vazia.";
        } elseif (empty($empresa_atual_id)) {
            $msg = "Erro: Tens de selecionar uma empresa.";
        } elseif ($matricula_nova !== $matricula_antiga_post) {
            $stmt_check = mysqli_prepare($con, "SELECT * FROM veiculos WHERE matricula = ?");
            mysqli_stmt_bind_param($stmt_check, "s", $matricula_nova);
            mysqli_stmt_execute($stmt_check);
            $check_result = mysqli_stmt_get_result($stmt_check);
            if (mysqli_num_rows($check_result) > 0) {
                $msg = "Erro: Já existe um veículo com essa matrícula.";
            }
        }

        if (!$msg) {
            $stmt_update = mysqli_prepare($con, "UPDATE veiculos SET 
                matricula = ?, Descricao = ?, Tipo = ?, Grupo = ?, km_atual = ?, estado = ?, empresa_atual_id = ?,
                relatorio = ?, criterio = ?, abastecimentos = ?, medida = ?, horas_atual = ?, capacidade_tanque = ?, id_tipo_combustivel = ?
                WHERE matricula = ?");
            mysqli_stmt_bind_param($stmt_update, "ssssisissisiiis", 
                $matricula_nova, $descricao, $tipo, $grupo, $km_atual, $estado, $empresa_atual_id,
                $relatorio, $criterio, $abastecimentos, $medida, $horas_atual, $capacidade_tanque, $id_tipo_combustivel,
                $matricula_antiga_post);

            if (mysqli_stmt_execute($stmt_update)) {
                $msg = "Veículo atualizado com sucesso.";
                $matricula_antiga = $matricula_nova;
                $stmt = mysqli_prepare($con, "SELECT * FROM veiculos WHERE matricula = ?");
                mysqli_stmt_bind_param($stmt, "s", $matricula_nova);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $veiculo = mysqli_fetch_assoc($result);
            } else {
                $msg = "Erro na atualização: " . mysqli_error($con);
            }
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
        body {
            font-family: Arial, sans-serif;
            background-color: #f1f3f4;
            margin: 0; padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 50px auto;
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
            box-sizing: border-box;
        }
        button {
            background-color: #00693e;
            color: white;
            padding: 10px 20px;
            margin-top: 25px;
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
        const matriculaInput = document.querySelector('input[name="Matricula"]');
        const avisoDiv = document.createElement('div');
        avisoDiv.style.color = 'orange';
        avisoDiv.style.marginBottom = '10px';
        avisoDiv.style.fontWeight = 'bold';
        avisoDiv.style.display = 'none';
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
    <input type="text" name="Matricula" required maxlength="15" value="<?= htmlspecialchars($veiculo['matricula']) ?>" />

    <label>Descrição:</label>
    <input type="text" name="Descricao" required value="<?= htmlspecialchars($veiculo['Descricao']) ?>" />

    <label>Empresa:</label>
    <select name="empresa_atual_id" required>
        <option value="">-- Selecionar Empresa --</option>
        <?php foreach ($empresas as $empresa): ?>
            <option value="<?= $empresa['empresa_id'] ?>" <?= $veiculo['empresa_atual_id'] == $empresa['empresa_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($empresa['nome']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <!-- Campo Tipo (aparece só se fizer sentido) -->
    <?php if (!in_array($grupo_nome, ['Empilhador', 'Máquina', 'Outros', 'Pesado'])): ?>
    <div class="mb-3">
        <label for="tipo" class="form-label">Tipo</label>
        <input type="text" class="form-control" id="tipo" name="tipo" value="<?= htmlspecialchars($tipo) ?>" readonly>
    </div>
    <?php endif; ?>

    <label>Grupo:</label>
    <input type="text" name="Grupo" value="<?= htmlspecialchars($veiculo['Grupo']) ?>" />

    <label>Abastecimentos:</label>
    <input type="number" name="abastecimentos" min="0" value="<?= htmlspecialchars($veiculo['abastecimentos']) ?>" />

    <label>Medida:</label>
    <select name="medida" id="medida" required>
        <option value="">-- Selecionar --</option>
        <option value="Horas" <?= $veiculo['medida'] == 'Horas' ? 'selected' : '' ?>>Horas</option>
        <option value="Km" <?= $veiculo['medida'] == 'Km' ? 'selected' : '' ?>>Km</option>
    </select>

    <label>KM Atual:</label>
    <input type="number" name="km_atual" required min="0" value="<?= htmlspecialchars($veiculo['km_atual']) ?>" />

    <!-- Horas Atual só se medida = Horas -->
    <div id="horas_atual_container" style="display: <?= ($veiculo['medida'] == 'Horas') ? 'block' : 'none' ?>;">
        <label>Horas Atual:</label>
        <input type="number" name="horas_atual" min="0" value="<?= htmlspecialchars($veiculo['horas_atual']) ?>" />
    </div>

    <label>Estado:</label>
    <select name="estado" required>
        <option value="">-- Selecionar --</option>
        <option value="Ativo" <?= $veiculo['estado'] == 'Ativo' ? 'selected' : '' ?>>Ativo</option>
        <option value="Inativo" <?= $veiculo['estado'] == 'Inativo' ? 'selected' : '' ?>>Inativo</option>
        <option value="Manutenção" <?= $veiculo['estado'] == 'Manutenção' ? 'selected' : '' ?>>Manutenção</option>
    </select>

    <label>Capacidade Tanque (L):</label>
    <input type="number" name="capacidade_tanque" min="0" value="<?= htmlspecialchars($veiculo['capacidade_tanque']) ?>" />

    <label>Tipo Combustível:</label>
    <select name="id_tipo_combustivel">
        <option value="">-- Selecionar --</option>
        <?php foreach ($tipos_combustivel as $tipo_comb): ?>
            <option value="<?= $tipo_comb['id'] ?>" <?= $veiculo['id_tipo_combustivel'] == $tipo_comb['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($tipo_comb['nome']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Guardar Alterações</button>
</form>
    <?php endif; ?>

    <a class="back-btn" href="ver_lista_veiculos.php">← Voltar à lista</a>
</div>


<script>
    // Mostrar/esconder Horas Atual conforme Medida escolhida
    const medidaSelect = document.getElementById('medida');
    const horasContainer = document.getElementById('horas_atual_container');

    medidaSelect.addEventListener('change', () => {
        if(medidaSelect.value === 'Horas') {
            horasContainer.style.display = 'block';
        } else {
            horasContainer.style.display = 'none';
        }
    });
</script>

</body>
</html>
