<?php 
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}
include("../php/config.php");

$msg = "";

// Buscar último km para min do input (default 0)
$kmAnterior = 0;
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    // Se não for submit, tentar obter último km do primeiro veículo para setar min
    $res = mysqli_query($con, "SELECT km_registados FROM abastecimentos ORDER BY data_abastecimento DESC LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        $kmAnterior = intval($row['km_registados']);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_veiculo = intval($_POST['id_veiculo']);
    $id_utilizador = intval($_POST['id_utilizador']);
    $data_abastecimento = date('Y-m-d H:i:s', strtotime($_POST['data_abastecimento']));
    $km_registados = intval($_POST['km_registados']);
    $km_anteriores = intval($_POST['km_anteriores']);
    $id_posto = intval($_POST['id_posto']);
    $litros = floatval($_POST['litros']);
    $preco_litro = floatval($_POST['preco_litro']);
    $valor_total = $litros * $preco_litro;
    $id_tipo_combustivel = intval($_POST['id_tipo_combustivel']);
    $observacoes = isset($_POST['observacoes']) ? trim($_POST['observacoes']) : '';

    // Validação km
    if ($km_registados < $km_anteriores) {
        $msg = "Erro: Os KM registados não podem ser inferiores ao último valor registado ($km_anteriores KM).";
    } else if (
        empty($id_veiculo) || empty($id_utilizador) || empty($data_abastecimento) ||
        empty($km_registados) || empty($id_posto) || empty($litros) || empty($id_tipo_combustivel)
    ) {
        $msg = "Por favor preencha todos os campos obrigatórios.";
    } else {
        // Prepared statement para inserir
        $stmt = $con->prepare("INSERT INTO abastecimentos 
        (id_veiculo, id_utilizador, data_abastecimento, km_registados, id_posto, litros, id_tipo_combustivel, observacoes, valor_total) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt === false) {
            $msg = "Erro na preparação da query: " . $con->error;
        } else {                    
                $stmt->bind_param("iisiddids", 
                    $id_veiculo, 
                    $id_utilizador, 
                    $data_abastecimento, 
                    $km_registados, 
                    $id_posto, 
                    $litros, 
                    $id_tipo_combustivel, 
                    $observacoes, 
                    $valor_total
                );

            if ($stmt->execute()) {
                $msg = "Abastecimento registado com sucesso!";
            } else {
                $msg = "Erro ao executar a query: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// AJAX interno para obter km e data/hora mais recente do veículo selecionado
if (isset($_GET['acao']) && $_GET['acao'] === 'obter_km' && isset($_GET['id_veiculo'])) {
    $id_veiculo = intval($_GET['id_veiculo']);
    $sql = "SELECT km_registados, data_abastecimento FROM abastecimentos WHERE id_veiculo = $id_veiculo ORDER BY data_abastecimento DESC LIMIT 1";
    $sql = "SELECT km_atual, data_atualizacao FROM veiculos WHERE id_veiculo = $id_veiculo";

    $res = mysqli_query($con, $sql);
if ($res && mysqli_num_rows($res) > 0) {
    $row = mysqli_fetch_assoc($res);
    echo json_encode([
        'km' => intval($row['km_atual']),
        'data' => $row['data_atualizacao']
    ]);
}
else {
        echo json_encode(['km' => 0, 'data' => 'Nenhum registo anterior']);
    }
    exit;
}

// Carregar dados para selects
$veiculos = [];
$res = mysqli_query($con, "SELECT id_veiculo, matricula FROM veiculos ORDER BY matricula");
while ($row = mysqli_fetch_assoc($res)) {
    $veiculos[] = $row;
}

$utilizadores = [];
$res = mysqli_query($con, "SELECT id_utilizador, nome FROM utilizadores ORDER BY nome");
while ($row = mysqli_fetch_assoc($res)) {
    $utilizadores[] = $row;
}

$postos = [];
$res = mysqli_query($con, "SELECT id_posto, nome FROM lista_postos ORDER BY nome");
while ($row = mysqli_fetch_assoc($res)) {
    $postos[] = $row;
}

$tipos_combustivel = [];
$res = mysqli_query($con, "SELECT id, nome FROM tipo_combustivel ORDER BY nome");
while ($row = mysqli_fetch_assoc($res)) {
    $tipos_combustivel[] = $row;
}
?>


<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <title>Registar Abastecimento</title>
  <style>
    /* Estilos iguais ao que tens */
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
    input[type=text], input[type=number], input[type=datetime-local], select, textarea {
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
  </style>
</head>
<body>

<div class="container">
  <h2>Registar Abastecimento de Veículos com Matrícula</h2>

  <?php if ($msg): ?>
    <div class="msg <?= strpos($msg, 'sucesso') !== false ? 'success' : '' ?>"><?= $msg ?></div>
  <?php endif; ?>

  <form method="POST" id="formAbastecimento">
    <!-- Select Veículo -->
    <label>Veículo</label>
    <select name="id_veiculo" id="veiculo" required>
      <option value="">Selecionar...</option>
      <?php foreach ($veiculos as $v): ?>
        <option value="<?= $v['id_veiculo'] ?>"><?= $v['matricula'] ?></option>
      <?php endforeach; ?>
    </select>

    <!-- Mostrar último KM e data -->
    <label>Último Registo:</label>
    <input type="text" id="ultimo_km_data" readonly>



    <!-- Campo hidden para guardar km anteriores -->
    <input type="hidden" id="km_anteriores" name="km_anteriores" value="0">

    <!-- Input KM atual -->
    <label>KM Atuais:</label>
    <input type="number" name="km_registados" id="km_registados" required>
    

    <!-- Select Utilizador -->
    <input type="hidden" name="id_utilizador" value="<?= $_SESSION['id_utilizador'] ?>">

    <!-- Data e Hora do Abastecimento -->
    <label>Data e Hora do Abastecimento:</label>
    <input type="datetime-local" name="data_abastecimento" required>

    <!-- Select Posto -->
    <label>Posto</label>
    <select name="id_posto" required>
      <option value="">Selecionar...</option>
      <?php foreach ($postos as $p): ?>
        <option value="<?= $p['id_posto'] ?>"><?= $p['nome'] ?></option>
      <?php endforeach; ?>
    </select>

    <!-- Tipo de Combustível -->
    <label>Tipo de Combustível:</label>
    <select name="id_tipo_combustivel" required>
  <option value="">Selecionar...</option>
  <?php foreach ($tipos_combustivel as $t): ?>
    <option value="<?= $t['id'] ?>"><?= $t['nome'] ?></option>
  <?php endforeach; ?>
</select>


    <!-- Litros, Preço e Valor Total -->
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
        <input type="number" step="0.01" id="valor_total" readonly>

      </div>
    </div>

    <!-- Observações -->
    <label>Observações:</label>
    <textarea name="observacoes" rows="2"></textarea>

    <button type="submit">Guardar Abastecimento</button>
  </form>

  <a class="back-btn" href="verifica_registo.php">← Ver Abastecimentos</a>
  <a class="back-btn" href="../html/index.php">← Voltar ao Início</a>
</div>

<script>
  // Função para calcular valor total
  function calcularValorTotal() {
    const litros = parseFloat(document.getElementById('litros').value) || 0;
    const precoLitro = parseFloat(document.getElementById('preco_litro').value) || 0;
    const valorTotal = litros * precoLitro;
    document.getElementById('valor_total').value = valorTotal.toFixed(2);
  }

  document.getElementById('litros').addEventListener('input', calcularValorTotal);
  document.getElementById('preco_litro').addEventListener('input', calcularValorTotal);

  // Variável global para km anteriores
  let kmAnteriores = 0;

  // Quando muda o veículo, buscar último km e data via AJAX interno
  document.getElementById('veiculo').addEventListener('change', function() {
    const idVeiculo = this.value;
    if (!idVeiculo) {
      document.getElementById('ultimo_km_data').value = "Nenhum registo anterior";
      document.getElementById('km_anteriores').value = 0;
      kmAnteriores = 0;
      document.getElementById('km_registados').value = '';
      return;
    }

    fetch(`<?= basename(__FILE__) ?>?acao=obter_km&id_veiculo=${idVeiculo}`)
    .then(response => response.json())
    .then(data => {
      kmAnteriores = parseInt(data.km) || 0;
      document.getElementById('km_anteriores').value = kmAnteriores; // <-- ESTA LINHA FALTAVA!

      if (data.km > 0) {
        document.getElementById('ultimo_km_data').value = `Último KM: ${data.km} | Data: ${data.data}`;
      } else {
        document.getElementById('ultimo_km_data').value = "Nenhum registo anterior";
      }

      document.getElementById('km_registados').value = '';
    });

  });

  // Validação KM atual: não pode ser inferior ao anterior
  document.getElementById('formAbastecimento').addEventListener('submit', function(e) {
    const kmAtual = parseInt(document.getElementById('km_registados').value);
    const kmAnteriores = parseInt(document.getElementById('km_anteriores').value);

    if (isNaN(kmAtual) || kmAtual < kmAnteriores) {
      e.preventDefault(); // impede submit
      alert(`Erro: Os KM registados não podem ser inferiores ao último valor registado (${kmAnteriores} KM).`);
    }
  });
  

</script>

</body> 
</html>
