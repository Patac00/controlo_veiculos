<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID inválido.");
}

$id = (int)$_GET['id'];
$erro = '';
$sucesso = '';

// Buscar dados atuais do posto
$sql = "SELECT * FROM lista_postos WHERE id_posto = $id";
$res = $con->query($sql);

if ($res->num_rows === 0) {
    die("Posto não encontrado.");
}

$posto = $res->fetch_assoc();

// Buscar lista de empresas para dropdown
$empresas_res = $con->query("SELECT empresa_id, nome FROM empresas ORDER BY nome");

// Atualizar dados
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $con->real_escape_string(trim($_POST['nome'] ?? ''));
    $local = $con->real_escape_string(trim($_POST['local'] ?? ''));
    $contacto = $con->real_escape_string(trim($_POST['contacto'] ?? ''));
    $unidade = $con->real_escape_string(trim($_POST['unidade'] ?? ''));
    $empresa_id = isset($_POST['empresa_id']) && is_numeric($_POST['empresa_id']) ? (int)$_POST['empresa_id'] : null;
    $capacidade = isset($_POST['capacidade']) ? (int)$_POST['capacidade'] : null;

    if ($nome === '' || $local === '' || $contacto === '') {
        $erro = "Por favor preencha todos os campos obrigatórios.";
    } else {
        $sql_update = "UPDATE lista_postos SET 
            nome = '$nome',
            local = '$local',
            contacto = '$contacto',
            unidade = '$unidade',
            empresa_id = " . ($empresa_id ?? "NULL") . ",
            capacidade = " . ($capacidade ?? "NULL") . "
            WHERE id_posto = $id";

        if ($con->query($sql_update)) {
            $sucesso = "Posto atualizado com sucesso!";
            // Atualiza o $posto para mostrar no formulário
            $posto = [
                'nome' => $nome,
                'local' => $local,
                'contacto' => $contacto,
                'unidade' => $unidade,
                'empresa_id' => $empresa_id,
                'capacidade' => $capacidade,
            ];
        } else {
            $erro = "Erro ao atualizar: " . $con->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <title>Editar Posto</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      max-width: 600px;
      margin: 30px auto;
      background: #f9f9f9;
      padding: 20px;
      border-radius: 8px;
    }
    h2 {
      color: #00693e;
      text-align: center;
    }
    form {
      margin-top: 20px;
    }
    label {
      display: block;
      margin: 12px 0 4px;
    }
    input[type="text"], select, input[type="number"] {
      width: 100%;
      padding: 8px;
      border: 1px solid #ccc;
      border-radius: 6px;
      box-sizing: border-box;
    }
    button {
      margin-top: 20px;
      padding: 10px 20px;
      background-color: #00693e;
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 16px;
    }
    .msg-erro {
      color: red;
      margin-top: 10px;
      font-weight: bold;
    }
    .msg-sucesso {
      color: green;
      margin-top: 10px;
      font-weight: bold;
    }
    a.back-btn {
      display: inline-block;
      margin-top: 20px;
      text-decoration: none;
      color: #00693e;
    }
  </style>
</head>
<body>

<h2>Editar Posto</h2>

<?php if ($erro): ?>
  <div class="msg-erro"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>
<?php if ($sucesso): ?>
  <div class="msg-sucesso"><?= htmlspecialchars($sucesso) ?></div>
<?php endif; ?>

<form method="post">
  <label for="nome">Nome *</label>
  <input type="text" id="nome" name="nome" required value="<?= htmlspecialchars($posto['nome'] ?? '') ?>">

  <label for="local">Local *</label>
  <input type="text" id="local" name="local" required value="<?= htmlspecialchars($posto['local'] ?? '') ?>">

  <label for="contacto">Contacto *</label>
  <input type="text" id="contacto" name="contacto" required value="<?= htmlspecialchars($posto['contacto'] ?? '') ?>">

  <label for="unidade">Unidade</label>
  <input type="text" id="unidade" name="unidade" value="<?= htmlspecialchars($posto['unidade'] ?? '') ?>">

  <label for="empresa_id">Empresa</label>
  <select id="empresa_id" name="empresa_id">
    <option value="">-- Nenhuma --</option>
    <?php while ($empresa = $empresas_res->fetch_assoc()): ?>
      <option value="<?= $empresa['empresa_id'] ?>" <?= ($empresa['empresa_id'] == $posto['empresa_id']) ? 'selected' : '' ?>>
        <?= htmlspecialchars($empresa['nome']) ?>
      </option>
    <?php endwhile; ?>
  </select>

  <label for="capacidade">Capacidade</label>
  <input type="number" id="capacidade" name="capacidade" min="0" value="<?= htmlspecialchars($posto['capacidade'] ?? '') ?>">

  <button type="submit">Guardar Alterações</button>
</form>

<a class="back-btn" href="ver_lista_postos.php">← Voltar à lista de postos</a>

</body>
</html>
