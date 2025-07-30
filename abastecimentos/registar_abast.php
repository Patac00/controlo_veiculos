<?php 
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}
include("../php/config.php");

$msg = "";

$id_utilizador = $_SESSION['id_utilizador'];
$res_empresa = $con->query("SELECT empresa_id FROM utilizadores WHERE id_utilizador = $id_utilizador LIMIT 1");
$empresa_id = null;
if ($res_empresa && $row_empresa = $res_empresa->fetch_assoc()) {
    $empresa_id = $row_empresa['empresa_id'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_veiculo = intval($_POST['id_veiculo']);
    $id_utilizador = intval($_POST['id_utilizador']);
    $empresa_id = intval($_POST['empresa_id']);
    $data_abastecimento = date('Y-m-d H:i:s'); // Agora mesmo, ignora input
    $km_registados = intval($_POST['km_registados']);
    $km_anteriores = intval($_POST['km_anteriores']);
    $id_posto = intval($_POST['id_posto']);
    $litros = floatval(str_replace(',', '.', $_POST['litros']));
    $preco_litro = str_replace(',', '.', $_POST['preco_litro']);
    $preco_litro = number_format((float)$preco_litro, 3, '.', '');
    $id_tipo_combustivel = intval($_POST['id_tipo_combustivel']);
    $observacoes = isset($_POST['observacoes']) ? trim($_POST['observacoes']) : '';

    // 4. Campos obrigatórios
    if (
        empty($id_veiculo) || empty($id_utilizador) || empty($empresa_id) ||
        empty($km_registados) || empty($id_posto) || empty($litros) || empty($id_tipo_combustivel) || empty($preco_litro)
    ) {
        $msg = "Por favor preencha todos os campos obrigatórios.";
    } 
    // 2. Litros plausíveis (ex: 0 < litros <= 1000)
    elseif ($litros <= 0 || $litros > 1000) {
        $msg = "Erro: quantidade de litros inválida.";
    }
    // 1. Data não futura (usa data atual, por isso está ok)
    // (não precisa validar pois já usas now)
    // 7. Evitar registos duplicados - verifica se já existe registo com veículo, data (hoje) e litros iguais
    elseif ($con->query("SELECT 1 FROM abastecimentos WHERE id_veiculo = $id_veiculo AND DATE(data_abastecimento) = CURDATE() AND litros = $litros LIMIT 1")->num_rows > 0) {
        $msg = "Erro: já existe um abastecimento igual para este veículo hoje.";
    }
    // 5. Consistência tipo de combustível - supõe que tens tabela veiculos com campo id_tipo_combustivel
    elseif ($res_tipo = $con->query("SELECT id_tipo_combustivel FROM veiculos WHERE id_veiculo = $id_veiculo LIMIT 1")) {
        $tipo_veiculo = $res_tipo->fetch_assoc()['id_tipo_combustivel'] ?? null;
        if ($tipo_veiculo && $tipo_veiculo != $id_tipo_combustivel) {
            $msg = "Erro: o tipo de combustível não é compatível com este veículo.";
        }
    }
    // 6. Observações limitadas (ex: max 255 chars)
    elseif (strlen($observacoes) > 255) {
        $msg = "Erro: observações demasiado longas (máx 255 caracteres).";
    }
    // 8. Validar se posto pertence à empresa
    elseif ($con->query("SELECT 1 FROM lista_postos WHERE id_posto = $id_posto AND empresa_id = $empresa_id LIMIT 1")->num_rows === 0) {
        $msg = "Erro: o posto selecionado não pertence à tua empresa.";
    }
    // 10. Verificar formatos de data/hora - já é garantido com date('Y-m-d H:i:s')
    // km registados superiores ao anterior
    elseif ($km_registados <= $km_anteriores) {
        $msg = "Erro: Os KM registados têm de ser superiores ao último valor registado para esta data ($km_anteriores KM).";
    }
    elseif ($preco_litro <= 0) {
        $msg = "Erro: preço por litro inválido.";
    }
    else {
        $valor_total = round($litros * $preco_litro, 2);
        if (abs(($litros * $preco_litro) - $valor_total) > 0.02) {
            $msg = "Erro: o valor total não corresponde ao cálculo correto.";
        } else {
            $stmt = $con->prepare("INSERT INTO abastecimentos 
                (id_veiculo, id_utilizador, empresa_id, data_abastecimento, km_registados, id_posto, litros, id_tipo_combustivel, observacoes, preco_litro, valor_total) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt === false) {
                $msg = "Erro na preparação da query: " . $con->error;
            } else {
                $stmt->bind_param("iiisiddidsd", 
                    $id_veiculo, 
                    $id_utilizador,
                    $empresa_id,
                    $data_abastecimento, 
                    $km_registados, 
                    $id_posto, 
                    $litros, 
                    $id_tipo_combustivel, 
                    $observacoes, 
                    $preco_litro,
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
}


if (isset($_GET['acao']) && $_GET['acao'] === 'obter_km' && isset($_GET['id_veiculo'])) {
    $id_veiculo = intval($_GET['id_veiculo']);
    $data = isset($_GET['data']) ? $_GET['data'] : null;

    $sql = "SELECT km_registados, data_abastecimento 
            FROM abastecimentos 
            WHERE id_veiculo = $id_veiculo";

    if ($data) {
        $sql .= " AND data_abastecimento <= '$data'";
    }

    $sql .= " ORDER BY data_abastecimento DESC LIMIT 1";

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
  </style>
</head>
<body>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Registo de Abastecimento</title>
<style>
  /* Reset simples */
  * {
    box-sizing: border-box;
  }
  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #e8f0ea;
    margin: 0; padding: 0;
    color: #333;
  }
  .container {
    max-width: 650px;
    margin: 60px auto 40px;
    background: #fff;
    border-radius: 12px;
    padding: 30px 40px;
    box-shadow: 0 8px 20px rgb(0 0 0 / 0.1);
  }
  h2 {
    color: #007a3d;
    text-align: center;
    margin-bottom: 25px;
    font-weight: 700;
  }
  form label {
    display: block;
    margin-top: 18px;
    font-weight: 600;
    font-size: 0.95rem;
  }
  input[type="text"],
  input[type="number"],
  input[type="datetime-local"],
  select,
  textarea {
    width: 100%;
    padding: 12px 14px;
    margin-top: 6px;
    border: 1.8px solid #ccc;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s ease;
    font-family: inherit;
  }
  input[type="text"]:focus,
  input[type="number"]:focus,
  input[type="datetime-local"]:focus,
  select:focus,
  textarea:focus {
    border-color: #007a3d;
    outline: none;
  }
  textarea {
    resize: vertical;
    min-height: 60px;
  }

  /* Grid para inputs lado a lado */
  .grid-3 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 18px 15px;
    margin-top: 10px;
  }
  .grid-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px 25px;
    margin-top: 10px;
  }

  button[type="submit"] {
    background-color: #007a3d;
    border: none;
    color: white;
    font-weight: 700;
    padding: 14px 0;
    border-radius: 10px;
    font-size: 1.1rem;
    cursor: pointer;
    margin-top: 28px;
    width: 100%;
    box-shadow: 0 4px 10px rgb(0 122 61 / 0.3);
    transition: background-color 0.3s ease;
  }
  button[type="submit"]:hover {
    background-color: #005d2a;
  }

  .msg {
    text-align: center;
    font-weight: 600;
    margin-bottom: 15px;
    padding: 10px;
    border-radius: 8px;
  }
  .msg.success {
    color: #155724;
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
  }
  .msg.error {
    color: #721c24;
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
  }

  .btns {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
    gap: 10px;
    flex-wrap: wrap;
  }
  .back-btn {
    flex: 1 1 48%;
    text-align: center;
    background-color: #eee;
    color: #007a3d;
    padding: 12px 10px;
    border-radius: 10px;
    text-decoration: none;
    font-weight: 600;
    border: 1.5px solid #007a3d;
    transition: background-color 0.3s ease, color 0.3s ease;
  }
  .back-btn:hover {
    background-color: #007a3d;
    color: white;
  }

  /* Responsivo */
  @media (max-width: 480px) {
    .grid-3, .grid-2 {
      grid-template-columns: 1fr;
    }
    .btns {
      flex-direction: column;
    }
    .back-btn {
      flex: none;
      width: 100%;
    }
  }
</style>
</head>
<body>
<div class="container">
  <h2>Registo de Abastecimento</h2>

  <?php if ($msg): ?>
  <div class="msg <?= strpos($msg, 'sucesso') !== false ? 'success' : 'error' ?>">
    <?= htmlspecialchars($msg) ?>
  </div>
  <?php endif; ?>

  <form method="POST" id="formAbastecimento" novalidate>
    <label for="tipo_registo">Tipo de Registo</label>
    <select name="tipo_registo" id="tipo_registo" required>
      <option value="km">Km</option>
      <option value="horas">Horas</option>
    </select>

    <label for="id_veiculo">Veículo</label>
    <select name="id_veiculo" id="veiculo" required>
      <option value="">Selecionar...</option>
      <?php foreach ($veiculos as $v): ?>
        <option value="<?= $v['id_veiculo'] ?>"><?= htmlspecialchars($v['matricula']) ?></option>
      <?php endforeach; ?>
    </select>

    <input type="hidden" id="km_anteriores" name="km_anteriores" value="0">
    <input type="hidden" name="empresa_id" value="<?= htmlspecialchars($empresa_id) ?>">

    <label for="km_registados">KM / Horas Atuais</label>
    <input type="number" name="km_registados" id="km_registados" required min="0" step="1" />

    <label for="data_abastecimento">Data e Hora do Abastecimento</label>
    <input type="datetime-local" name="data_abastecimento" id="data_abastecimento" value="<?= date('Y-m-d\TH:i') ?>" required>

    <div class="grid-2">
      <div>
        <label for="id_posto">Posto</label>
        <select name="id_posto" id="id_posto" required>
          <option value="">Selecionar...</option>
          <?php foreach ($postos as $p): ?>
            <option value="<?= $p['id_posto'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label for="id_tipo_combustivel">Combustível</label>
        <select name="id_tipo_combustivel" id="id_tipo_combustivel" required>
          <option value="">Selecionar...</option>
          <?php foreach ($tipos_combustivel as $t): ?>
            <option value="<?= $t['id'] ?>" <?= 
              (isset($_POST['id_tipo_combustivel']) && $_POST['id_tipo_combustivel'] == $t['id']) || 
              (!isset($_POST['id_tipo_combustivel']) && $t['id'] == $gasoleo_id) ? 'selected' : '' ?>>
              <?= htmlspecialchars($t['nome']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid-3">
      <div>
        <label for="litros">Litros</label>
        <input type="number" step="0.01" name="litros" id="litros" required min="0.01" />
      </div>
      <div>
        <label for="preco_litro">Preço/Litro (€)</label>
        <input type="number" step="0.0001" name="preco_litro" id="preco_litro" required min="0.0001" />
      </div>
      <div>
        <label for="valor_total">Total (€)</label>
        <input type="number" step="0.01" id="valor_total" readonly />
      </div>
    </div>

    <label for="observacoes">Observações</label>
    <textarea name="observacoes" id="observacoes" rows="3" maxlength="255" placeholder="Comentários (máx 255 caracteres)"></textarea>

    <input type="hidden" name="id_utilizador" value="<?= $_SESSION['id_utilizador'] ?>">

    <div class="btns">
      <a class="back-btn" href="verifica_registo.php">&larr; Ver Registos</a>
      <button type="submit">Guardar</button>
      <a class="back-btn" href="../html/index.php">Voltar Início</a>
    </div>
  </form>
</div>

<script>
  function calcularValorTotal() {
    const litros = parseFloat(document.getElementById('litros').value.replace(',', '.')) || 0;
    const precoLitro = parseFloat(document.getElementById('preco_litro').value.replace(',', '.')) || 0;
    const total = litros * precoLitro;
    document.getElementById('valor_total').value = total.toFixed(2);
  }
  document.getElementById('litros').addEventListener('input', calcularValorTotal);
  document.getElementById('preco_litro').addEventListener('input', calcularValorTotal);
</script>
</body>
</html>
