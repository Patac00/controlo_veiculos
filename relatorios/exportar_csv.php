<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");

$ano = $_GET['ano'] ?? '';
$mes = $_GET['mes'] ?? '';
$local = $_GET['local'] ?? '';
$grupo = $_GET['grupo'] ?? '';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="relatorio_abastecimentos.csv"');
$output = fopen('php://output', 'w');

// Cabeçalho
fputcsv($output, ['Matrícula', 'Tipo', 'Grupo', 'Ano', 'Mês', 'Local', 'Qtd Registos', 'Total KM/H', 'Total Litros', 'Valor c/ IVA (€)', 'Valor s/ IVA (€)'], ';');

$condicoes = [];
if ($ano !== '') $condicoes[] = "YEAR(data_abastecimento) = '$ano'";
if ($mes !== '') $condicoes[] = "MONTH(data_abastecimento) = '$mes'";
if ($local !== '') $condicoes[] = "veiculos.localizacao = '$local'";
if ($grupo !== '') $condicoes[] = "veiculos.grupo = '$grupo'";
$where_sql = count($condicoes) > 0 ? "WHERE " . implode(" AND ", $condicoes) : "";

// Dados bomba_redinha
$sql1 = "
    SELECT v.matricula, v.tipo, v.grupo, v.localizacao,
           COUNT(b.id) AS total_registos,
           SUM(b.odometro) AS total_km,
           SUM(b.litros) AS total_lts,
           SUM(b.total_pago) AS total_valor,
           SUM(b.total_pago / 1.23) AS total_siva,
           YEAR(b.data_abastecimento) AS ano,
           MONTH(b.data_abastecimento) AS mes
    FROM bomba_redinha b
    INNER JOIN veiculos v ON b.veiculo_id = v.id
    $where_sql
    GROUP BY v.matricula
";

// Dados abastecimentos manuais
$sql2 = "
    SELECT v.matricula, v.tipo, v.grupo, v.localizacao,
           COUNT(a.id) AS total_registos,
           SUM(a.km_registados) AS total_km,
           SUM(a.litros_abastecidos) AS total_lts,
           SUM(a.valor_pago) AS total_valor,
           SUM(a.valor_pago / 1.23) AS total_siva,
           YEAR(a.data_abastecimento) AS ano,
           MONTH(a.data_abastecimento) AS mes
    FROM abastecimentos a
    INNER JOIN veiculos v ON a.veiculo_id = v.id
    $where_sql
    GROUP BY v.matricula
";

$dados = [];

foreach ([$sql1, $sql2] as $sql) {
    $resultado = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($resultado)) {
        $matricula = $row['matricula'];
        if (!isset($dados[$matricula])) {
            $dados[$matricula] = [
                'matricula' => $matricula,
                'tipo' => $row['tipo'],
                'grupo' => $row['grupo'],
                'ano' => $row['ano'],
                'mes' => $row['mes'],
                'local' => $row['localizacao'],
                'total_registos' => 0,
                'total_km' => 0,
                'total_lts' => 0,
                'total_valor' => 0,
                'total_siva' => 0
            ];
        }
        $dados[$matricula]['total_registos'] += $row['total_registos'];
        $dados[$matricula]['total_km'] += $row['total_km'];
        $dados[$matricula]['total_lts'] += $row['total_lts'];
        $dados[$matricula]['total_valor'] += $row['total_valor'];
        $dados[$matricula]['total_siva'] += $row['total_siva'];
    }
}

// Totais gerais
$total_geral_lts = 0;
$total_geral_valor = 0;
$total_geral_siva = 0;

foreach ($dados as $d) {
    fputcsv($output, [
        $d['matricula'],
        $d['tipo'],
        $d['grupo'],
        $d['ano'],
        $d['mes'],
        $d['local'],
        $d['total_registos'],
        number_format($d['total_km'], 0, ',', '.'),
        number_format($d['total_lts'], 2, ',', '.'),
        number_format($d['total_valor'], 2, ',', '.'),
        number_format($d['total_siva'], 2, ',', '.')
    ], ';');

    $total_geral_lts += $d['total_lts'];
    $total_geral_valor += $d['total_valor'];
    $total_geral_siva += $d['total_siva'];
}

// Linha com os totais
fputcsv($output, ['TOTAL GERAL', '', '', '', '', '', '', '',
    number_format($total_geral_lts, 2, ',', '.'),
    number_format($total_geral_valor, 2, ',', '.'),
    number_format($total_geral_siva, 2, ',', '.')
], ';');

fclose($output);
exit;
 