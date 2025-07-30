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
             LEFT JOIN empresas e ON u.empresa_id = e.empresa_id
             WHERE u.id_utilizador = ? LIMIT 1";

$stmt_user = $con->prepare($sql_user);
$stmt_user->bind_param("i", $id);
$stmt_user->execute();
$res_user = $stmt_user->get_result();
if ($res_user && mysqli_num_rows($res_user) > 0) {
    $user_data = $res_user->fetch_assoc();
    $empresa_id_sessao = (int)$user_data['empresa_id'];
    $unidade_str = $user_data['unidades'];
    $empresa_nome = $user_data['empresa_nome'];
}
$stmt_user->close();

// Buscar os tipos de combustível
$res = mysqli_query($con, "SELECT DISTINCT nome FROM tipo_combustivel");
while ($row = mysqli_fetch_assoc($res)) {
    $tipos[] = $row['nome'];
}
$tipo_combustivel_default = $tipos[0] ?? '';

// Buscar postos associados à empresa
$postos = [];
if ($empresa_id_sessao) {
    $stmt = $con->prepare("SELECT unidades FROM empresas WHERE empresa_id = ?");
    $stmt->bind_param("i", $empresa_id_sessao);
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
    $id_posto = $_POST['id_posto'] ?? null;
    $fatura = $_POST['fatura'] ?? null;
    $litros = $_POST['litros'] ?? null;
    $id_empresa = $_POST['empresa_id'] ?? null;
    $unidade_escolhida = null;

    $missing = [];

    // Validar data - formato e validade
    if (!$data) {
        $missing[] = 'Data';
    } else {
        $d = DateTime::createFromFormat('Y-m-d', $data);
        $valid_date = $d && $d->format('Y-m-d') === $data;
        if (!$valid_date) {
            $mensagem = "Data inválida. Usa o formato YYYY-MM-DD.";
            $tipo_mensagem = "danger";
            $data = null; // invalida para evitar continuar
        }
    }

    // Validar tipo_combustivel (deve estar na lista)
    if (!$tipo_combustivel || !in_array($tipo_combustivel, $tipos, true)) {
        $missing[] = 'Tipo de Combustível válido';
    }

    // Validar preco_litro
    if (!$preco_litro || !is_numeric($preco_litro) || floatval($preco_litro) <= 0) {
        $missing[] = 'Preço por Litro válido e positivo';
    } else {
        $preco_litro = floatval($preco_litro);
    }

    // Validar id_posto (inteiro e pertence à empresa)
    if (!$id_posto || !is_numeric($id_posto)) {
        $missing[] = 'Posto válido';
        $id_posto = null;
    } else {
        $id_posto = (int)$id_posto;

        // Confirmar que posto pertence à empresa_id_sessao
        $stmt_check_posto = $con->prepare("SELECT COUNT(*) FROM lista_postos WHERE id_posto = ? AND empresa_id = ?");
        $stmt_check_posto->bind_param("ii", $id_posto, $empresa_id_sessao);
        $stmt_check_posto->execute();
        $stmt_check_posto->bind_result($count_posto);
        $stmt_check_posto->fetch();
        $stmt_check_posto->close();

        if ($count_posto === 0) {
            $mensagem = "Posto não pertence à empresa do utilizador.";
            $tipo_mensagem = "danger";
            $id_posto = null;
        }
    }

    // Validar fatura (não vazio e sanitizar)
    if (!$fatura || trim($fatura) === '') {
        $missing[] = 'Fatura';
    } else {
        $fatura = trim($fatura);
        // Exemplo simples: só aceitar letras, números, - e _
        if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $fatura)) {
            $mensagem = "Fatura contém caracteres inválidos.";
            $tipo_mensagem = "danger";
            $fatura = null;
        }
    }

    // Validar litros
    if (!$litros || !is_numeric($litros) || floatval($litros) <= 0) {
        $missing[] = 'Litros (maior que 0)';
    } else {
        $litros = floatval($litros);
        if ($litros > 10000) {
            $mensagem = "Erro: não podes inserir mais que 10.000 litros de uma vez.";
            $tipo_mensagem = "danger";
            $litros = null;
        }
    }

    // Validar empresa_id (se existe e válido)
    if (!$id_empresa) {
        $missing[] = 'Empresa';
        $empresa_id = null;
    } else {
        if (is_numeric($id_empresa)) {
            $empresa_id = (int)$id_empresa;
        } else {
            // Busca empresa pelo nome
            $sql_empresa = "SELECT empresa_id FROM empresas WHERE nome = ? LIMIT 1";
            $stmt_empresa = $con->prepare($sql_empresa);
            $stmt_empresa->bind_param("s", $id_empresa);
            $stmt_empresa->execute();
            $res_empresa = $stmt_empresa->get_result();
            if ($res_empresa && $row_emp = $res_empresa->fetch_assoc()) {
                $empresa_id = (int)$row_emp['empresa_id'];
            } else {
                $empresa_id = null;
            }
            $stmt_empresa->close();
        }

        // Confirmar empresa_id existe na BD
        if ($empresa_id) {
            $stmt_check_empresa = $con->prepare("SELECT COUNT(*) FROM empresas WHERE empresa_id = ?");
            $stmt_check_empresa->bind_param("i", $empresa_id);
            $stmt_check_empresa->execute();
            $stmt_check_empresa->bind_result($count_empresa);
            $stmt_check_empresa->fetch();
            $stmt_check_empresa->close();

            if ($count_empresa === 0) {
                $mensagem = "Empresa inválida.";
                $tipo_mensagem = "danger";
                $empresa_id = null;
            }
        } else {
            $missing[] = 'Empresa válida';
        }
    }

    // Buscar unidade correspondente ao id_posto escolhido
    if ($id_posto) {
        $sql_unidade = "SELECT unidade FROM lista_postos WHERE id_posto = ? LIMIT 1";
        $stmt_unidade = $con->prepare($sql_unidade);
        $stmt_unidade->bind_param("i", $id_posto);
        $stmt_unidade->execute();
        $res_unidade = $stmt_unidade->get_result();
        if ($res_unidade && $row_unidade = $res_unidade->fetch_assoc()) {
            $unidade_escolhida = $row_unidade['unidade'];
        }
        $stmt_unidade->close();

        if ($unidade_escolhida === null) {
            $missing[] = 'Unidade';
        }
    } else {
        $missing[] = 'Posto válido para obter Unidade';
    }

    // Se houver campos em falta, mostra aviso e não avança
    if (count($missing) > 0) {
        $mensagem = "Por favor, preenche corretamente os seguintes campos: " . implode(', ', $missing) . ".";
        $tipo_mensagem = "warning";
    } else {
        // Verificar limite total diário para este posto e combustível
        $sql = "SELECT COALESCE(SUM(litros), 0) AS total FROM fornecimentos_bomba WHERE tipo_combustivel = ? AND id_posto = ? AND data = ?";
        $stmt_check = $con->prepare($sql);
        $stmt_check->bind_param("sis", $tipo_combustivel, $id_posto, $data);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        $row = $result->fetch_assoc();
        $total_atual = $row['total'] ?? 0;
        $stmt_check->close();

        $novo_total = $total_atual + $litros;

        if ($novo_total > 10000) {
            $mensagem = "Erro: Limite de 10.000 litros ultrapassado para este tipo de combustível, posto e data.<br>" .
                        "Já existem $total_atual litros registados, estás a tentar adicionar $litros litros.";
            $tipo_mensagem = "danger";
        } else {
            // Inserir no BD
            $stmt = $con->prepare("INSERT INTO fornecimentos_bomba (data, tipo_combustivel, litros, id_posto, preco_litro, fatura, empresa_id, unidade) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdidsis", $data, $tipo_combustivel, $litros, $id_posto, $preco_litro, $fatura, $empresa_id, $unidade_escolhida);

            if ($stmt->execute()) {
                $mensagem = "✅ Fornecimento registado com sucesso!";
                $tipo_mensagem = "success";
            } else {
                $mensagem = "Erro ao registar fornecimento: " . $stmt->error;
                $tipo_mensagem = "danger";
            }

            $stmt->close();
        }
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
            <?= $tipo_mensagem === 'danger' ? $mensagem : htmlspecialchars($mensagem) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
          </div>
        <?php endif; ?>

        <!-- Mostrar a empresa -->
        <div class="mb-3">
          <label class="form-label">Empresa</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($empresa_nome) ?>" readonly>
        </div>

        <form action="" method="post">
          <!-- Aqui o hidden para enviar o empresa_id -->
          <input type="hidden" name="empresa_id" value="<?= htmlspecialchars($empresa_id_sessao) ?>">

          <!-- Mostrar a unidade -->
          <div class="mb-3">
            <label class="form-label">Unidade</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($unidade_str) ?>" readonly>
          </div>

          <div class="mb-3">
            <label for="data" class="form-label">Data</label>
            <input type="date" name="data" class="form-control" value="<?= date('Y-m-d') ?>" required />
          </div>

          <div class="mb-3">
            <label for="tipo_combustivel" class="form-label">Tipo de Combustível</label>
            <select name="tipo_combustivel" class="form-select" required>
            <option value="">Selecionar...</option>
            <?php foreach ($tipos as $tipo): ?>
              <option value="<?= htmlspecialchars($tipo) ?>" <?= $tipo === $tipo_combustivel_default ? 'selected' : '' ?>>
                <?= htmlspecialchars($tipo) ?>
              </option>
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
            <label for="id_posto" class="form-label">Posto</label>
            <?php if ($posto_selecionado):
              $stmt_post = $con->prepare("SELECT id_posto FROM lista_postos WHERE nome = ? LIMIT 1");
              $stmt_post->bind_param("s", $posto_selecionado);
              $stmt_post->execute();
              $res_post = $stmt_post->get_result();
              $id_posto_val = ($res_post && $row_post = $res_post->fetch_assoc()) ? $row_post['id_posto'] : '';
              $stmt_post->close();
            ?>
              <input type="hidden" name="id_posto" value="<?= htmlspecialchars($id_posto_val) ?>">
              <input type="text" class="form-control" value="<?= htmlspecialchars($posto_selecionado) ?>" readonly>
            <?php else: ?>
              <select name="id_posto" class="form-select" required>
                <option value="">Selecionar...</option>
                <?php
                $escaped_postos = array_map([$con, 'real_escape_string'], $postos);
                $sql_postos = "SELECT id_posto, nome FROM lista_postos WHERE nome IN ('" . implode("','", $escaped_postos) . "')";
                $res_postos = $con->query($sql_postos);
                if ($res_postos) {
                  while ($row_posto = $res_postos->fetch_assoc()) {
                    $selected = ($posto_selecionado == $row_posto['nome']) ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars($row_posto['id_posto']) . '" ' . $selected . '>' . htmlspecialchars($row_posto['nome']) . '</option>';
                  }
                }
                ?>
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

  <script>
    document.querySelector('form').addEventListener('submit', function(e) {
      const data = this.data.value;
      const tipo = this.tipo_combustivel.value;
      const preco = parseFloat(this.preco_litro.value);
      const litros = parseFloat(this.litros.value);
      const idPosto = this.id_posto.value;
      const fatura = this.fatura.value;

      let erros = [];

      // Validar data no formato yyyy-mm-dd
      if (!/^\d{4}-\d{2}-\d{2}$/.test(data)) {
        erros.push("Data inválida. Usa o formato YYYY-MM-DD.");
      }

      // Tipo combustível
      if (!tipo) {
        erros.push("Seleciona um tipo de combustível.");
      }

      // Preço por litro
      if (isNaN(preco) || preco <= 0) {
        erros.push("Preço por litro deve ser um número positivo.");
      }

      // Litros
      if (isNaN(litros) || litros <= 0 || litros > 10000) {
        erros.push("Litros devem ser entre 0 e 10.000.");
      }

      // Posto
      if (!idPosto) {
        erros.push("Seleciona um posto.");
      }

      // Fatura
      if (!fatura) {
        erros.push("Seleciona se há fatura.");
      }

      if (erros.length > 0) {
        e.preventDefault();
        alert("Erros:\n" + erros.join("\n"));
      }
    });
  </script>

</body>
</html>
