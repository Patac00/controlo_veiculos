<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");
$con->set_charset("utf8mb4");

// Pega o id do abastecimento a editar
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("ID inválido.");
}

// Buscar dados atuais do abastecimento
$sql = "SELECT * FROM abastecimentos WHERE id_abastecimento = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$abastecimento = $result->fetch_assoc();

if (!$abastecimento) {
    die("Abastecimento não encontrado.");
}

// Carregar listas necessárias (exemplo: veículos, empresas, postos, combustíveis)
$veiculos = [];
$resV = $con->query("SELECT id_veiculo, matricula FROM veiculos ORDER BY matricula");
while ($v = $resV->fetch_assoc()) $veiculos[] = $v;

$empresas = [];
$resE = $con->query("SELECT empresa_id, nome FROM empresas ORDER BY nome");
while ($e = $resE->fetch_assoc()) $empresas[] = $e;

$postos = [];
$resP = $con->query("SELECT id_posto, nome FROM lista_postos ORDER BY nome");
while ($p = $resP->fetch_assoc()) $postos[] = $p;

$combustiveis = [];
$resC = $con->query("SELECT id, nome FROM tipo_combustivel ORDER BY nome");
while ($c = $resC->fetch_assoc()) $combustiveis[] = $c;

// Processar submit do formulário
$msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_veiculo = (int)$_POST['id_veiculo'];
    $empresa_id = (int)$_POST['empresa_id'];
    $data_abastecimento = $_POST['data_abastecimento'];
    $km_registados = !empty($_POST['km_registados']) ? (int)$_POST['km_registados'] : null;
    $horas_registadas = !empty($_POST['horas_registadas']) ? (float)$_POST['horas_registadas'] : null;
    $id_posto = (int)$_POST['id_posto'];
    $litros = (float)$_POST['litros'];
    $id_tipo_combustivel = (int)$_POST['id_tipo_combustivel'];
    $preco_litro = (float)$_POST['preco_litro'];
    $valor_total = (float)$_POST['valor_total'];
    $observacoes = $_POST['observacoes'];

    // Validações simples (podes ampliar)
    if (empty($id_veiculo) || empty($data_abastecimento) || empty($id_posto) || empty($litros) || empty($id_tipo_combustivel)) {
        $msg = "Por favor, preenche os campos obrigatórios.";
    } else {
        // Atualizar na BD
        $sqlUpdate = "UPDATE abastecimentos SET 
            id_veiculo=?, empresa_id=?, data_abastecimento=?, km_registados=?, horas_registadas=?, 
            id_posto=?, litros=?, id_tipo_combustivel=?, preco_litro=?, valor_total=?, observacoes=? 
            WHERE id_abastecimento=?";
        $stmtUpdate = $con->prepare($sqlUpdate);
        $stmtUpdate->bind_param(
            "issiiididssi", 
            $id_veiculo, $empresa_id, $data_abastecimento, $km_registados, $horas_registadas,
            $id_posto, $litros, $id_tipo_combustivel, $preco_litro, $valor_total, $observacoes, $id
        );
        if ($stmtUpdate->execute()) {
            header("Location: ver_lista_abastecimentos.php?msg=editado");
            exit();
        } else {
            $msg = "Erro ao atualizar o abastecimento.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <title>Editar Abastecimento</title>
  <style>
    body {
        font-family: Arial, sans-serif;
        margin: 80px 0 40px 0;
        overflow-y: auto; /* scroll da página */
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        background: #f9f9f9;
    }
    .container {
        max-width: 600px;
        margin: 0 auto;
        background: white;
        padding: 30px 40px;
        border-radius: 8px;
        box-shadow: 0 0 12px rgba(0,0,0,0.1);
        box-sizing: border-box;
    }
    h2 {
        color: #00693e;
        text-align: center;
        margin-bottom: 20px;
    }
    form {
      width: 100%;
    }
    label {
      display: block;
      margin-top: 15px;
      font-weight: bold;
    }
    input, select, textarea {
      width: 100%;
      padding: 8px;
      margin-top: 6px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 14px;
      box-sizing: border-box;
    }
    button {
      margin-top: 25px;
      background: #00693e;
      color: white;
      padding: 12px 20px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 16px;
      width: 100%;
      transition: background-color 0.3s ease;
    }
    button:hover {
      background: #005632;
    }
    .msg {
      color: red;
      margin-top: 15px;
      text-align: center;
    }
    a.btn-voltar {
      display: block;
      margin-bottom: 20px;
      color: #00693e;
      text-decoration: none;
      font-weight: 600;
      text-align: center;
    }
    a.btn-voltar:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

<div class="container">

  <a href="ver_lista_abastecimentos.php" class="btn-voltar">← Voltar à lista</a>

  <h2>Editar Abastecimento #<?= $id ?></h2>

  <?php if ($msg): ?>
    <div class="msg"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>

  <form method="post" action="">
    <label for="id_veiculo">Veículo *</label>
    <select name="id_veiculo" id="id_veiculo" required>
      <option value="">Seleciona um veículo</option>
      <?php foreach ($veiculos as $v): ?>
        <option value="<?= $v['id_veiculo'] ?>" <?= ($v['id_veiculo'] == $abastecimento['id_veiculo']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($v['matricula']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="empresa_id">Empresa</label>
    <select name="empresa_id" id="empresa_id">
      <option value="">Seleciona empresa</option>
      <?php foreach ($empresas as $e): ?>
        <option value="<?= $e['empresa_id'] ?>" <?= ($e['empresa_id'] == $abastecimento['empresa_id']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($e['nome']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="data_abastecimento">Data e Hora *</label>
    <input type="datetime-local" name="data_abastecimento" id="data_abastecimento" value="<?= date('Y-m-d\TH:i', strtotime($abastecimento['data_abastecimento'])) ?>" required>

    <label for="km_registados">KM Registados</label>
    <input type="number" name="km_registados" id="km_registados" value="<?= htmlspecialchars($abastecimento['km_registados']) ?>">

    <label for="horas_registadas">Horas Registadas</label>
    <input type="number" step="0.01" name="horas_registadas" id="horas_registadas" value="<?= htmlspecialchars($abastecimento['horas_registadas']) ?>">

    <label for="id_posto">Posto *</label>
    <select name="id_posto" id="id_posto" required>
      <option value="">Seleciona um posto</option>
      <?php foreach ($postos as $p): ?>
        <option value="<?= $p['id_posto'] ?>" <?= ($p['id_posto'] == $abastecimento['id_posto']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($p['nome']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="litros">Litros *</label>
    <input type="number" step="0.01" name="litros" id="litros" value="<?= htmlspecialchars($abastecimento['litros']) ?>" required>

    <label for="id_tipo_combustivel">Tipo de Combustível *</label>
    <select name="id_tipo_combustivel" id="id_tipo_combustivel" required>
      <option value="">Seleciona o combustível</option>
      <?php foreach ($combustiveis as $c): ?>
        <option value="<?= $c['id'] ?>" <?= ($c['id'] == $abastecimento['id_tipo_combustivel']) ? 'selected' : '' ?>>
          <?= htmlspecialchars($c['nome']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="preco_litro">Preço/Litro</label>
    <input type="number" step="0.01" name="preco_litro" id="preco_litro" value="<?= htmlspecialchars($abastecimento['preco_litro']) ?>">

    <label for="valor_total">Valor Total</label>
    <input type="number" step="0.01" name="valor_total" id="valor_total" value="<?= htmlspecialchars($abastecimento['valor_total']) ?>">

    <label for="observacoes">Observações</label>
    <textarea name="observacoes" id="observacoes" rows="4"><?= htmlspecialchars($abastecimento['observacoes']) ?></textarea>

    <button type="submit">Guardar Alterações</button>
  </form>

</div>

</body>
</html>
