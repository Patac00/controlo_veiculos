<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");
require('../fpdf/fpdf.php');


$anos = [];
$grupos = [];
$locais = [];
$tipos = [];

$resAno = mysqli_query($con, "SELECT DISTINCT YEAR(data) AS ano FROM bomba ORDER BY ano DESC");
while ($row = mysqli_fetch_assoc($resAno)) $anos[] = $row['ano'];

$resGrupo = mysqli_query($con, "SELECT DISTINCT nome FROM grupos ORDER BY nome ASC");
while ($row = mysqli_fetch_assoc($resGrupo)) $grupos[] = $row['nome'];

$resLocal = mysqli_query($con, "SELECT DISTINCT local FROM lista_postos ORDER BY local ASC");
while ($row = mysqli_fetch_assoc($resLocal)) $locais[] = $row['local'];

$resTipo = mysqli_query($con, "SELECT DISTINCT tipo FROM grupos ORDER BY tipo ASC");
while ($row = mysqli_fetch_assoc($resTipo)) $tipos[] = $row['tipo'];

$ano = $_GET['ano'] ?? date('Y');
$mes = $_GET['mes'] ?? date('m');
$local = $_GET['local'] ?? '';
$grupo = $_GET['grupo'] ?? '';
$tipo = $_GET['tipo'] ?? '';



$inicio = "$ano-$mes-01";
$fim = date("Y-m-t", strtotime($inicio));

$where_veic = [];
if ($grupo) $where_veic[] = "v.Grupo = '" . mysqli_real_escape_string($con, $grupo) . "'";
if ($tipo) $where_veic[] = "g.tipo = '" . mysqli_real_escape_string($con, $tipo) . "'";
$where_veic_sql = $where_veic ? "WHERE " . implode(" AND ", $where_veic) : "";

$sql_veiculos = "
    SELECT v.id_veiculo, v.matricula, v.Descricao, e.nome AS empresa, v.Grupo, g.tipo
    FROM veiculos v
    LEFT JOIN empresas e ON v.empresa_atual_id = e.empresa_id
    LEFT JOIN grupos g ON v.Grupo = g.nome
    $where_veic_sql
    ORDER BY v.matricula
";

$res_veiculos = mysqli_query($con, $sql_veiculos);
$relatorio = [];

$litros_total_geral = 0;
$valor_total_geral = 0;

while ($v = mysqli_fetch_assoc($res_veiculos)) {
    $id_veic = (int)$v['id_veiculo'];

    // Período atual (mês selecionado)
    $inicio = "$ano-$mes-01";
    $fim = date("Y-m-t", strtotime($inicio));

    // Litros e valor (bomba)
    $sql_litros_bomba = "
        SELECT 
            SUM(b.quantidade) AS total,
            SUM(b.quantidade * IFNULL(ms.preco_litro, 0)) AS total_valor
        FROM bomba b
        LEFT JOIN movimentos_stock ms 
            ON ms.id_posto = b.id_posto
            AND ms.id_veiculo = b.id_veiculo
            AND DATE(ms.data) = b.data
            AND ms.litros > 0
        LEFT JOIN lista_postos p ON b.id_posto = p.id_posto
        WHERE b.id_veiculo = $id_veic 
          AND b.data BETWEEN '$inicio' AND '$fim'
    ";
    if ($local) $sql_litros_bomba .= " AND p.local = '" . mysqli_real_escape_string($con, $local) . "'";
    $res_bomba = mysqli_query($con, $sql_litros_bomba);
    $row_bomba = mysqli_fetch_assoc($res_bomba);
    $litros_bomba = (float)($row_bomba['total'] ?? 0);
    $valor_bomba = (float)($row_bomba['total_valor'] ?? 0);

    // Litros e valor (manual)
    $sql_litros_manual = "
        SELECT SUM(litros) AS total, SUM(litros * preco_litro) AS total_valor FROM abastecimentos a
        LEFT JOIN lista_postos p ON a.id_posto = p.id_posto
        WHERE id_veiculo = $id_veic AND data_abastecimento BETWEEN '$inicio' AND '$fim'
    ";
    if ($local) $sql_litros_manual .= " AND p.local = '" . mysqli_real_escape_string($con, $local) . "'";
    $res_manual = mysqli_query($con, $sql_litros_manual);
    $row_manual = mysqli_fetch_assoc($res_manual);
    $litros_manual = (float)($row_manual['total'] ?? 0);
    $valor_manual = (float)($row_manual['total_valor'] ?? 0);

    $litros_total = $litros_bomba + $litros_manual;
    $valor_total = $valor_bomba + $valor_manual;

    $litros_total_geral += $litros_total;
    $valor_total_geral += $valor_total;

    // Kms/hrs no mês atual
    $sql_min = "SELECT MIN(odometro) AS min_km FROM bomba WHERE id_veiculo = $id_veic AND data BETWEEN '$inicio' AND '$fim'";
    $sql_max = "SELECT MAX(odometro) AS max_km FROM bomba WHERE id_veiculo = $id_veic AND data BETWEEN '$inicio' AND '$fim'";
    $min_km = (float)(mysqli_fetch_assoc(mysqli_query($con, $sql_min))['min_km'] ?? 0);
    $max_km = (float)(mysqli_fetch_assoc(mysqli_query($con, $sql_max))['max_km'] ?? 0);
    $kms_hrs_mes = max(0, $max_km - $min_km);

    // Consumo no mês atual
    $consumo_1mes = '-';
    if ($litros_total > 0 && $kms_hrs_mes > 0) {
        $consumo_1mes = ($v['tipo'] == 'Máquina' || $v['tipo'] == 'Empilhador')
            ? round($litros_total / $kms_hrs_mes, 2)
            : round(($litros_total / $kms_hrs_mes) * 100, 2);
    }

    // --- Cálculos para 3 e 12 meses ---
    $kms_3 = 0;
    $litros_3 = 0;
    $kms_12 = 0;
    $litros_12 = 0;

    for ($i = 0; $i < 12; $i++) {
        $mes_ref = date('Y-m', strtotime("-$i months", strtotime("$ano-$mes-01")));
        $inicio_m = "$mes_ref-01";
        $fim_m = date("Y-m-t", strtotime($inicio_m));

        // Kms/Hrs no mês i
        $sql_min = "SELECT MIN(odometro) AS min_km FROM bomba WHERE id_veiculo = $id_veic AND data BETWEEN '$inicio_m' AND '$fim_m'";
        $sql_max = "SELECT MAX(odometro) AS max_km FROM bomba WHERE id_veiculo = $id_veic AND data BETWEEN '$inicio_m' AND '$fim_m'";
        $min_km = (float)(mysqli_fetch_assoc(mysqli_query($con, $sql_min))['min_km'] ?? 0);
        $max_km = (float)(mysqli_fetch_assoc(mysqli_query($con, $sql_max))['max_km'] ?? 0);
        $diferenca_km = max(0, $max_km - $min_km);

        // Litros no mês i (bomba + manual)
        $sql_lts_b = "SELECT SUM(quantidade) as total FROM bomba WHERE id_veiculo = $id_veic AND data BETWEEN '$inicio_m' AND '$fim_m'";
        $lts_b = (float)(mysqli_fetch_assoc(mysqli_query($con, $sql_lts_b))['total'] ?? 0);

        $sql_lts_m = "SELECT SUM(litros) as total FROM abastecimentos WHERE id_veiculo = $id_veic AND data_abastecimento BETWEEN '$inicio_m' AND '$fim_m'";
        $lts_m = (float)(mysqli_fetch_assoc(mysqli_query($con, $sql_lts_m))['total'] ?? 0);

        if ($i < 3) {
            $kms_3 += $diferenca_km;
            $litros_3 += $lts_b + $lts_m;
        }
        $kms_12 += $diferenca_km;
        $litros_12 += $lts_b + $lts_m;
    }

    // Médias mensais
    $media_3 = $kms_3 / 3;
    $media_12 = $kms_12 / 12;

    // Consumo para 3 meses
    if ($litros_3 > 0 && $kms_3 > 0) {
        $consumo_3 = ($v['tipo'] == 'Máquina' || $v['tipo'] == 'Empilhador')
            ? round($litros_3 / $kms_3, 2)
            : round(($litros_3 / $kms_3) * 100, 2);
    } else {
        $consumo_3 = '-';
    }

    // Consumo para 12 meses
    if ($litros_12 > 0 && $kms_12 > 0) {
        $consumo_12 = ($v['tipo'] == 'Máquina' || $v['tipo'] == 'Empilhador')
            ? round($litros_12 / $kms_12, 2)
            : round(($litros_12 / $kms_12) * 100, 2);
    } else {
        $consumo_12 = '-';
    }

    // Nota para o mês atual
    $nota = '';
    if ($litros_total > 0 && $kms_hrs_mes > 0) {
        if ($v['tipo'] == 'Máquina' || $v['tipo'] == 'Empilhador') {
            $nota = "L/h = $litros_total ÷ $kms_hrs_mes";
        } else {
            $nota = "L/100km = ($litros_total ÷ $kms_hrs_mes) × 100";
        }
    }

    // Guardar no array do relatório
    $relatorio[] = [
        'descricao' => $v['Descricao'],
        'matricula' => $v['matricula'],
        'empresa' => $v['empresa'],
        'grupo' => $v['Grupo'],
        'tipo' => $v['tipo'],
        'litros' => $litros_total,
        'kms_hrs_mes' => $kms_hrs_mes,
        'media_3meses' => number_format($media_3, 2, ',', '.'),
        'media_12meses' => number_format($media_12, 2, ',', '.'),
        'consumo_1mes' => $consumo_1mes,
        'consumo_3meses' => is_numeric($consumo_3) ? number_format($consumo_3, 2, ',', '.') : '-',
        'consumo_12meses' => is_numeric($consumo_12) ? number_format($consumo_12, 2, ',', '.') : '-',
        'total_valor' => $valor_total,
        'notas' => $nota
    ];
}


// CALCULAR PREÇO MÉDIO NA TABELA movimentos_stock
$sql_preco = "SELECT tipo_combustivel, id_posto, AVG(preco_litro) as preco_medio 
              FROM movimentos_stock 
              GROUP BY tipo_combustivel, id_posto";
$res_preco = mysqli_query($con, $sql_preco);

$precos_medios = [];
while ($row = mysqli_fetch_assoc($res_preco)) {
    $key = $row['tipo_combustivel'] . '_' . $row['id_posto'];
    $precos_medios[$key] = $row['preco_medio'];
}

$grupos_rel = [];
foreach ($relatorio as $item) {
    $grp = $item['grupo'] ?: 'Sem Grupo';
    if (!isset($grupos_rel[$grp])) $grupos_rel[$grp] = [];
    $grupos_rel[$grp][] = $item;
}

$data = DateTime::createFromFormat('Y-m', "$ano-$mes");
if (!$data) $data = new DateTime();

$nome_mes = $data->format('F \d\e Y');
$traducoes =[
    'January' => 'Janeiro',
    'February' => 'Fevereiro',
    'March' => 'Março',
    'April' => 'Abril',
    'May' => 'Maio',
    'June' => 'Junho',
    'July' => 'Julho',
    'August' => 'Agosto',
    'September' => 'Setembro',
    'October' => 'Outubro',
    'November' => 'Novembro',
    'December' => 'Dezembro'];
$nome_mes_pt = strtr($nome_mes, $traducoes);


// Mostrar no topo do relatório
//echo "<h2>Relatório de Consumos - $nome_mes_pt</h2>";
$preco_medio_comb = count($precos_medios) > 0 ? array_sum($precos_medios) / count($precos_medios) : 0;
echo "<p>Preço médio por litro (movimentos_stock): <strong>" . number_format($preco_medio_comb, 3, ',', '.') . " €</strong></p>";
// Preço médio geral de movimentos_stock
$preco_medio_comb = 0;
if (count($precos_medios) > 0) {
    $preco_medio_comb = array_sum($precos_medios) / count($precos_medios);
}
$temFiltro = ($ano !== '' && $ano !== null) || 
             ($mes !== '' && $mes !== null) || 
             ($grupo !== '') || 
             ($tipo !== '') || 
             ($local !== '');
?>
 
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8" />
    <title>Relatório Mensal</title>
    <style>
    body {
    font-family: Arial, sans-serif;
    margin: 20px 40px;
    background: #f7faff;
    color: #003366;
}
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    font-size: 13px;
    table-layout: fixed; /* Para larguras fixas das colunas */
}
th, td {
    border: 1px solid #aaccee;
    padding: 6px 8px;
    text-align: center;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
}

/* Define larguras fixas para cada coluna */
th:nth-child(1), td:nth-child(1) { width: 18%;  text-align: left; }  /* Descrição */
th:nth-child(2), td:nth-child(2) { width: 6%;  text-align: left; }  /* Matrícula */
th:nth-child(3), td:nth-child(3) { width: 12%;  text-align: left; }  /* Empresa */
th:nth-child(4), td:nth-child(4) { width: 5%;   text-align: right; } /* Lts (mês) */
th:nth-child(5), td:nth-child(5) { width: 9%;   text-align: right; } /* Kms/Hrs (Reg bomba) */
th:nth-child(6), td:nth-child(6) { width: 13%;   text-align: right; } /* Kms/Hrs médios (3 meses) */
th:nth-child(7), td:nth-child(7) { width: 13%;   text-align: right; } /* Kms/Hrs médios (12 meses) */
th:nth-child(8), td:nth-child(8) { width: 7%;   text-align: right; } /* L/Kms (1 mês) */
th:nth-child(9), td:nth-child(9) { width: 10%;   text-align: right; } /* L/Kms (3 meses) */
th:nth-child(10), td:nth-child(10) { width: 10%; text-align: right; } /* L/Kms (12 meses) */
th:nth-child(11), td:nth-child(11) { width: 8%; text-align: right; } /* Total €*/
th:nth-child(12), td:nth-child(12) { width: 5%; text-align: right; } /* Notas */
th {
    background-color: #004080;
    color: white;
}
.right { text-align: right; }
.left { text-align: left; }
.nota {
    font-size: 11px;
    color: #666;
    text-align: left;
    padding-left: 5px;
}
form label {
    font-weight: bold;
    margin-right: 8px;
}
form select {
    margin-right: 15px;
    padding: 4px 6px;
}
button {
    background-color: #004080;
    color: white;
    border: none;
    padding: 7px 15px;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    margin-top: 10px;
}
button:hover {
    background-color: #424446ff;
}
.btn-voltar {
    position: fixed;
    bottom: 20px;
    left: 20px;
    background-color: #ff0000ff;
    color: white;
    padding: 12px 18px;
    border-radius: 30px;
    text-decoration: none;
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
    z-index: 999;
    transition: 0.3s;
}
.btn-voltar:hover {
    background-color: #5f6264ff;
}

@media print {
    body {
        background: white !important;
        color: black !important;
        margin: 10mm !important;
        font-size: 12pt !important;
    }
    a.btn-voltar, button, form, select, input, /* esconder botões e filtros */
    button, form {
        display: none !important;
    }
    table {
        width: 100% !important;
        border-collapse: collapse !important;
        page-break-inside: avoid !important;
        table-layout: fixed !important; /* mantém largura fixa na impressão */
    }
    th, td {
        border: 1px solid black !important;
        padding: 6px 10px !important;
        text-align: center !important;
        overflow: hidden !important;
        white-space: nowrap !important;
        text-overflow: ellipsis !important;
    }
    th {
        background-color: #ccc !important; /* torna header visível na impressão */
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    h2, h3 {
        page-break-after: avoid !important;
    }
    tr {
        page-break-inside: avoid !important;
    }
    table, thead, tbody, tr, td, th {
        page-break-inside: avoid !important;
    }
}


    </style>

    
</head>
<body>

<h2>Relatório Mensal de Consumos: <?= $nome_mes_pt ?></h2>

<h5>Legenda:</h5>
<p>AA - Amanhece & Acontece / AP - Ambipombal / BT - Btermec / CV - Civtrhi / FR - Forças Robustas / O - Outros / PT - Particular / PJ - Pombaljardim / RV - Revalor / RT - Ribtejo / RP - Rádio / SH - Shade / SL - Silimpa</p>

<form method="get" style="margin-bottom:15px;">
    <label>Ano:</label>
    <select name="ano">
        <option value="">-- Todos --</option>
        <?php foreach ($anos as $a): ?>
            <option value="<?= $a ?>" <?= ($a == $ano) ? 'selected' : '' ?>><?= $a ?></option>
        <?php endforeach; ?>
    </select>

    <label>Mês:</label>
    <select name="mes">
        <option value="">-- Todos --</option>
        <?php for ($m = 1; $m <= 12; $m++):
            $val = str_pad($m, 2, '0', STR_PAD_LEFT);
        ?>
            <option value="<?= $val ?>" <?= ($val == $mes) ? 'selected' : '' ?>><?= $val ?></option>
        <?php endfor; ?>
    </select>

    <label>Grupo:</label>
    <select name="grupo">
        <option value="">-- Todos --</option>
        <?php foreach ($grupos as $g): ?>
            <option value="<?= htmlspecialchars($g) ?>" <?= ($g == $grupo) ? 'selected' : '' ?>><?= htmlspecialchars($g) ?></option>
        <?php endforeach; ?>
    </select>

    <label>Tipo:</label>
    <select name="tipo">
        <option value="">-- Todos --</option>
        <?php foreach ($tipos as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>" <?= ($t == $tipo) ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
        <?php endforeach; ?>
    </select>

    <label>Local:</label>
    <select name="local">
        <option value="">-- Todos --</option>
        <?php foreach ($locais as $l): ?>
            <option value="<?= htmlspecialchars($l) ?>" <?= ($l == $local) ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Filtrar</button>
</form>

<a href="../html/index.php" class="btn-voltar">Voltar</a>
<button onclick="exportTableToCSV('relatorio_consumos.csv')">Exportar CSV</button>

<?php if ($temFiltro): ?>
  <form method="get" action="relatorio_pdf_mensal.php" target="_blank" style="display:inline;">
    <input type="hidden" name="ano" value="<?= htmlspecialchars($ano) ?>">
    <input type="hidden" name="mes" value="<?= htmlspecialchars($mes) ?>">
    <input type="hidden" name="grupo" value="<?= htmlspecialchars($grupo) ?>">
    <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">
    <input type="hidden" name="local" value="<?= htmlspecialchars($local) ?>">
    <button type="submit">Gerar PDF</button>
  </form>
<?php endif; ?>



<?php if (empty($relatorio)): ?>
    <p><strong>Nenhum dado encontrado.</strong></p>
<?php else: ?>
    <?php foreach ($grupos_rel as $grupo_nome => $dados): ?>
        <h3>Grupo: <?= htmlspecialchars($grupo_nome) ?></h3>
        <table>
            <thead>
                <tr>
                    <th>Descrição</th>
                    <th>Matrícula</th>
                    <th>Empresa</th>
                    <th>Lts (mês)</th>
                    <th>
                        <?php
                        $tipo_coluna = in_array($dados[0]['tipo'], ['Máquina', 'Empilhador']) ? 'Hrs' : 'Kms';
                        echo "$tipo_coluna (Reg bomba)";
                        ?>
                    </th>
                    <th><?= $tipo_coluna ?> médios mês (3 meses)</th>
                    <th><?= $tipo_coluna ?> médios mês (12 meses)</th>
                    <th>L/<?= $tipo_coluna ?> (1 mês)</th>
                    <th>L/<?= $tipo_coluna ?> (3 meses)</th>
                    <th>L/<?= $tipo_coluna ?> (12 meses)</th>
                    <th>Total €</th>
                    <th>Notas</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($dados as $r): ?>
                <tr>
                    <td class="left"><?= htmlspecialchars($r['descricao']) ?></td>
                    <td class="left"><?= htmlspecialchars($r['matricula']) ?></td>
                    <td class="left"><?= htmlspecialchars($r['empresa']) ?></td>
                    <td class="right"><?= number_format($r['litros'], 2, ',', '.') ?></td>
                    <td class="right"><?= number_format($r['kms_hrs_mes'], 2, ',', '.') ?></td>
                    <td class="right"><?= $r['media_3meses'] ?></td>
                    <td class="right"><?= $r['media_12meses'] ?></td>
                    <td class="right"><?= is_numeric($r['consumo_1mes']) ? number_format($r['consumo_1mes'], 2, ',', '.') : '-' ?></td>
                    <td class="right"><?= $r['consumo_3meses'] ?></td>
                    <td class="right"><?= $r['consumo_12meses'] ?></td>
                    <td class="right"><?= number_format($r['total_valor'], 2, ',', '.') ?></td>
                    <td class="left"><?= htmlspecialchars($r['notas']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>

        </table>
    <?php endforeach; ?>
<?php endif; ?>

<div style="text-align: right; font-family: Arial, sans-serif; margin: 15px 0; color: #00693e;">
  <p style="margin: 4px 0; font-weight: 700; font-size: 1.1em;">
    Total Litros: <span style="color: #004d26;"><?= number_format($litros_total_geral, 2, ',', '.') ?> L</span>
  </p>
  <p style="margin: 4px 0; font-weight: 700; font-size: 1.1em;">
    Total €: <span style="color: #004d26;"><?= number_format($valor_total_geral, 2, ',', '.') ?> €</span>
  </p>
  <p style="margin: 4px 0; font-weight: 700; font-size: 1.1em;">
    Preço Médio por Litro (movimentos_stock): <span style="color: #004d26;"><?= number_format($preco_medio_comb, 3, ',', '.') ?> €/L</span>
  </p>
</div>


<script>
function downloadCSV(csv, filename) {
  const csvFile = new Blob([csv], { type: 'text/csv' });
  const downloadLink = document.createElement('a');
  downloadLink.download = filename;
  downloadLink.href = window.URL.createObjectURL(csvFile);
  downloadLink.style.display = 'none';
  document.body.appendChild(downloadLink);
  downloadLink.click();
  document.body.removeChild(downloadLink);
}

function exportTableToCSV(filename) {
  const rows = document.querySelectorAll('table tbody tr');
  const headers = document.querySelectorAll('table thead tr th');
  const csv = [];

  // Header row
  let headerRow = [];
  headers.forEach(th => headerRow.push('"' + th.innerText.trim().replace(/"/g, '""') + '"'));
  csv.push(headerRow.join(','));

  // Data rows
  rows.forEach(row => {
    const cols = row.querySelectorAll('td');
    let rowData = [];
    cols.forEach(td => rowData.push('"' + td.innerText.trim().replace(/"/g, '""') + '"'));
    csv.push(rowData.join(','));
  });

  downloadCSV(csv.join('\n'), filename);
}
</script>


</body>
</html>
