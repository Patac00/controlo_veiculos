<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");

$mensagem = '';
$tipo_mensagem = '';

$id = $_SESSION['id_utilizador'];
$tipos = [];
$postos = [];
$unidade_str = null;
$empresa_nome = '';
$unidade_nome = '';
$unidade_selecionada = null;

// Buscar empresa e unidades do utilizador
$sql_user = "SELECT u.empresa_id, e.unidades, e.nome AS empresa_nome FROM utilizadores u
             LEFT JOIN empresas e ON u.empresa_id = e.id_empresa
             WHERE u.id_utilizador = ? LIMIT 1";
$stmt_user = $con->prepare($sql_user);
$stmt_user->bind_param("i", $id);
$stmt_user->execute();
$res_user = $stmt_user->get_result();
if ($res_user && mysqli_num_rows($res_user) > 0) {
    $user_data = $res_user->fetch_assoc();
    $empresa_id = (int)$user_data['empresa_id'];
    $unidade_str = $user_data['unidades'];
    $empresa_nome = $user_data['empresa_nome'];
}
$stmt_user->close();

// Buscar os tipos de combustível
$res = mysqli_query($con, "SELECT DISTINCT nome FROM tipo_combustivel");
while ($row = mysqli_fetch_assoc($res)) {
    $tipos[] = $row['nome'];
}

// Buscar postos associados à empresa
$postos = [];
if ($empresa_id) {
    $stmt = $con->prepare("SELECT unidades FROM empresas WHERE id_empresa = ?");
    $stmt->bind_param("i", $empresa_id);
    $stmt->execute();
    $stmt->bind_result($unidades_str);
    $stmt->fetch();
    $stmt->close();

    if ($unidades_str) {
        $postos = array_map('trim', explode(',', $unidades_str));
    }
}

// Verificar se veio o posto no GET (do botão)
$posto_selecionado = $_GET['posto'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST['data'] ?? null;
    $tipo_combustivel = $_POST['tipo_combustivel'] ?? null;
    $preco_litro = $_POST['preco_litro'] ?? null;
    $localizacao = $_POST['localizacao'] ?? null;
    $fatura = $_POST['fatura'] ?? null;
    $litros = floatval($_POST['litros']);
    $unidade_escolhida = null;

    // Buscar unidade correspondente à localização escolhida
    if ($localizacao) {
        $sql_unidade = "SELECT unidade FROM lista_postos WHERE nome = ? LIMIT 1";
        $stmt_unidade = $con->prepare($sql_unidade);
        $stmt_unidade->bind_param("s", $localizacao);
        $stmt_unidade->execute();
        $res_unidade = $stmt_unidade->get_result();
        if ($res_unidade && $row_unidade = $res_unidade->fetch_assoc()) {
            $unidade_escolhida = $row_unidade['unidade'];  // É string ou int, conforme a coluna
        }
        $stmt_unidade->close();
    }

    if ($data && $tipo_combustivel && $litros > 0 && $preco_litro && $localizacao && $fatura && $empresa_id && $unidade_escolhida !== null) {
        if ($litros > 10000) {
            $mensagem = "Erro: não podes inserir mais que 10000 litros de uma vez.";
            $tipo_mensagem = "danger";
        } else {
            $sql = "SELECT COALESCE(SUM(litros), 0) AS total FROM fornecimentos_bomba WHERE tipo_combustivel = ? AND localizacao = ? AND data = ?";
            $stmt_check = $con->prepare($sql);
            $stmt_check->bind_param("sss", $tipo_combustivel, $localizacao, $data);
            $stmt_check->execute();
            $result = $stmt_check->get_result();
            $row = $result->fetch_assoc();
            $total_atual = $row['total'] ?? 0;

            $novo_total = $total_atual + $litros;

            if ($novo_total > 10000) {
                $mensagem = "Erro: Limite de 10.000 litros ultrapassado para este tipo de combustível, localização e data.";
                $tipo_mensagem = "danger";
            } else {
                $stmt = $con->prepare("INSERT INTO fornecimentos_bomba (data, tipo_combustivel, litros, localizacao, preco_litro, fatura, empresa_id, unidade) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdssdsi", $data, $tipo_combustivel, $litros, $localizacao, $preco_litro, $fatura, $empresa_id, $unidade_escolhida);

                if ($stmt->execute()) {
                    $mensagem = "✅ Fornecimento registado com sucesso!";
                    $tipo_mensagem = "success";
                } else {
                    $mensagem = "Erro ao registar fornecimento: " . $stmt->error;
                    $tipo_mensagem = "danger";
                }

                $stmt->close();
            }

            $stmt_check->close();
        }
    } else {
        $mensagem = "Por favor, preenche todos os campos corretamente.";
        $tipo_mensagem = "warning";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Fornecimento à Bomba</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
</head>
<body class="bg-light">
  <div class="container mt-5">
    <div class="card shadow">
      <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h4 class="mb-0"><i class="bi bi-fuel-pump"></i> Registar Fornecimento</h4>
      </div>
      <div class="card-body">

        <?php if (!empty($mensagem)): ?>
          <div class="alert alert-<?= $tipo_mensagem ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensagem) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
          </div>
        <?php endif; ?>

        <!-- Mostrar a empresa -->
        <div class="mb-3">
          <label class="form-label">Empresa</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($empresa_nome) ?>" readonly>
          <input type="hidden" name="empresa_id" value="<?= htmlspecialchars($empresa_id) ?>">
        </div>

        <!-- Mostrar a unidade -->
        <div class="mb-3">
          <label class="form-label">Unidade</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($unidade_str) ?>" readonly>
        </div>

        <form action="" method="post">
          <div class="mb-3">
            <label for="data" class="form-label">Data</label>
            <input type="date" name="data" class="form-control" required />
          </div>

          <div class="mb-3">
            <label for="tipo_combustivel" class="form-label">Tipo de Combustível</label>
            <select name="tipo_combustivel" class="form-select" required>
              <option value="">Selecionar...</option>
              <?php foreach ($tipos as $tipo): ?>
                <option value="<?= htmlspecialchars($tipo) ?>"><?= htmlspecialchars($tipo) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label for="litros" class="form-label">Litros</label>
            <input type="number" step="0.01" name="litros" max="10000" class="form-control" required />
          </div>

          <div class="mb-3">
            <label for="preco_litro" class="form-label">Preço por Litro (€)</label>
            <input type="number" step="0.01" name="preco_litro" class="form-control" required />
          </div>

          <div class="mb-3">
            <label for="localizacao" class="form-label">Localização</label>
            <?php if ($posto_selecionado): ?>
              <input type="hidden" name="localizacao" value="<?= htmlspecialchars($posto_selecionado) ?>">
              <input type="text" class="form-control" value="<?= htmlspecialchars($posto_selecionado) ?>" readonly>
            <?php else: ?>
              <select name="localizacao" class="form-select" required>
                <option value="">Selecionar...</option>
                <?php foreach ($postos as $posto): ?>
                  <option value="<?= htmlspecialchars($posto) ?>" <?= $posto === $posto_selecionado ? 'selected' : '' ?>>
                    <?= htmlspecialchars($posto) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </div>


          <div class="mb-4">
            <label for="fatura" class="form-label">Fatura?</label>
            <select name="fatura" class="form-select" required>
              <option value="Sim">Sim</option>
              <option value="Não">Não</option>
            </select>
          </div>

          <div class="d-flex justify-content-between">
            <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> Registar</button>
            <a href="../html/index.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
          </div>
        </form>

      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
