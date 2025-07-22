<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}
include("../php/config.php");
$con->set_charset("utf8mb4");

// Carrega lista de veículos
$veiculos = [];
$resV = mysqli_query($con, "SELECT id_veiculo, matricula FROM veiculos ORDER BY matricula");
while ($v = mysqli_fetch_assoc($resV)) $veiculos[] = $v;

// Carrega lista de postos
$postos = [];
$resP = mysqli_query($con, "SELECT id_posto, nome FROM lista_postos ORDER BY nome");
while ($p = mysqli_fetch_assoc($resP)) $postos[] = $p;

// Carrega lista de tipos de combustível
$combustiveis = [];
$resC = mysqli_query($con, "SELECT id, nome FROM tipo_combustivel ORDER BY nome");
while ($c = mysqli_fetch_assoc($resC)) $combustiveis[] = $c;

// Filtros
$filtros = [];
if (!empty($_GET['id_veiculo'])) {
    $filtros[] = "a.id_veiculo = " . intval($_GET['id_veiculo']);
}
if (!empty($_GET['id_posto'])) {
    $filtros[] = "a.id_posto = " . intval($_GET['id_posto']);
}
if (!empty($_GET['id_tipo_combustivel'])) {
    $filtros[] = "a.id_tipo_combustivel = " . intval($_GET['id_tipo_combustivel']);
}

// WHERE
$where = count($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';

// Query principal
$sql = "
SELECT a.*, v.matricula, p.nome AS nome_posto, c.nome AS nome_combustivel
FROM abastecimentos a
LEFT JOIN veiculos v ON a.id_veiculo = v.id_veiculo
LEFT JOIN lista_postos p ON a.id_posto = p.id_posto
LEFT JOIN tipo_combustivel c ON a.id_tipo_combustivel = c.id
{$where}
ORDER BY a.data_abastecimento DESC
";
$result = mysqli_query($con, $sql);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <title>Lista de Abastecimentos</title>
  <style>
    body { font-family: Arial; background-color: #f2f2f2; margin: 30px; }
    table { width: 100%; border-collapse: collapse; background-color: #fff; }
    th, td { padding: 10px; border-bottom: 1px solid #ddd; }
    th { background: #f8f8f8; }
    .btn-voltar, button, a[href] {
      background-color: #007BFF; color: white; padding: 8px 16px;
      border-radius: 5px; text-decoration: none; font-size: 14px; border: none;
    }
    .btn-voltar:hover, button:hover, a[href]:hover { background-color: #0056b3; }
    .filter-actions { text-align: right; }
  </style>
</head>
<body>

<h2>Lista de Abastecimentos</h2>

<form method="get" action="">
  <table>
    <thead>
      <tr>
        <th>
          <select name="id_veiculo">
            <option value="">Veículo...</option>
            <?php foreach($veiculos as $v): ?>
              <option value="<?= $v['id_veiculo'] ?>" <?= ($_GET['id_veiculo'] ?? '') == $v['id_veiculo'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($v['matricula']) ?>
              </option>
            <?php endforeach ?>
          </select>
        </th>
        <th>
          <select name="id_posto">
            <option value="">Posto...</option>
            <?php foreach($postos as $p): ?>
              <option value="<?= $p['id_posto'] ?>" <?= ($_GET['id_posto'] ?? '') == $p['id_posto'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['nome']) ?>
              </option>
            <?php endforeach ?>
          </select>
        </th>
        <th>
          <select name="id_tipo_combustivel">
            <option value="">Combustível...</option>
            <?php foreach($combustiveis as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($_GET['id_tipo_combustivel'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nome']) ?>
              </option>
            <?php endforeach ?>
          </select>
        </th>
        <th class="filter-actions" colspan="3">
          <button type="submit">Filtrar</button>
          <a href="lista_abastecimento.php">Limpar</a>
        </th>
      </tr>
      <tr>
        <th>Veículo</th>
        <th>Posto</th>
        <th>Combustível</th>
        <th>Litros</th>
        <th>KMs</th>
        <th>Data</th>
      </tr>
    </thead>
    <a href="../html/index.php" class="btn-voltar">Voltar</a>
    <tbody>
      <?php while ($row = mysqli_fetch_assoc($result)): ?>
        <tr>
          <td><?= htmlspecialchars($row['matricula']) ?></td>
          <td><?= htmlspecialchars($row['nome_posto']) ?></td>
          <td><?= htmlspecialchars($row['nome_combustivel']) ?></td>
          <td><?= htmlspecialchars($row['litros']) ?></td>
          <td><?= htmlspecialchars($row['km_registados']) ?></td>
          <td><?= htmlspecialchars($row['data_abastecimento']) ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</form>

</body>
</html>
