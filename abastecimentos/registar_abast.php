<?php 
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}
include("../php/config.php");
date_default_timezone_set('Europe/Lisbon');

$msg = "";

// Obter capacidade do tanque de combustivel
$capacidades = [];
$res = mysqli_query($con, "SELECT id_veiculo, capacidade_tanque FROM veiculos");
while ($row = mysqli_fetch_assoc($res)) {
    $capacidades[$row['id_veiculo']] = (int)$row['capacidade_tanque'];
}

// Se for POST, processar o registo
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_veiculo = intval($_POST['id_veiculo']);
    $id_utilizador = intval($_POST['id_utilizador']);
    $empresa_id = intval($_POST['empresa_id']);
    $data_abastecimento = date('Y-m-d H:i:s', strtotime($_POST['data_abastecimento']));
    $km_registados = intval($_POST['km_registados']);
    $km_anteriores = intval($_POST['km_anteriores']);
    $id_posto = intval($_POST['id_posto']);
    $litros = floatval($_POST['litros']);
    $capacidade = $capacidades[$id_veiculo] ?? null;
    $preco_litro = floatval($_POST['preco_litro']);
    $valor_total = $litros * $preco_litro;
    $id_tipo_combustivel = intval($_POST['id_tipo_combustivel']);
    $observacoes = isset($_POST['observacoes']) ? trim($_POST['observacoes']) : '';

    // Validações
    if ($capacidade !== null && $litros > $capacidade) {
        $msg = "Erro: Não pode abastecer mais litros ($litros) do que a capacidade do tanque do veículo ($capacidade litros).";
    } else if ($km_registados <= $km_anteriores) {
        $msg = "Erro: Os KM registados têm de ser superiores ao último valor registado para esta data ($km_anteriores KM).";
    } else if (
        empty($id_veiculo) || empty($id_utilizador) || empty($data_abastecimento) ||
        empty($km_registados) || empty($id_posto) || empty($litros) || empty($id_tipo_combustivel)
    ) {
        $msg = "Por favor preencha todos os campos obrigatórios.";
    }

    if (empty($msg)) {
        $stmt = $con->prepare("INSERT INTO abastecimentos 
        (id_veiculo, id_utilizador, empresa_id, data_abastecimento, km_registados, id_posto, litros, preco_litro, valor_total, id_tipo_combustivel, observacoes) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt === false) {
            $msg = "Erro na preparação da query: " . $con->error;
        } else {
            $stmt->bind_param("iiisiddddis", 
                $id_veiculo, 
                $id_utilizador, 
                $empresa_id,
                $data_abastecimento, 
                $km_registados, 
                $id_posto, 
                $litros, 
                $preco_litro,
                $valor_total,
                $id_tipo_combustivel, 
                $observacoes
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

// AJAX para obter último km antes da data selecionada
if (isset($_GET['acao']) && $_GET['acao'] === 'obter_km' && isset($_GET['id_veiculo'])) {
    $id_veiculo = intval($_GET['id_veiculo']);
    $data = isset($_GET['data']) ? $_GET['data'] : null;

    if ($data) {
        $sql = "SELECT km_registados, data_abastecimento 
                FROM abastecimentos 
                WHERE id_veiculo = $id_veiculo AND data_abastecimento <= '$data'
                ORDER BY data_abastecimento DESC 
                LIMIT 1";
    } else {
        $sql = "SELECT km_registados, data_abastecimento 
                FROM abastecimentos 
                WHERE id_veiculo = $id_veiculo 
                ORDER BY data_abastecimento DESC 
                LIMIT 1";
    }

    $res = mysqli_query($con, $sql);
    if ($res && mysqli_num_rows($res) > 0) {
        $row = mysqli_fetch_assoc($res);
        echo json_encode([
            'km' => intval($row['km_registados']),
            'data' => $row['data_abastecimento']
        ]);
    } else {
        echo json_encode(['km' => 0, 'data' => 'Nenhum registo anterior']);
    }
    exit;
}

// Obter empresa do utilizador
$id_utilizador = $_SESSION['id_utilizador'];
$empresa_id = null;
$res = $con->query("SELECT empresa_id FROM utilizadores WHERE id_utilizador = $id_utilizador LIMIT 1");
if ($res && $row = $res->fetch_assoc()) {
    $empresa_id = $row['empresa_id'];
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

// Puxar id do combustível "Gasóleo"
$gasoleo_id = null;
$res_gasoleo = $con->query("SELECT id FROM tipo_combustivel WHERE nome = 'Gasóleo' LIMIT 1");
if ($res_gasoleo && $row_gasoleo = $res_gasoleo->fetch_assoc()) {
    $gasoleo_id = $row_gasoleo['id'];
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
    #ultimo_km_data, #valor_total {
      background-color: #f0f0f0; /* Cinza claro */
      color: #333; /* Texto mais escuro para contraste */
      border: 1px solid #ccc;
    }
  </style>
</head>
<body>

<div class="container">
  <h2>Registo de Abastecimento</h2>

  <?php if ($msg): ?>
    <div class="msg <?= strpos($msg, 'sucesso') !== false ? 'success' : '' ?>"><?= $msg ?></div>
  <?php endif; ?>

  <form method="POST" id="formAbastecimento">
    <label>Veículo</label>
    <select name="id_veiculo" id="veiculo" required>
      <option value="">Selecionar...</option>
      <?php foreach ($veiculos as $v): ?>
        <option value="<?= $v['id_veiculo'] ?>"><?= htmlspecialchars($v['matricula']) ?></option>
      <?php endforeach; ?>
    </select>

    <label>Último Registo:</label>
    <input type="text" id="ultimo_km_data" readonly background-color: #e6e6e6;
>

    <input type="hidden" id="km_anteriores" name="km_anteriores" value="0">

    <label>KM Atuais:</label>
    <input type="number" name="km_registados" id="km_registados" required>

    <input type="hidden" name="id_utilizador" value="<?= $_SESSION['id_utilizador'] ?>">
    <input type="hidden" name="empresa_id" value="<?= $empresa_id ?>">

    <label>Data e Hora do Abastecimento:</label>
    <input type="datetime-local" name="data_abastecimento" value="<?= date('Y-m-d\TH:i') ?>" required>

    <label>Posto</label>
    <select name="id_posto" required>
      <option value="">Selecionar...</option>
      <?php foreach ($postos as $p): ?>
        <option value="<?= $p['id_posto'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
      <?php endforeach; ?>
    </select>

    <label>Tipo de Combustível:</label>
    <select name="id_tipo_combustivel" required>
      <option value="">Selecionar...</option>
      <?php foreach ($tipos_combustivel as $t): ?>
        <option value="<?= $t['id'] ?>" <?= 
          (isset($_POST['id_tipo_combustivel']) && $_POST['id_tipo_combustivel'] == $t['id']) || 
          (!isset($_POST['id_tipo_combustivel']) && $t['id'] == $gasoleo_id) ? 'selected' : '' ?>>
          <?= htmlspecialchars($t['nome']) ?>
        </option>
      <?php endforeach; ?>
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
        <input type="number" step="0.01" id="valor_total" readonly>
      </div>
    </div>

    <label>Observações:</label>
    <textarea name="observacoes" rows="2"></textarea>

    <button type="submit">Guardar Abastecimento</button>
  </form>

  <a class="back-btn" href="verifica_registo.php">← Ver Abastecimentos</a>
  <a class="back-btn" href="../html/index.php">← Voltar ao Início</a>
</div>

<script>
  function calcularValorTotal() {
    const litros = parseFloat(document.getElementById('litros').value) || 0;
    const precoLitro = parseFloat(document.getElementById('preco_litro').value) || 0;
    const valorTotal = litros * precoLitro;
    document.getElementById('valor_total').value = valorTotal.toFixed(2);
  }

  document.getElementById('litros').addEventListener('input', calcularValorTotal);
  document.getElementById('preco_litro').addEventListener('input', calcularValorTotal);

  const veiculoSelect = document.getElementById('veiculo');
  const dataInput = document.querySelector('input[name="data_abastecimento"]');

  function atualizarUltimoKm() {
    const idVeiculo = veiculoSelect.value;
    const data = dataInput.value; 

    if (!idVeiculo) {
      document.getElementById('ultimo_km_data').value = "Nenhum registo anterior";
      document.getElementById('km_anteriores').value = 0;
      return;
    }

    const dataFormatada = data ? data.replace('T', ' ') + ':00' : '';

    let url = `<?= basename(__FILE__) ?>?acao=obter_km&id_veiculo=${idVeiculo}`;
    if (dataFormatada) {
      url += `&data=${encodeURIComponent(dataFormatada)}`;
    }

    fetch(url)
      .then(res => res.json())
      .then(data => {
        const km = parseInt(data.km) || 0;
        const dt = data.data || "Nenhum registo anterior";

        document.getElementById('ultimo_km_data').value = `Último KM: ${km} | Data: ${dt}`;
        document.getElementById('km_anteriores').value = km;
      });
  }

  veiculoSelect.addEventListener('change', atualizarUltimoKm);
  dataInput.addEventListener('change', atualizarUltimoKm);

  if (dataInput.value) atualizarUltimoKm();

  const capacidades = <?= json_encode($capacidades) ?>;

  document.getElementById('formAbastecimento').addEventListener('submit', function(e) {
    const idVeiculo = veiculoSelect.value;
    const litros = parseFloat(document.getElementById('litros').value);
    const capacidade = capacidades[idVeiculo] || 0;

    const kmAtual = parseInt(document.getElementById('km_registados').value);
    const kmAnteriores = parseInt(document.getElementById('km_anteriores').value);

    if (litros <= 0) {
      e.preventDefault();
      alert("Erro: Os litros têm de ser maiores que 0.");
      return false;
    }

    if (precoLitro <= 0) {
      e.preventDefault();
      alert("Erro: O preço por litro tem de ser maior que 0.");
      return false;
    }

    if (litros > capacidade) {
      e.preventDefault();
      alert(`Erro: O tanque do veículo suporta no máximo ${capacidade} litros.`);
      return false;
    }

    if (isNaN(kmAtual) || kmAtual <= kmAnteriores) {
      e.preventDefault();
      alert(`Erro: Os KM registados têm de ser superiores ao último valor registado para esta data (${kmAnteriores} KM).`);
      return false;
    }
  });
</script>

</body>
</html>
