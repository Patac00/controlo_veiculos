<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");
$con->set_charset("utf8mb4");

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
if (!empty($_GET['data_inicio'])) {
    $di = mysqli_real_escape_string($con, $_GET['data_inicio']);
    $filtros[] = "a.data_abastecimento >= '{$di}'";
}
if (!empty($_GET['data_fim'])) {
    $df = mysqli_real_escape_string($con, $_GET['data_fim']);
    $filtros[] = "a.data_abastecimento <= '{$df}'";
}
if (!empty($_GET['local'])) {
    $loc = mysqli_real_escape_string($con, $_GET['local']);
    $filtros[] = "p.nome LIKE '%{$loc}%'";
}
if (!empty($_GET['empresa_id'])) {
    $ei = (int)$_GET['empresa_id'];
    $filtros[] = "a.empresa_id = {$ei}";
}

$where = count($filtros) ? 'WHERE ' . implode(' AND ', $filtros) : '';

$sql = "
SELECT a.*, e.nome AS nome_empresa, v.matricula, p.nome AS posto_nome
FROM abastecimentos a
LEFT JOIN empresas e ON a.empresa_id = e.empresa_id
LEFT JOIN veiculos v ON a.id_veiculo = v.id_veiculo
LEFT JOIN lista_postos p ON a.id_posto = p.id_posto
{$where}
ORDER BY a.data_abastecimento DESC, v.matricula
";


$result = mysqli_query($con, $sql);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <title>Lista de Abastecimentos</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f2f2f2;
      margin: 30px;
    }
    h2 {
      color: #00693e;
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
      border-color: #00693e;
      outline: none;
    }
    tbody tr:hover {
      background-color: #f1f1f1;
    }
    .btn-warning {
      background-color: #f0ad4e;
      color: white;
      border: none;
      padding: 5px 10px;
      border-radius: 5px;
      font-weight: 600;
      font-size: 14px;
      text-decoration: none;
    }
    .btn-warning:hover {
      background-color: #ec971f;
      color: white;
      text-decoration: none;
    }
    .btn-danger {
      background-color: #d9534f;
      color: white;
      border: none;
      padding: 5px 10px;
      border-radius: 5px;
      font-weight: 600;
      font-size: 14px;
      cursor: pointer;
    }
    .btn-danger:hover {
      background-color: #c9302c;
    }
    button, a.btn-voltar, a.export-btn {
      background-color: #00693e;
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
      background-color: #005632;
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
      background-color: #00693e;
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      transition: background-color 0.3s;
    }
    .pagination button:hover:not(:disabled) {
      background-color: #005632;
    }
    .pagination button:disabled {
      background-color: #ccc;
      cursor: not-allowed;
      color: #666;
    }
    .action-buttons {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-bottom: 15px;
    }

    .btn-importar {
      background-color: #4cafef; /* Azul claro */
      color: white;
      padding: 8px 14px;
      border-radius: 5px;
      text-decoration: none;
    }

    .btn-inserir {
      background-color: #4caf50; /* Verde */
      color: white;
      padding: 8px 14px;
      border-radius: 5px;
      text-decoration: none;
    }

    .btn-importar:hover {
      background-color: #2f8bd1;
    }

    .btn-inserir:hover {
      background-color: #3e8e41;
    }
  </style>
</head>
<body>

<a href="../html/index.php" class="btn-voltar">← Voltar</a>

<h2>Lista de Abastecimentos</h2>

<br>

<div class="action-buttons">
  <a href="importar_excel.php" class="btn-importar">Importar Bomba</a>
  <a href="registar_abast.php" class="btn-inserir">Inserir Abastecimento</a>
</div>


<form method="get" action="">
  <table id="abastecimentos-table">
    <thead>
      <tr class="filters">
        <th><input type="text" name="matricula" placeholder="Matrícula" value="<?= htmlspecialchars($_GET['matricula'] ?? '') ?>"></th>
        <th><input type="date" name="data_inicio" placeholder="Data início" value="<?= htmlspecialchars($_GET['data_inicio'] ?? '') ?>"></th>
        <th><input type="date" name="data_fim" placeholder="Data fim" value="<?= htmlspecialchars($_GET['data_fim'] ?? '') ?>"></th>
        <th><input type="text" name="local" placeholder="Local" value="<?= htmlspecialchars($_GET['local'] ?? '') ?>"></th>
        <th>
          <select name="empresa_id">
            <option value="">Empresa...</option>
            <?php foreach($empresas as $emp): ?>
              <option value="<?= $emp['empresa_id'] ?>" <?= (($_GET['empresa_id'] ?? '') == $emp['empresa_id'] ? 'selected' : '') ?>>
                <?= htmlspecialchars($emp['nome']) ?>
              </option>
            <?php endforeach ?>
          </select>
        </th>
        <th colspan="6" class="filter-actions">
          <button type="submit">Filtrar</button>
          <a href="ver_lista_abastecimentos.php" class="export-btn">Limpar</a>
          <a href="#" class="export-btn" onclick="exportTableToCSV('abastecimentos.csv'); return false;">Exportar CSV</a>
        </th>
      </tr>
      <tr>
        <th>Matrícula</th>
        <th>Data</th>
        <th>Local</th>
        <th>Empresa</th>
        <th>Litros</th>
        <th>Preço/L c/IVA</th>
        <th>Custo</th>
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
      <?php foreach ($rows as $row): ?>
      <tr class="data-row">
        <!--<td><?= $row['id_abastecimento'] ?? $row['id'] ?></td> -->
        <td><?= htmlspecialchars($row['matricula'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['data_abastecimento'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['posto_nome'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['nome_empresa'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['litros'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['preco_litro'] ?? '') ?></td>
        <td><?= htmlspecialchars($row['custo'] ?? '') ?></td>
        <td>
          <a href="editar_abastecimento.php?id=<?= urlencode($row['id_abastecimento'] ?? $row['id']) ?>" class="btn-warning">Editar</a>
          <form method="post" action="eliminar_abastecimento.php" style="display:inline;" onsubmit="return confirm('Tens a certeza que queres eliminar?');">
            <input type="hidden" name="id" value="<?= $row['id_abastecimento'] ?? $row['id'] ?>">
            <button type="submit" class="btn-danger">Eliminar</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</form>

<div class="pagination">
  <button type="button" onclick="changePage(-1)">« Anterior</button>
  <span id="page-info">Página 1</span>
  <button type="button" onclick="changePage(1)">Seguinte »</button>
</div>

<script>
// Paginação simples cliente - mostra 10 linhas por página
const rows = document.querySelectorAll('tr.data-row');
const rowsPerPage = 10;
let currentPage = 1;
const totalPages = Math.ceil(rows.length / rowsPerPage);

function updateButtons() {
  document.querySelector('button[onclick="changePage(-1)"]').disabled = (currentPage === 1);
  document.querySelector('button[onclick="changePage(1)"]').disabled = (currentPage === totalPages || totalPages === 0);
}

function showPage(page) {
  if (page < 1 || page > totalPages) return;
  currentPage = page;
  const start = (page - 1) * rowsPerPage;
  const end = start + rowsPerPage;

  rows.forEach((row, i) => {
    row.style.display = (i >= start && i < end) ? '' : 'none';
  });

  document.getElementById('page-info').textContent = `Página ${currentPage} de ${totalPages}`;
  updateButtons();
}

function changePage(increment) {
  let newPage = currentPage + increment;
  if (newPage < 1) newPage = 1;
  if (newPage > totalPages) newPage = totalPages;
  showPage(newPage);
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
  const rows = document.querySelectorAll("#abastecimentos-table tr");

  for (const row of rows) {
    const cols = row.querySelectorAll("th, td");
    const rowData = [];
    for (const col of cols) {
      let data = col.innerText.replace(/,/g, ""); // remove vírgulas
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
