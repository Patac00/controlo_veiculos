<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");

$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST['data'] ?? null;
    $tipo_combustivel = $_POST['tipo_combustivel'] ?? null;
    $preco_litro = $_POST['preco_litro'] ?? null;
    $localizacao = $_POST['localizacao'] ?? null;
    $fatura = $_POST['fatura'] ?? null;
    $litros = floatval($_POST['litros']);

    if ($data && $tipo_combustivel && $litros && $preco_litro && $localizacao && $fatura) {
        if ($litros > 10000) {
            $mensagem = "Erro: não podes inserir mais que 10000 litros.";
            $tipo_mensagem = "danger";
        } else {
            $stmt = $con->prepare("INSERT INTO fornecimentos_bomba (data, tipo_combustivel, litros, localizacao, preco_litro, fatura) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssddss", $data, $tipo_combustivel, $litros, $localizacao, $preco_litro, $fatura);

            if ($stmt->execute()) {
                $mensagem = "✅ Fornecimento registado com sucesso!";
                $tipo_mensagem = "success";
            } else {
                $mensagem = "Erro ao registar fornecimento: " . $stmt->error;
                $tipo_mensagem = "danger";
            }

            $stmt->close();
        }
    } else {
        $mensagem = "Por favor, preenche todos os campos.";
        $tipo_mensagem = "warning";
    }

    $con->close();
}
?>

<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Fornecimento à Bomba</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
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
            <?= $mensagem ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
          </div>
        <?php endif; ?>

        <form action="" method="post">
          <div class="mb-3">
            <label for="data" class="form-label">Data</label>
            <input type="date" name="data" class="form-control" required>
          </div>

          <div class="mb-3">
            <label for="tipo_combustivel" class="form-label">Tipo de Combustível</label>
            <select name="tipo_combustivel" class="form-select" required>
              <option value="Diesel">Diesel</option>
            </select>
          </div>

          <div class="mb-3">
            <label for="litros" class="form-label">Litros</label>
            <input type="number" step="0.01" name="litros" max="10000" class="form-control" required>
          </div>

          <div class="mb-3">
            <label for="preco_litro" class="form-label">Preço por Litro (€)</label>
            <input type="number" step="0.01" name="preco_litro" class="form-control" required>
          </div>

          <div class="mb-3">
            <label for="localizacao" class="form-label">Localização</label>
            <select name="localizacao" class="form-select" required>
              <option value="CIVTRIHI">CIVTRIHI</option>
              <option value="Dep móvel">Dep móvel</option>
              <option value="Redinha">Redinha</option>
              <option value="Ribtejo">Ribtejo</option>
              <option value="Venda Cruz">Venda Cruz</option>
            </select>
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
            <button type="button" class="btn btn-secondary" onclick="window.location.href='../html/index.php'"><i class="bi bi-arrow-left"></i> Voltar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
