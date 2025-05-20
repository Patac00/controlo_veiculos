<?php
include '../php/config.php';

$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $id_veiculo = mysqli_real_escape_string($con, $_POST['id_veiculo']);
  $id_utilizador = mysqli_real_escape_string($con, $_POST['id_utilizador']);
  $data_abastecimento = mysqli_real_escape_string($con, $_POST['data_abastecimento']);
  $km_registados = mysqli_real_escape_string($con, $_POST['km_registados']);
  $id_posto = mysqli_real_escape_string($con, $_POST['id_posto']);
  $litros = mysqli_real_escape_string($con, $_POST['litros']);
  $tipo_combustivel = isset($_POST['tipo_combustivel']) ? mysqli_real_escape_string($con, $_POST['tipo_combustivel']) : '';
  $observacoes = mysqli_real_escape_string($con, $_POST['observacoes']);
  $valor_total = mysqli_real_escape_string($con, $_POST['valor_total']);
  $data_abastecimento_raw = mysqli_real_escape_string($con, $_POST['data_abastecimento']);
  $data_abastecimento = date('Y-m-d H:i:s', strtotime($data_abastecimento_raw));


  $sql = "INSERT INTO abastecimentos 
    (id_veiculo, id_utilizador, data_abastecimento, km_registados, id_posto, litros, tipo_combustivel, observacoes, valor_total) 
    VALUES 
    ('$id_veiculo', '$id_utilizador', '$data_abastecimento', '$km_registados', '$id_posto', '$litros', '$tipo_combustivel', '$observacoes', '$valor_total')";

  if (mysqli_query($con, $sql)) {
    $msg = "Abastecimento registado com sucesso!";
  } else {
    $msg = "Erro: " . mysqli_error($con);
  }
}


// Veículos
$veiculos = [];
$res = mysqli_query($con, "SELECT id_veiculo, matricula FROM veiculos ORDER BY matricula");
while ($row = mysqli_fetch_assoc($res)) {
    $veiculos[] = $row;
}

// Utilizadores
$utilizadores = [];
$res = mysqli_query($con, "SELECT id_utilizador, nome FROM utilizadores ORDER BY nome");
while ($row = mysqli_fetch_assoc($res)) {
    $utilizadores[] = $row;
}

// Postos
$postos = [];
$res = mysqli_query($con, "SELECT id_posto, nome FROM lista_postos ORDER BY nome");
while ($row = mysqli_fetch_assoc($res)) {
    $postos[] = $row;
}

?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <title>Registar Abastecimento</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f1f3f4;
      margin: 0; padding: 0;
    }
    .container {
      max-width: 600px;
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
    input[type=text], input[type=number], input[type=date], select, textarea {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      border: 1px solid #ccc;
      border-radius: 8px;
      box-sizing: border-box;
    }
    textarea {
      resize: vertical;
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

    /* CSS para Select2 */
    .select2-container {
      width: 100% !important;
    }

    .select2-container--default .select2-selection--single {
      background-color: #fff;
      border: 1px solid #ccc;
      border-radius: 8px;
      height: 70px !important;
      display: flex;
      align-items: center;
      padding: 0 12px;
      box-sizing: border-box;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
      color: #444;
      line-height: normal !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 100%;
      position: absolute;
      top: 0;
      right: 10px;
      width: 20px;
    }
  </style>
</head>
<body>

<div class="container">
  <h2>Registar Abastecimento de Veiculos com Matricula</h2>

  <?php if ($msg): ?>
    <div class="msg <?= strpos($msg, 'sucesso') !== false ? 'success' : '' ?>"><?= $msg ?></div>
  <?php endif; ?>

  <form method="POST">
    <label class="form-label">Veículo</label>
    <select name="id_veiculo" class="select2" required>
      <option value="">Selecionar...</option>
      <?php foreach ($veiculos as $v): ?>
        <option value="<?= $v['id_veiculo'] ?>"><?= $v['matricula'] ?></option>
      <?php endforeach; ?>
    </select>

    <label class="form-label">Utilizador</label>
    <select name="id_utilizador" class="select2" required>
      <option value="">Selecionar...</option>
      <?php foreach ($utilizadores as $u): ?>
        <option value="<?= $u['id_utilizador'] ?>"><?= $u['nome'] ?></option>
      <?php endforeach; ?>
    </select>

    <label>Data e Hora do Abastecimento:</label>
    <input type="datetime-local" name="data_abastecimento" required>


    <label>KM Atuais:</label>
    <input type="number" name="km_registados" required>

    <label class="form-label">Posto</label>
    <select name="id_posto" class="select2" required>
      <option value="">Selecionar...</option>
      <?php foreach ($postos as $p): ?>
        <option value="<?= $p['id_posto'] ?>"><?= $p['nome'] ?></option>
      <?php endforeach; ?>
    </select>

    <label>Tipo de Combustível:</label>
    <select name="tipo_combustivel" required>
      <option value="">-- Selecionar --</option>
      <option value="Gasóleo">Gasóleo</option>
      <option value="Gasolina">Gasolina</option>
      <option value="GPL">GPL</option>
      <option value="Elétrico">Elétrico</option>
    </select>

    <div style="display: flex; gap: 10px; margin-top: 15px;">
      <div style="flex: 1;">
        <label>Litros:</label>
        <input type="number" step="0.01" name="litros" id="litros" required>
      </div>
      <div style="flex: 1;">
        <label>Preço por Litro (€):</label>
        <input type="number" step="0.0001" name="preco_litro" id="preco_litro" required>
      </div>
      <div style="flex: 1;">
        <label>Valor Total (€):</label>
        <input type="number" step="0.01" name="valor_total" id="valor_total" required readonly>
      </div>
    </div>

    <label>Observações:</label>
    <textarea name="observacoes" rows="2"></textarea>

    <button type="submit">Guardar Abastecimento</button>
  </form>

  <a class="back-btn" href="ver_lista_abastecimentos.php">← Ver Abastecimentos</a>
  <a class="back-btn" href="../html/index.php">← Voltar ao Início</a>
</div>

<!-- JQuery + Select2 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
  $(document).ready(function() {
    $('.select2').select2({
      width: '100%',
      placeholder: "Selecionar...",
      allowClear: true
    });
  });

  function calcularValorTotal() {
  const litros = parseFloat(document.getElementById('litros').value) || 0;
  const precoLitro = parseFloat(document.getElementById('preco_litro').value) || 0;
  const valorTotal = litros * precoLitro;
  document.getElementById('valor_total').value = valorTotal.toFixed(2);
  }

  document.getElementById('litros').addEventListener('input', calcularValorTotal);
  document.getElementById('preco_litro').addEventListener('input', calcularValorTotal);

</script>

</body>
</html>
