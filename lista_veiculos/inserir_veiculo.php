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
    $grupo = mysqli_real_escape_string($con, $_POST['grupo']);
    $empresa_atual_id = (int)$_POST['empresa_atual_id'];
    $tipo_medida = $_POST['tipo_medida'];
    $estado = mysqli_real_escape_string($con, $_POST['estado']);

    // Gerar a Descrição
    $descricao = $marca . ' ' . $modelo;

    $km_atual = ($tipo_medida === 'km') ? (int)$_POST['km_atual'] : null;
    $horas_atual = ($tipo_medida === 'horas') ? (int)$_POST['horas_atual'] : null;

    // Se tipo medida for horas e matricula vazia, colocar NULL
    if ($tipo_medida === 'horas' && empty($matricula)) {
        $matricula = null;
    } else {
        // Verificar duplicação só se matricula não for null
        $check_sql = "SELECT * FROM veiculos WHERE matricula = '$matricula'";
        $check_result = mysqli_query($con, $check_sql);

        if (mysqli_num_rows($check_result) > 0) {
            $msg = "Erro: Veículo com esta matrícula já existe.";
        }
    }

    if (!$msg) {
        $sql = "INSERT INTO veiculos (matricula, Descricao, empresa_atual_id, Tipo, Grupo, km_atual, horas_atual, estado) 
                VALUES (" . ($matricula !== null ? "'$matricula'" : "NULL") . ", '$descricao', $empresa_atual_id, '$tipo_veiculo', '$grupo', " . 
                ($km_atual !== null ? $km_atual : "NULL") . ", " . 
                ($horas_atual !== null ? $horas_atual : "NULL") . ", '$estado')";

        if (mysqli_query($con, $sql)) {
            $msg = "Veículo inserido com sucesso.";
        } else {
            $msg = "Erro: " . mysqli_error($con);
        }
    }
}

// Buscar empresas
$empresas = [];
$empresas_sql = "SELECT id_empresa, nome FROM empresas ORDER BY nome ASC";
$empresas_result = mysqli_query($con, $empresas_sql);
if ($empresas_result) {
    while ($row = mysqli_fetch_assoc($empresas_result)) {
        $empresas[] = $row;
    }
}

// Buscar grupos
$grupos = [];
$grupos_sql = "SELECT nome FROM grupos ORDER BY nome ASC";
$grupos_result = mysqli_query($con, $grupos_sql);
if ($grupos_result) {
    while ($row = mysqli_fetch_assoc($grupos_result)) {
        $grupos[] = $row;
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
            max-width: 500px !important;
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

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

</head>
<body>

<div class="container">
    <?php if ($msg): ?>
        <div class="msg <?= strpos($msg, 'sucesso') !== false ? 'success' : '' ?>"><?= $msg ?></div>
    <?php endif; ?>

    <h2>Inserir Veículo</h2>

    <form action="" method="POST">
        <!-- Tipo de Medida -->
        <label class="form-label d-block">Tipo de Medida:</label>
        <div class="btn-group w-100" role="group">
            <input type="radio" class="btn-check" name="tipo_medida" id="tipo_km" value="km" required>
            <label class="btn btn-outline-primary" for="tipo_km">KM</label>

            <input type="radio" class="btn-check" name="tipo_medida" id="tipo_horas" value="horas">
            <label class="btn btn-outline-primary" for="tipo_horas">Horas</label>
        </div>

        <!-- Matrícula -->
        <div id="matricula_group" style="margin-top: 15px; max-width: 300px; display: flex; align-items: center; gap: 5px; position: relative;">
    <label for="matricula_input" style="flex-shrink: 0; width: 80px;">Matrícula:</label>
    <input type="text" name="matricula" id="matricula_input" maxlength="15" class="form-control" style="flex-grow: 1;" />
    <div id="matricula_toggle" style="display: none; flex-shrink: 0;">
        <span id="toggle_hide" style="cursor: pointer; color: red; font-size: 20px;">❌</span>
        <span id="toggle_show" style="cursor: pointer; color: green; font-size: 20px; display: none;">✔️</span>
    </div>
</div>


        <!-- Marca -->
        <label>Marca:</label>
        <input type="text" name="marca" required />

        <!-- Modelo -->
        <label>Modelo:</label>
        <input type="text" name="modelo" required />

        <!-- Tipo de Veículo -->
        <label>Tipo de Veículo:</label>
        <select name="tipo_veiculo" required>
            <option value="">-- Selecionar --</option>
            <option value="Carro">Carro</option>
            <option value="Camião">Camião</option>
            <option value="Mota">Mota</option>
            <option value="Outro">Outro</option>
        </select>

        <!-- Empresa -->
        <label>Empresa Atual:</label>
        <select name="empresa_atual_id" required>
            <option value="">-- Selecionar --</option>
            <?php foreach ($empresas as $empresa): ?>
                <option value="<?= $empresa['id_empresa'] ?>"><?= htmlspecialchars($empresa['nome']) ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Grupo -->
        <label>Grupo:</label>
        <select name="grupo" required>
            <option value="">-- Selecionar --</option>
            <?php foreach ($grupos as $g): ?>
                <option value="<?= htmlspecialchars($g['nome']) ?>"><?= htmlspecialchars($g['nome']) ?></option>
            <?php endforeach; ?>
        </select>

        <!-- KM -->
        <div id="input_km" style="display:none;">
            <label>KM Atual:</label>
            <input type="number" name="km_atual" min="0" />
        </div>

        <!-- Horas -->
        <div id="input_horas" style="display:none;">
            <label>Horas Atual:</label>
            <input type="number" name="horas_atual" min="0" />
        </div>

        <!-- Estado -->
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

<script>
    const matriculaInput = document.getElementById('matricula_input');
    const toggleHide = document.getElementById('toggle_hide');
    const toggleShow = document.getElementById('toggle_show');
    const toggleArea = document.getElementById('matricula_toggle');
    const kmDiv = document.getElementById('input_km');
    const horasDiv = document.getElementById('input_horas');
    const kmInput = document.querySelector('input[name="km_atual"]');
    const horasInput = document.querySelector('input[name="horas_atual"]');

    // Função para obter o tipo de medida selecionado
    function getTipoMedida() {
        const sel = document.querySelector('input[name="tipo_medida"]:checked');
        return sel ? sel.value : null;
    }

    // Atualizar UI quando muda o tipo de medida
    document.querySelectorAll('input[name="tipo_medida"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const tipo = getTipoMedida();

            if (tipo === 'km') {
                kmDiv.style.display = 'block';
                horasDiv.style.display = 'none';
                kmInput.required = true;
                horasInput.required = false;

                toggleArea.style.display = 'none';
                matriculaInput.required = true;
                matriculaInput.disabled = false;
                matriculaInput.style.opacity = 1;

                // Mostrar o toggle como escondido por defeito
                toggleHide.style.display = 'inline';
                toggleShow.style.display = 'none';
            } else if (tipo === 'horas') {
                kmDiv.style.display = 'none';
                horasDiv.style.display = 'block';
                kmInput.required = false;
                horasInput.required = true;

                toggleArea.style.display = 'inline';
                matriculaInput.required = false;
                matriculaInput.disabled = false;
                matriculaInput.style.opacity = 1;

                toggleHide.style.display = 'inline';
                toggleShow.style.display = 'none';
            }
        });
    });

    // Clicar no X para esconder matrícula (desativa input)
    toggleHide.addEventListener('click', () => {
        matriculaInput.value = '';
        matriculaInput.disabled = true;
        matriculaInput.required = false;
        matriculaInput.style.opacity = 0.5;
        toggleHide.style.display = 'none';
        toggleShow.style.display = 'inline';
    });

    // Clicar no ✔️ para mostrar matrícula (ativa input)
    toggleShow.addEventListener('click', () => {
        matriculaInput.disabled = false;
        matriculaInput.required = false; // continua opcional no modo horas
        matriculaInput.style.opacity = 1;
        toggleShow.style.display = 'none';
        toggleHide.style.display = 'inline';
    });
</script>

</body>
</html>
