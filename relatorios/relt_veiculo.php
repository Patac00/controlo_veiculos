<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");

// Obter opções distintas
$anos = [];
$grupos = [];
$locais = [];

$resAno = mysqli_query($con, "SELECT DISTINCT YEAR(data) AS ano FROM bomba ORDER BY ano DESC");
while ($row = mysqli_fetch_assoc($resAno)) $anos[] = $row['ano'];

$resGrupo = mysqli_query($con, "SELECT DISTINCT Grupo FROM veiculos ORDER BY Grupo ASC");
while ($row = mysqli_fetch_assoc($resGrupo)) $grupos[] = $row['Grupo'];

$resLocal = mysqli_query($con, "SELECT DISTINCT local FROM lista_postos ORDER BY local ASC");
while ($row = mysqli_fetch_assoc($resLocal)) $locais[] = $row['local'];

// Filtros opcionais
$ano = $_GET['ano'] ?? '';
$mes = $_GET['mes'] ?? '';
$local = $_GET['local'] ?? '';
$grupo = $_GET['grupo'] ?? '';

$dados = [];

$inicio = ($ano && $mes) ? "$ano-$mes-01" : null;
$fim = $inicio ? date("Y-m-t", strtotime($inicio)) : null;

// Montar condições da query
$where = [];
$where_abast = [];
if ($inicio && $fim) {
    $where[] = "b.data BETWEEN '$inicio' AND '$fim'";
    $where_abast[] = "a.data_abastecimento BETWEEN '$inicio' AND '$fim'";
}
if ($grupo) {
    $where[] = "v.Grupo = '$grupo'";
    $where_abast[] = "v.Grupo = '$grupo'";
}
if ($local) {
    $where[] = "p.local = '$local'";
    $where_abast[] = "p.local = '$local'";
}
$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";
$where_abast_sql = $where_abast ? "WHERE " . implode(" AND ", $where_abast) : "";

// Query bomba
$sql_bomba = "
    SELECT 
        e.nome AS empresa,
        v.Tipo,
        v.matricula,
        v.Descricao,
        b.odometro AS km,
        COALESCE(m.nome, b.motorista) AS funcionario,
        p.local,
        '' AS requisicao,
        b.quantidade AS litros,
        0 AS valor_total,
        0 AS valor_sem_iva,
        v.Grupo
    FROM bomba b
    LEFT JOIN veiculos v ON b.id_veiculo = v.id_veiculo
    LEFT JOIN empresas e ON v.empresa_atual_id = e.empresa_id
    LEFT JOIN lista_postos p ON b.id_posto = p.id_posto
    LEFT JOIN motoristas m ON b.motorista = m.codigo_bomba
    $where_sql
";

// Query abastecimentos manuais
$sql_abastecimentos = "
    SELECT 
        e.nome AS empresa,
        v.Tipo,
        v.matricula,
        v.Descricao,
        a.km_registados AS km,
        u.nome AS funcionario,
        p.local,
        '' AS requisicao,
        a.litros,
        a.valor_total,
        ROUND(a.valor_total / 1.23, 2) AS valor_sem_iva,
        v.Grupo
    FROM abastecimentos a
    JOIN veiculos v ON a.id_veiculo = v.id_veiculo
    LEFT JOIN empresas e ON v.empresa_atual_id = e.empresa_id
    JOIN utilizadores u ON a.id_utilizador = u.id_utilizador
    LEFT JOIN lista_postos p ON a.id_posto = p.id_posto
    $where_abast_sql
";

$res1 = mysqli_query($con, $sql_bomba);
$res2 = mysqli_query($con, $sql_abastecimentos);

// Juntar resultados
while ($row = mysqli_fetch_assoc($res1)) $dados[] = $row;
while ($row = mysqli_fetch_assoc($res2)) $dados[] = $row;

// Ordenar por matrícula
usort($dados, function($a, $b) {
    $matA = $a['matricula'] ?? '';
    $matB = $b['matricula'] ?? '';
    return strcmp($matA, $matB);
});

// Totais por matrícula
$totais_veiculos = [];
$total_lts = 0;
$total_montante = 0;
$total_montante_siva = 0;

foreach ($dados as $linha) {
    $mat = $linha['matricula'];
    if (!isset($totais_veiculos[$mat])) {
        $totais_veiculos[$mat] = ['lts' => 0, 'valor' => 0, 'valor_siva' => 0];
    }

    $totais_veiculos[$mat]['lts'] += (float)$linha['litros'];
    $totais_veiculos[$mat]['valor'] += (float)$linha['valor_total'];
    $totais_veiculos[$mat]['valor_siva'] += (float)$linha['valor_sem_iva'];

    $total_lts += (float)$linha['litros'];
    $total_montante += (float)$linha['valor_total'];
    $total_montante_siva += (float)$linha['valor_sem_iva'];
}

// Exportar CSV
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=relatorio_veiculos.csv');

    $output = fopen('php://output', 'w');

    // Cabeçalho
    fputcsv($output, [
        'Empresa', 'Tipo', 'Matrícula', 'Descrição', 'KM',
        'Funcionário', 'Local', 'Requisição', 'LTS',
        'Montante (€)', 'Montante (€) s/iva'
    ], ';');

    foreach ($dados as $d) {
        $litros = number_format($d['litros'], 2, ',', '.');
        $valor_total = ($d['valor_total'] > 0) ? number_format($d['valor_total'], 2, ',', '.') : '-';
        $valor_siva = ($d['valor_sem_iva'] > 0) ? number_format($d['valor_sem_iva'], 2, ',', '.') : '-';

        $linha = [
            $d['empresa'] ?? '-',
            $d['Tipo'] ?? '-',
            $d['matricula'] ?? '-',
            $d['Descricao'] ?? '-',
            $d['km'] ?? '-',
            $d['funcionario'] ?? '-',
            $d['local'] ?? '-',
            trim($d['requisicao']) !== '' ? $d['requisicao'] : '(em branco)',
            $litros,
            $valor_total,
            $valor_siva
        ];

        fputcsv($output, $linha, ';');
    }

    fclose($output);
    exit();
}
?>


<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Relatório por Veículo</title>
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
        }
        th, td {
            border: 1px solid #aaccee;
            padding: 8px 6px;
            text-align: left;
            font-size: 12px;
        }
        th {
            background-color: #004080;
            color: white;
            text-align: center;
        }
        td.center {
            text-align: center;
        }
        td.right {
            text-align: right;
        }
        tr.group-header td {
            background: #cce6ff;
            font-weight: bold;
            font-size: 14px;
        }
        tr.total-row {
            background-color: #ddeeff;
            font-weight: bold;
        }
        tr.total-geral {
            background-color: #99ccff;
            font-weight: bold;
            font-size: 16px;
        }
        form label {
            margin-right: 8px;
            font-weight: bold;
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
            margin-bottom: 10px; /* Todos os botões */
        }
        button:hover {
            background-color: #424446ff;
        }
        .botoes-wrapper {
            overflow: hidden; /* conter floats */
            margin-bottom: 10px;
        }
        .btn-voltar {
            float: left;
            margin-bottom: 0px;
            background-color: #e41212ff;
        }
        .btn-pdf {
            float: right;
            margin-bottom: 0px;
            margin-left: 10px;
        }
        .btn-csv {
            float: right;
            margin-bottom: 0px;
        }
    </style>
</head>
<body>
<h2>Relatório de Abastecimentos por Veículo</h2>

<div class="form-wrapper">
<form method="get">
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
            $val = str_pad($m, 2, "0", STR_PAD_LEFT);
        ?>
            <option value="<?= $val ?>" <?= ($val == $mes) ? 'selected' : '' ?>><?= $val ?></option>
        <?php endfor; ?>
    </select>

    <label>Local:</label>
    <select name="local">
        <option value="">-- Todos --</option>
        <?php foreach ($locais as $l): ?>
            <option value="<?= htmlspecialchars($l) ?>" <?= ($l == $local) ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
        <?php endforeach; ?>
    </select>

    <label>Grupo:</label>
    <select name="grupo">
        <option value="">-- Todos --</option>
        <?php foreach ($grupos as $g): ?>
            <option value="<?= htmlspecialchars($g) ?>" <?= ($g == $grupo) ? 'selected' : '' ?>><?= htmlspecialchars($g) ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Filtrar</button>
</form>
</div>

<!-- Botões -->
<div class="botoes-wrapper">
    <button class="btn-voltar" onclick="location.href='../html/index.php'">Voltar</button>

    <form method="get" action="gera_pdf.php" target="_blank" class="btn-pdf">
        <input type="hidden" name="ano" value="<?= htmlspecialchars($ano) ?>">
        <input type="hidden" name="mes" value="<?= htmlspecialchars($mes) ?>">
        <input type="hidden" name="local" value="<?= htmlspecialchars($local) ?>">
        <input type="hidden" name="grupo" value="<?= htmlspecialchars($grupo) ?>">
        <button type="submit">Gerar PDF</button>
    </form>

    <form method="get" class="btn-csv" style="float:right; margin-left:10px;">
        <input type="hidden" name="ano" value="<?= htmlspecialchars($ano) ?>">
        <input type="hidden" name="mes" value="<?= htmlspecialchars($mes) ?>">
        <input type="hidden" name="local" value="<?= htmlspecialchars($local) ?>">
        <input type="hidden" name="grupo" value="<?= htmlspecialchars($grupo) ?>">
        <input type="hidden" name="export_csv" value="1">
        <button type="submit" style="background-color:#28a745;">Exportar CSV</button>
    </form>
</div>


<?php if ($dados): ?>
<table>
    <thead>
        <tr>
            <th>Empresa</th>
            <th>Tipo</th>
            <th>Matrícula</th>
            <th>Descrição</th>
            <th>KM</th>
            <th>Funcionário</th>
            <th>Local</th>
            <th>Requisição</th>
            <th>LTS</th>
            <th>Montante (€)</th>
            <th>Montante (€) s/iva</th>
        </tr>
    </thead>
    <tbody>
    <?php
    $matricula_atual = '';
    foreach ($dados as $d):
        if (empty($d['matricula'])) continue;

        if ($d['matricula'] !== $matricula_atual):
            if ($matricula_atual != ''): ?>
            <tr class="total-row">
                <td colspan="8" style="text-align:right;">Total <?= htmlspecialchars($matricula_atual) ?>:</td>
                <td class="right"><?= number_format($totais_veiculos[$matricula_atual]['lts'], 2, ',', '.') ?></td>
                <td class="right"><?= number_format($totais_veiculos[$matricula_atual]['valor'], 2, ',', '.') ?> €</td>
                <td class="right"><?= number_format($totais_veiculos[$matricula_atual]['valor_siva'], 2, ',', '.') ?> €</td>
            </tr>
            <?php endif;
            $matricula_atual = $d['matricula'];
            ?>
            <tr class="group-header">
                <td colspan="11">Veículo: <?= htmlspecialchars($matricula_atual ?? '-') ?></td>
            </tr>
    <?php endif; ?>

        <tr>
            <td><?= htmlspecialchars($d['empresa'] ?? '-') ?></td>
            <td><?= htmlspecialchars($d['Tipo'] ?? '-') ?></td>
            <td><?= htmlspecialchars($d['matricula'] ?? '-') ?></td>
            <td><?= htmlspecialchars($d['Descricao'] ?? '-') ?></td>
            <td class="center"><?= htmlspecialchars($d['km'] ?? '-') ?></td>
            <td><?= htmlspecialchars($d['funcionario'] ?? '-') ?></td>
            <td><?= htmlspecialchars($d['local'] ?? '-') ?></td>
            <td><?= trim($d['requisicao']) !== '' ? htmlspecialchars($d['requisicao']) : '(em branco)' ?></td>
            <td class="right"><?= number_format($d['litros'], 2, ',', '.') ?></td>
            <td class="right"><?= ($d['valor_total'] > 0) ? number_format($d['valor_total'], 2, ',', '.') : '-' ?></td>
            <td class="right"><?= ($d['valor_sem_iva'] > 0) ? number_format($d['valor_sem_iva'], 2, ',', '.') : '-' ?></td>
        </tr>
    <?php endforeach; ?>

        <!-- Total último veículo -->
        <tr class="total-row">
            <td colspan="8" style="text-align:right;">Total <?= htmlspecialchars($matricula_atual) ?>:</td>
            <td class="right"><?= number_format($totais_veiculos[$matricula_atual]['lts'], 2, ',', '.') ?></td>
            <td class="right"><?= number_format($totais_veiculos[$matricula_atual]['valor'], 2, ',', '.') ?> €</td>
            <td class="right"><?= number_format($totais_veiculos[$matricula_atual]['valor_siva'], 2, ',', '.') ?> €</td>
        </tr>
        <!-- Total geral -->
        <tr class="total-geral">
            <td colspan="8" style="text-align:right;">Total Geral:</td>
            <td class="right"><?= number_format($total_lts, 2, ',', '.') ?></td>
            <td class="right"><?= number_format($total_montante, 2, ',', '.') ?> €</td>
            <td class="right"><?= number_format($total_montante_siva, 2, ',', '.') ?> €</td>
        </tr>
    </tbody>
</table>
<?php elseif ($_GET): ?>
<p><strong>Nenhum dado encontrado.</strong></p>
<?php endif; ?>

<br>
<a href="../html/index.php">Voltar ao Início</a>
</body>
</html>
