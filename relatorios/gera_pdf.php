<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");
require_once('../tcpdf/tcpdf.php');

// Filtros recebidos por GET
$ano = $_GET['ano'] ?? '';
$mes = $_GET['mes'] ?? '';
$local = $_GET['local'] ?? '';
$grupo = $_GET['grupo'] ?? '';
$mostrar_valores = $_GET['mostrar_valores'] ?? '0';

$inicio = ($ano && $mes) ? "$ano-$mes-01" : null;
$fim = $inicio ? date("Y-m-t", strtotime($inicio)) : null;

// Montar condições SQL
$where = [];
if ($inicio && $fim) $where[] = "a.data_abastecimento BETWEEN '$inicio' AND '$fim'";
if ($grupo) $where[] = "v.Grupo = '$grupo'";
if ($local) $where[] = "p.local = '$local'";
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Parte 1: abastecimentos
$sql1 = "
    SELECT v.matricula, a.km_registados AS km, u.nome AS funcionario, p.local, 
           a.observacoes AS requisicao, a.litros, a.valor_total, 
           ROUND(a.valor_total / 1.23, 2) AS valor_sem_iva, v.Grupo
    FROM abastecimentos a
    JOIN veiculos v ON a.id_veiculo = v.id_veiculo
    JOIN utilizadores u ON a.id_utilizador = u.id_utilizador
    JOIN lista_postos p ON a.id_posto = p.id_posto
    $where_sql
";

// Parte 2: bomba_redinha
$where_redinha = [];
if ($inicio && $fim) $where_redinha[] = "b.data BETWEEN '$inicio' AND '$fim'";
if ($grupo) $where_redinha[] = "v.Grupo = '$grupo'";
$where_sql2 = $where_redinha ? 'WHERE ' . implode(' AND ', $where_redinha) : '';

$sql2 = "
    SELECT v.matricula, b.odometro AS km, b.motorista AS funcionario, 'Redinha' AS local,
           '' AS requisicao, b.quantidade AS litros, 0 AS valor_total, 
           0 AS valor_sem_iva, v.Grupo
    FROM bomba_redinha b
    JOIN veiculos v ON b.id_veiculo = v.id_veiculo
    $where_sql2
";

// Executar ambas as queries com UNION
$sql = "$sql1 UNION ALL $sql2 ORDER BY Grupo, matricula, km ASC";

$res = mysqli_query($con, $sql);

$dados = [];
while ($row = mysqli_fetch_assoc($res)) {
    if ($mostrar_valores === '1' && ((float)$row['valor_total'] <= 0)) continue;
    $dados[] = $row;
}

// Iniciar PDF
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Ambipombal');
$pdf->SetTitle('Relatório de Abastecimentos por Veiculo');
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage('L');  // Aqui está em paisagem


if (empty($dados)) {
    $pdf->Write(0, 'Nenhum dado encontrado para os filtros aplicados.', '', 0, 'L', true, 0, false, false, 0);
    $pdf->Output('relatorio_abastecimentos.pdf', 'I');
    exit;
}

// Criar HTML
$html = '<h2 style="text-align:center;">Relatório de Abastecimentos</h2>';
$html .= '<table border="1" cellpadding="4" cellspacing="0">
<thead>
    <tr style="background-color:#004080; color:white;">
        <th>Grupo</th>
        <th>Matrícula</th>
        <th>KM</th>
        <th>Funcionário</th>
        <th>Local</th>
        <th>Requisição</th>
        <th>Litros</th>
        <th>Total (€)</th>
        <th>Total s/IVA (€)</th>
    </tr>
</thead><tbody>';

$totais_grupo = [];
$total_geral = 0;

foreach ($dados as $d) {
    $grupo = $d['Grupo'];
    if (!isset($totais_grupo[$grupo])) $totais_grupo[$grupo] = 0;
    $totais_grupo[$grupo] += (float)$d['valor_total'];
    $total_geral += (float)$d['valor_total'];

    $html .= "<tr>
        <td>{$d['Grupo']}</td>
        <td>{$d['matricula']}</td>
        <td>{$d['km']}</td>
        <td>{$d['funcionario']}</td>
        <td>{$d['local']}</td>
        <td>" . ($d['requisicao'] ?: '-') . "</td>
        <td>{$d['litros']}</td>
        <td>" . number_format($d['valor_total'], 2, ',', '.') . "</td>
        <td>" . number_format($d['valor_sem_iva'], 2, ',', '.') . "</td>
    </tr>";
}

// Totais por grupo
foreach ($totais_grupo as $grupo => $total) {
    $html .= "<tr style='font-weight:bold;background:#e0f7ff;'>
        <td colspan='7' align='right'>Total {$grupo}:</td>
        <td colspan='2'>" . number_format($total, 2, ',', '.') . " €</td>
    </tr>";
}

// Total geral
$html .= "<tr style='font-weight:bold;background:#cceeff;'>
    <td colspan='7' align='right'>Total Geral:</td>
    <td colspan='2'>" . number_format($total_geral, 2, ',', '.') . " €</td>
</tr>";

$html .= '</tbody></table>';

// Escrever HTML no PDF
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('relatorio_abastecimentos.pdf', 'I');
