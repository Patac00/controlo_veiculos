<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");

// --- Obter opções para filtros ---
$anos = [];
$grupos = [];
$locais = [];
$tipos = [];

// Anos disponíveis
$resAno = mysqli_query($con, "SELECT DISTINCT YEAR(data) AS ano FROM bomba ORDER BY ano DESC");
while ($row = mysqli_fetch_assoc($resAno)) {
    $anos[] = $row['ano'];
}

// Grupos
$resGrupo = mysqli_query($con, "SELECT DISTINCT nome FROM grupos ORDER BY nome ASC");
while ($row = mysqli_fetch_assoc($resGrupo)) {
    $grupos[] = $row['nome'];
}

// Locais
$resLocal = mysqli_query($con, "SELECT DISTINCT local FROM lista_postos ORDER BY local ASC");
while ($row = mysqli_fetch_assoc($resLocal)) {
    $locais[] = $row['local'];
}

// Tipos
$resTipo = mysqli_query($con, "SELECT DISTINCT tipo FROM grupos ORDER BY tipo ASC");
while ($row = mysqli_fetch_assoc($resTipo)) {
    $tipos[] = $row['tipo'];
}

// --- Receber filtros (com valores padrão atuais) ---
$ano = $_GET['ano'] ?? date('Y');
$mes = $_GET['mes'] ?? date('m');
$local = $_GET['local'] ?? '';
$grupo = $_GET['grupo'] ?? '';
$tipo = $_GET['tipo'] ?? '';

// --- Calcular datas do mês para filtro ---
$inicio = "$ano-$mes-01";
$fim = date("Y-m-t", strtotime($inicio));

// --- Preparar query veículos com filtros ---
$where_veic = [];
if ($grupo) {
    $where_veic[] = "v.Grupo = '" . mysqli_real_escape_string($con, $grupo) . "'";
}
if ($tipo) {
    $where_veic[] = "g.tipo = '" . mysqli_real_escape_string($con, $tipo) . "'";
}
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

// --- Montar relatório ---
$relatorio = [];

while ($v = mysqli_fetch_assoc($res_veiculos)) {
    $id_veic = (int)$v['id_veiculo'];

    // Litros - bomba
    $sql_litros_bomba = "
        SELECT SUM(quantidade) AS total FROM bomba b
        LEFT JOIN lista_postos p ON b.id_posto = p.id_posto
        WHERE id_veiculo = $id_veic AND data BETWEEN '$inicio' AND '$fim'
    ";
    if ($local) {
        $sql_litros_bomba .= " AND p.local = '" . mysqli_real_escape_string($con, $local) . "'";
    }
    $res_bomba = mysqli_query($con, $sql_litros_bomba);
    $litros_bomba = (float)(mysqli_fetch_assoc($res_bomba)['total'] ?? 0);

    // Litros - manual
    $sql_litros_manual = "
        SELECT SUM(litros) AS total FROM abastecimentos a
        LEFT JOIN lista_postos p ON a.id_posto = p.id_posto
        WHERE id_veiculo = $id_veic AND data_abastecimento BETWEEN '$inicio' AND '$fim'
    ";
    if ($local) {
        $sql_litros_manual .= " AND p.local = '" . mysqli_real_escape_string($con, $local) . "'";
    }
    $res_manual = mysqli_query($con, $sql_litros_manual);
    $litros_manual = (float)(mysqli_fetch_assoc($res_manual)['total'] ?? 0);

    $litros_total = $litros_bomba + $litros_manual;

    // Kms/Horas no mês (registo bomba)
    $sql_min = "SELECT MIN(odometro) AS min_km FROM bomba WHERE id_veiculo = $id_veic AND data BETWEEN '$inicio' AND '$fim'";
    $sql_max = "SELECT MAX(odometro) AS max_km FROM bomba WHERE id_veiculo = $id_veic AND data BETWEEN '$inicio' AND '$fim'";
    $min_km = (float)(mysqli_fetch_assoc(mysqli_query($con, $sql_min))['min_km'] ?? 0);
    $max_km = (float)(mysqli_fetch_assoc(mysqli_query($con, $sql_max))['max_km'] ?? 0);
    $kms_hrs_mes = max(0, $max_km - $min_km);

    // Consumo 1 mês
    $consumo_1mes = '-';
    if ($litros_total > 0 && $kms_hrs_mes > 0) {
        $consumo_1mes = ($v['tipo'] == 'Máquina' || $v['tipo'] == 'Empilhador')
            ? round($litros_total / $kms_hrs_mes, 2) // litros por hora
            : round(($litros_total / $kms_hrs_mes) * 100, 2); // litros por 100 km
    }

    $relatorio[] = [
        'descricao' => $v['Descricao'],
        'matricula' => $v['matricula'],
        'empresa' => $v['empresa'],
        'grupo' => $v['Grupo'],
        'tipo' => $v['tipo'],
        'litros' => $litros_total,
        'kms_hrs_mes' => $kms_hrs_mes,
        'media_3meses' => '-', // Pode implementar depois
        'media_12meses' => '-', // Pode implementar depois
        'consumo_1mes' => $consumo_1mes,
        'consumo_3meses' => '-', // Pode implementar depois
        'consumo_12meses' => '-', // Pode implementar depois
        'notas' => '',
    ];
}

// --- Agrupar relatório por grupo ---
$grupos_rel = [];
foreach ($relatorio as $item) {
    $grp = $item['grupo'] ?: 'Sem Grupo';
    if (!isset($grupos_rel[$grp])) $grupos_rel[$grp] = [];
    $grupos_rel[$grp][] = $item;
}

// --- Traduzir nome do mês para português ---
$data = DateTime::createFromFormat('Y-m', "$ano-$mes");
if (!$data) $data = new DateTime();

$nome_mes = $data->format('F \d\e Y');
$traducoes = [
    'January' => 'Janeiro', 'February' => 'Fevereiro', 'March' => 'Março',
    'April' => 'Abril', 'May' => 'Maio', 'June' => 'Junho',
    'July' => 'Julho', 'August' => 'Agosto', 'September' => 'Setembro',
    'October' => 'Outubro', 'November' => 'Novembro', 'December' => 'Dezembro',
];
$nome_mes_pt = strtr($nome_mes, $traducoes);
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
        }
        th, td {
            border: 1px solid #aaccee;
            padding: 6px 8px;
            text-align: center;
        }
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
    </style>
</head>
<body>

<h2>Relatório Mensal de Consumos: <?= $nome_mes_pt ?></h2>

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

    <label>Tipo:</label>
    <select name="tipo">
        <option value="">-- Todos --</option>
        <?php foreach ($tipos as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>" <?= ($t == $tipo) ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit">Filtrar</button>
</form>

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
                        // Detectar se é hora ou km para mostrar na tabela
                        $tipo_coluna = in_array($dados[0]['tipo'], ['Máquina', 'Empilhador']) ? 'Hrs' : 'Kms';
                        echo "$tipo_coluna (Reg bomba)";
                        ?>
                    </th>
                    <th><?= $tipo_coluna ?> médios mês (3 meses)</th>
                    <th><?= $tipo_coluna ?> médios mês (12 meses)</th>
                    <th>L/<?= $tipo_coluna ?> (1 mês)</th>
                    <th>/<?= $tipo_coluna ?> (3 meses)</th>
                    <th>/<?= $tipo_coluna ?> (12 meses)</th>
                    <th>Notas</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dados as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['descricao']) ?></td>
                        <td><?= htmlspecialchars($r['matricula']) ?></td>
                        <td><?= htmlspecialchars($r['empresa']) ?></td>
                        <td class="right"><?= number_format($r['litros'], 2, ',', '.') ?></td>
                        <td class="right"><?= number_format($r['kms_hrs_mes'], 2, ',', '.') ?></td>
                        <td class="right"><?= $r['media_3meses'] ?></td>
                        <td class="right"><?= $r['media_12meses'] ?></td>
                        <td class="right"><?= is_numeric($r['consumo_1mes']) ? number_format($r['consumo_1mes'], 2, ',', '.') : '-' ?></td>
                        <td class="right"><?= $r['consumo_3meses'] ?></td>
                        <td class="right"><?= $r['consumo_12meses'] ?></td>
                        <td><?= htmlspecialchars($r['notas']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
<?php endif; ?>

<h5>Legenda:</h5>
<p>AA - Amanhece & Acontece / AP - Ambipombal / BT - Btermec / CV - Civtrhi / FR - Forças Robustas / O - Outros / PT - Particular / PJ - Pombaljardim / RV - Revalor / RT - Ribtejo / RP - Rádio / SH - Shade / SL - Silimpa</p>

</body>
</html>
