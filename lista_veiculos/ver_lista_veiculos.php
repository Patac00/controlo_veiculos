<?php 
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");
include_once("../funcoes/funcoes_medias.php");
$con->set_charset("utf8mb4");

// Carrega opções de filtros para "tipo"
$tipos = [];
$resT = mysqli_query($con, "SELECT DISTINCT Tipo FROM veiculos");
while ($r = mysqli_fetch_row($resT)) $tipos[] = $r[0];

// Carrega lista de empresas para filtro
$empresas = [];
$resE = mysqli_query($con, "SELECT empresa_id, nome FROM empresas ORDER BY nome");
while ($e = mysqli_fetch_assoc($resE)) $empresas[] = $e;

// Recolhe filtros do GET  
$filtros = [];
if (!empty($_GET['matricula'])) {
    $m = mysqli_real_escape_string($con, $_GET['matricula']);
    $filtros[] = "v.matricula LIKE '%{$m}%'";
}
if (!empty($_GET['descricao'])) {
    $d = mysqli_real_escape_string($con, $_GET['descricao']);
    $filtros[] = "v.Descricao LIKE '%{$d}%'";
}
if (!empty($_GET['empresa_atual_id'])) {
    $e = (int)$_GET['empresa_atual_id'];
    $filtros[] = "v.empresa_atual_id = {$e}";
}
if (!empty($_GET['tipo'])) {
    $t = mysqli_real_escape_string($con, $_GET['tipo']);
    $filtros[] = "v.Tipo = '{$t}'";
}
if (!empty($_GET['grupo'])) {
    $g = mysqli_real_escape_string($con, $_GET['grupo']);
    $filtros[] = "v.Grupo LIKE '%{$g}%'";
}

$where = count($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';

$sql = "
  SELECT v.*, e.nome AS nome_empresa
  FROM veiculos v
  LEFT JOIN empresas e ON v.empresa_atual_id = e.empresa_id
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
      margin-bottom: 10px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background-color: white;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
      margin-bottom: 15px;
    }
    th, td {
      padding: 8px 12px;
      text-align: center;
      border-bottom: 1px solid #ddd;
      font-size: 14px;
      white-space: nowrap;
    }
    thead tr.filters th {
      background-color: #e9f1ff;
      padding: 6px 8px;
    }
    thead tr.filters input,
    thead tr.filters select {
      font-size: 14px;
      padding: 5px;
      width: 100%;
      box-sizing: border-box;
      border-radius: 4px;
      border: 1px solid #ccc;
      transition: border-color 0.3s;
    }
    thead tr.filters input:focus,
    thead tr.filters select:focus {
      border-color: #007BFF;
      outline: none;
    }
    tbody tr:hover {
      background-color: #f1f1f1;
    }
    .edit-btn {
      text-decoration: none;
      background-color: #007BFF;
      color: white;
      padding: 5px 10px;
      border-radius: 5px;
      font-weight: 600;
      font-size: 14px;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: background-color 0.3s;
    }
    .edit-btn:hover {
      background-color: #0056b3;
    }
    .edit-btn svg {
      width: 16px;
      height: 16px;
      fill: white;
    }
    button, a.btn-voltar, a.export-btn {
      background-color: #007BFF;
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 6px;
      text-decoration: none;
      font-size: 14px;
      cursor: pointer;
      margin: 0 4px 12px 0;
      display: inline-block;
      transition: background-color 0.3s;
    }
    button:hover, a.btn-voltar:hover, a.export-btn:hover {
      background-color: #0056b3;
    }
    .filter-actions {
      text-align: right;
      padding-right: 0;
    }
    .btn-voltar {
      margin-bottom: 15px;
      font-weight: 600;
    }
    .pagination {
      margin-top: 12px;
      text-align: center;
    }
    .pagination button {
      margin: 0 3px;
      font-weight: 600;
      padding: 6px 12px;
    }
  </style>
</head>
<body>

<a href="../html/index.php" class="btn-voltar">Voltar</a>

<h2>Lista de Veículos</h2>

<form method="get" action="">
  <table id="veiculos-table">
    <thead>
      <tr class="filters">
        <th><input type="text" name="matricula" placeholder="Matrícula" value="<?= htmlspecialchars($_GET['matricula'] ?? '') ?>"></th>
        <th><input type="text" name="descricao" placeholder="Descrição" value="<?= htmlspecialchars($_GET['descricao'] ?? '') ?>"></th>
        <th>
          <select name="empresa_atual_id">
            <option value="">Empresa...</option>
            <?php foreach($empresas as $emp): ?>
              <option value="<?= $emp['empresa_id'] ?>" <?= (($_GET['empresa_atual_id'] ?? '') == $emp['empresa_id'] ? 'selected' : '') ?>>
                <?= htmlspecialchars($emp['nome']) ?>
              </option>
            <?php endforeach ?>
          </select>
        </th>
        <th><input type="text" name="grupo" placeholder="Grupo" value="<?= htmlspecialchars($_GET['grupo'] ?? '') ?>"></th>
        <th>
          <select name="tipo">
            <option value="">Tipo...</option>
            <?php foreach($tipos as $tt): ?>
              <option value="<?= htmlspecialchars($tt)?>" <?= (($_GET['tipo'] ?? '') == $tt ? 'selected' : '') ?>><?= htmlspecialchars($tt)?></option>
            <?php endforeach ?>
          </select>
        </th>
        <th colspan="10" class="filter-actions">
          <button type="submit">Filtrar</button>
          <a href="ver_lista_veiculos.php">Limpar</a>
          <a href="#" class="export-btn" onclick="exportTableToCSV('veiculos.csv'); return false;">Exportar CSV</a>
        </th>
      </tr>
    <tr>
      <th>ID</th>
      <th>Matrícula</th>
      <th>Descrição</th>
      <th>Empresa Atual</th>
      <th>Tipo</th>
      <th>Grupo</th>
      <th>Relatório</th>
      <th>Critério</th>
      <th>Abastecimentos</th>
      <th>Medida</th>
      <th>KM Atual</th>
      <th>Horas Atual</th>
      <th>Estado</th>
      <th>Capacidade Tanque</th>
      <th>Ações</th>
    </tr>
    </thead>
    <tbody>
      <?php 
      $rows = [];
      while ($row = mysqli_fetch_assoc($result)) {
          $rows[] = $row;
      }
      ?>
<?php foreach ($rows as $index => $row): ?>
  <tr class="data-row">
    <td><?= $row['id_veiculo'] ?></td>
    <td><?= $row['matricula'] ?></td>
    <td><?= $row['Descricao'] ?></td>
    <td><?= $row['nome_empresa'] ?></td>
    <td><?= $row['Tipo'] ?></td>
    <td><?= $row['Grupo'] ?></td>
    <td><?= $row['relatorio'] ?></td>
    <td><?= $row['criterio'] ?></td>
    <td><?= $row['abastecimentos'] ?></td>
    <td><?= $row['medida'] ?></td>
    <td><?= $row['km_atual'] ?></td>
    <td><?= $row['horas_atual'] ?></td>
    <td><?= $row['estado'] ?></td>
    <td><?= $row['capacidade_tanque'] ?></td>
    <td>
      <a href="editar.php?id=<?= $row['id_veiculo'] ?>" class="btn btn-sm btn-warning">Editar</a>
      <a href="eliminar.php?id=<?= $row['id_veiculo'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Tens a certeza que queres eliminar?')">Eliminar</a>
    </td>
  </tr>
<?php endforeach; ?>

    </tbody>
  </table>
</form>

<div class="pagination">
  <button onclick="changePage(-1)">« Anterior</button>
  <span id="page-info">Página 1</span>
  <button onclick="changePage(1)">Seguinte »</button>
</div>

<script>
// Paginação simples cliente - mostra 10 linhas por página
const rows = document.querySelectorAll('tr.data-row');
const rowsPerPage = 10;
let currentPage = 1;
const totalPages = Math.ceil(rows.length / rowsPerPage);

function showPage(page) {
  if (page < 1 || page > totalPages) return;
  currentPage = page;
  const start = (page - 1) * rowsPerPage;
  const end = start + rowsPerPage;

  rows.forEach((row, i) => {
    row.style.display = (i >= start && i < end) ? '' : 'none';
  });

  document.getElementById('page-info').textContent = `Página ${currentPage} de ${totalPages}`;
}

function changePage(increment) {
  showPage(currentPage + increment);
}

showPage(1);

// Exportar tabela para CSV
function downloadCSV(csv, filename) {
  const csvFile = new Blob([csv], {type: "text/csv"});
  const downloadLink = document.createElement("a");
  downloadLink.download = filename;
  downloadLink.href = window.URL.createObjectURL(csvFile);
  downloadLink.style.display = "none";
  document.body.appendChild(downloadLink);
  downloadLink.click();
  document.body.removeChild(downloadLink);
}

function exportTableToCSV(filename) {
  const csv = [];
  const rows = document.querySelectorAll("#veiculos-table tr");

  for (const row of rows) {
    const cols = row.querySelectorAll("th, td");
    const rowData = [];
    for (const col of cols) {
      let data = col.innerText.replace(/,/g, ""); 
      data = data.trim();
      rowData.push('"' + data + '"');
    }
    csv.push(rowData.join(","));
  }
  downloadCSV(csv.join("\n"), filename);
}
</script>

</body>
</html>
