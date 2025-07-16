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
if ($inicio && $fim) $where[] = "b.data BETWEEN '$inicio' AND '$fim'";
if ($grupo) $where[] = "v.Grupo = '$grupo'";
if ($local) $where[] = "p.local = '$local'";
$where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Query bomba
$sql_bomba = "
    SELECT v.matricula, b.odometro AS km, b.motorista AS funcionario, p.local,
           '' AS requisicao, b.quantidade AS litros, 0 AS valor_total,
           0 AS valor_sem_iva, v.Grupo
    FROM bomba b
    LEFT JOIN veiculos v ON b.id_veiculo = v.id_veiculo
    LEFT JOIN lista_postos p ON b.id_posto = p.id_posto
    $where_sql
    ORDER BY v.matricula, b.odometro ASC
";

$res = mysqli_query($con, $sql_bomba);
while ($row = mysqli_fetch_assoc($res)) {
    $dados[] = $row;
}

// Totais por matrícula
$totais_veiculos = [];
$total_geral = 0;

foreach ($dados as $linha) {
    $mat = $linha['matricula'];
    $valor = (float)$linha['valor_total'];

    if (!isset($totais_veiculos[$mat])) {
        $totais_veiculos[$mat] = 0;
    }
    $totais_veiculos[$mat] += $valor;
    $total_geral += $valor;
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
            padding: 8px 10px;
            text-align: left;
        }
        th {
            background-color: #004080;
            color: white;
        }
        tr.group-header td {
            background: #cce6ff;
            font-weight: bold;
            text-align: left;
            font-size: 1.1em;
        }
        tr.total-row {
            background-color: #ddeeff;
            font-weight: bold;
        }
        tr.total-geral {
            background-color: #99ccff;
            font-weight: bold;
            font-size: 1.1em;
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
        }
        button:hover {
            background-color: #003366;
        }
        .btn-pdf {
            margin-top: 15px;
            display: inline-block;
        }
        .form-wrapper {
            margin-bottom: 10px;
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
            <option value="<?= $l ?>" <?= ($l == $local) ? 'selected' : '' ?>><?= $l ?></option>
        <?php endforeach; ?>
    </select>

    <label>Grupo:</label>
    <select name="grupo">
        <option value="">-- Todos --</option>
        <?php foreach ($grupos as $g): ?>
            <option value="<?= $g ?>" <?= ($g == $grupo) ? 'selected' : '' ?>><?= $g ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Filtrar</button>
</form>
</div>

<div class="form-wrapper">
<form method="get">
    <!-- Manter os filtros preenchidos -->
    <input type="hidden" name="ano" value="<?= htmlspecialchars($ano) ?>">
    <input type="hidden" name="mes" value="<?= htmlspecialchars($mes) ?>">
    <input type="hidden" name="local" value="<?= htmlspecialchars($local) ?>">
    <input type="hidden" name="grupo" value="<?= htmlspecialchars($grupo) ?>">

    <button type="submit">Aplicar</button>
</form>
</div>

<!-- Botão PDF -->
<form method="get" action="gera_pdf.php" target="_blank" class="btn-pdf">
    <input type="hidden" name="ano" value="<?= htmlspecialchars($ano) ?>">
    <input type="hidden" name="mes" value="<?= htmlspecialchars($mes) ?>">
    <input type="hidden" name="local" value="<?= htmlspecialchars($local) ?>">
    <input type="hidden" name="grupo" value="<?= htmlspecialchars($grupo) ?>">
    <button type="submit">Gerar PDF</button>
</form>

<?php if ($dados): ?>
<table>
    <thead>
        <tr>
            <th>Matrícula</th>
            <th>KM</th>
            <th>Funcionário</th>
            <th>Local</th>
            <th>Requisição</th>
            <th>Lts</th>
            <th>Montante (€)</th>
            <th>Montante s/IVA (€)</th>
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
                <td colspan="6" style="text-align:right;">Total <?= htmlspecialchars($matricula_atual) ?>:</td>
                <td colspan="2"><?= number_format($totais_veiculos[$matricula_atual], 2, ',', '.') ?> €</td>
            </tr>
            <?php endif;
            $matricula_atual = $d['matricula'];
            ?>
            <tr class="group-header">
                <td colspan="8">Veículo: <?= htmlspecialchars($matricula_atual ?? '-') ?></td>
            </tr>
    <?php endif; ?>

        <tr>
            <td><?= htmlspecialchars($d['matricula'] ?? '-') ?></td>
            <td><?= htmlspecialchars($d['km'] ?? '-') ?></td>
            <td><?= htmlspecialchars($d['funcionario'] ?? '-') ?></td>
            <td><?= htmlspecialchars($d['local'] ?? '-') ?></td>
            <td><?= htmlspecialchars($d['requisicao'] ?? '-') ?></td>
            <td><?= htmlspecialchars($d['litros'] ?? '-') ?></td>
            <td><?= (isset($d['valor_total']) && $d['valor_total'] > 0) ? number_format($d['valor_total'], 2, ',', '.') : '-' ?></td>
            <td><?= (isset($d['valor_sem_iva']) && $d['valor_sem_iva'] > 0) ? number_format($d['valor_sem_iva'], 2, ',', '.') : '-' ?></td>
        </tr>
    <?php endforeach; ?>

        <!-- Total último veículo -->
        <tr class="total-row">
            <td colspan="6" style="text-align:right;">Total <?= htmlspecialchars($matricula_atual) ?>:</td>
            <td colspan="2"><?= number_format($totais_veiculos[$matricula_atual], 2, ',', '.') ?> €</td>
        </tr>
        <!-- Total geral -->
        <tr class="total-geral">
            <td colspan="6" style="text-align:right;">Total Geral:</td>
            <td colspan="2"><?= number_format($total_geral, 2, ',', '.') ?> €</td>
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
