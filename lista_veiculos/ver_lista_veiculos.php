<?php 
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}
include("../php/config.php");
$con->set_charset("utf8mb4");

// Carrega opções de filtros para "tipo"
$tipos = [];
$resT = mysqli_query($con, "SELECT DISTINCT tipo FROM veiculos");
while ($r = mysqli_fetch_row($resT)) $tipos[] = $r[0];

// Carrega lista de empresas para filtro
$empresas = [];
$resE = mysqli_query($con, "SELECT id_empresa, nome FROM empresas ORDER BY nome");
while ($e = mysqli_fetch_assoc($resE)) $empresas[] = $e;

// Recolhe filtros do GET
$filtros = [];
if (!empty($_GET['matricula'])) {
    $m = mysqli_real_escape_string($con, $_GET['matricula']);
    $filtros[] = "v.matricula LIKE '%{$m}%'";
}
if (!empty($_GET['descricao'])) {
    $d = mysqli_real_escape_string($con, $_GET['descricao']);
    $filtros[] = "v.descricao LIKE '%{$d}%'";
}
if (!empty($_GET['empresa_atual_id'])) {
    $e = mysqli_real_escape_string($con, $_GET['empresa_atual_id']);
    $filtros[] = "v.empresa_atual_id = '{$e}'";
}

if (!empty($_GET['tipo'])) {
    $t = mysqli_real_escape_string($con, $_GET['tipo']);
    $filtros[] = "v.tipo = '{$t}'";
}
if (!empty($_GET['grupo'])) {
    $g = mysqli_real_escape_string($con, $_GET['grupo']);
    $filtros[] = "v.grupo LIKE '%{$g}%'";
}

// Monta o WHERE
$where = count($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';

// Query principal com JOIN para obter nome da empresa
$sql = "
  SELECT v.*, e.nome AS nome_empresa
  FROM veiculos v
  LEFT JOIN empresas e ON v.empresa_atual_id = e.id_empresa
  {$where}
  ORDER BY v.matricula
";

$result = mysqli_query($con, $sql);

?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <title>Lista de Veículos Filtrável</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f2f2f2;
      margin: 30px;
    }

    h2 {
      color: #333;
      text-align: center;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background-color: white;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
      margin-bottom: 15px;
    }

    th, td {
      padding: 10px 12px;
      text-align: left;
      border-bottom: 1px solid #ddd;
    }

    th {
      background-color: #f8f8f8;
    }

    th input, th select {
      width: 100%;
      padding: 6px;
      box-sizing: border-box;
      border: 1px solid #ccc;
      border-radius: 5px;
    }

    tbody tr:hover {
      background-color: #f1f1f1;
    }

    .edit-btn {
      text-decoration: none;
      background-color: #4CAF50;
      color: white;
      padding: 5px 10px;
      border-radius: 4px;
      font-size: 14px;
    }

    .edit-btn:hover {
      background-color: #45a049;
    }

    button, a[href] {
      background-color: #007BFF;
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 5px;
      text-decoration: none;
      font-size: 14px;
      cursor: pointer;
    }

    button:hover, a[href]:hover {
      background-color: #0056b3;
    }

    .filter-actions {
      text-align: right;
    }

    .btn-voltar {
      display: inline-block;
      background-color: #007BFF;
      color: white;
      padding: 10px 18px;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 600;
      font-size: 16px;
      transition: background-color 0.3s ease;
      cursor: pointer;
      border: none;
    }

    .btn-voltar:hover {
      background-color: #0056b3;
    }

  </style>
</head>
<body>

<h2>Lista de Veículos</h2>

<form method="get" action="">
  <table>
    <thead>
      <tr>
        <th><input type="text" name="matricula" placeholder="Matrícula" value="<?= htmlspecialchars($_GET['matricula'] ?? '') ?>"></th>
        
        <th><input type="text" name="descricao" placeholder="Descrição" value="<?= htmlspecialchars($_GET['descricao'] ?? '') ?>"></th>
        <th>
          <select name="empresa_atual_id">
            <option value="">Empresa...</option>
            <?php foreach($empresas as $emp): ?>
              <option value="<?= $emp['id_empresa'] ?>" <?= (($_GET['empresa_atual_id'] ?? '') == $emp['id_empresa'] ? 'selected' : '') ?>>
                <?= htmlspecialchars($emp['nome']) ?>
              </option>
            <?php endforeach ?>
          </select>
        </th>
        <th>
          <select name="tipo">
            <option value="">Tipo...</option>
            <?php foreach($tipos as $tt): ?>
              <option value="<?= htmlspecialchars($tt)?>" <?= (($_GET['tipo'] ?? '') == $tt ? 'selected' : '') ?>><?= htmlspecialchars($tt)?></option>
            <?php endforeach ?>
          </select>
        </th>
        <th><input type="text" name="grupo" placeholder="Grupo" value="<?= htmlspecialchars($_GET['grupo'] ?? '') ?>"></th>
        <th></th>
        <th></th>
        <th class="filter-actions">
          <button type="submit">Filtrar</button>
          <a href="ver_lista_veiculos.php">Limpar</a>
        </th>
      </tr>
      <tr>
        <th>Matrícula</th>
        <th>Descrição</th>
        <th>Empresa</th>
        <th>Tipo</th>
        <th>Grupo</th>
        <th>km totais</th>
        <th>l/100km</th>
        <th>Horas totais</th>
        <th>l/Hora</th>
        <th>Estado</th>
        <th>Ações</th>
      </tr>
    </thead>
    <a href="../html/index.php" class="btn-voltar">Voltar</a>

    <tbody>
      <?php while ($row = mysqli_fetch_assoc($result)): ?>
        <tr>
          <td><?= htmlspecialchars($row['matricula'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['Descricao'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['nome_empresa'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['Tipo'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['Grupo'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['km_atual'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['l/100km'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['horas_atual'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['l/hora'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['estado'] ?? '') ?></td>
          <td>  
            <a href="editar_veiculo.php?matricula=<?= urlencode($row['matricula'] ?? '') ?>" class="edit-btn">Editar</a>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</form>

</body>
</html>
