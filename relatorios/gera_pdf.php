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
$pdf->SetTitle('Relatório de Abastecimentos por Veículo');
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage('L'); // Paisagem

if (empty($dados)) {
    $pdf->Write(0, 'Nenhum dado encontrado para os filtros aplicados.', '', 0, 'L', true, 0, false, false, 0);
    $pdf->Output('relatorio_abastecimentos.pdf', 'I');
    exit;
}

// Ordenar os dados
usort($dados, function($a, $b) {
    return $a['Grupo'] <=> $b['Grupo']
        ?: strcmp($a['matricula'], $b['matricula'])
        ?: $a['km'] <=> $b['km'];
});

// HTML da tabela
$html = '<h2 style="text-align:center; color:#004080;">Relatório de Abastecimentos</h2>';
$html .= '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; font-family: Arial, sans-serif;">';
$html .= '<thead>
    <tr style="background-color:#004080; color:#fff; font-weight: bold;">
        <th style="width:12%;">Matrícula</th>
        <th style="width:8%;">KM</th>
        <th style="width:18%;">Funcionário</th>
        <th style="width:12%;">Local</th>
        <th style="width:18%;">Requisição</th>
        <th style="width:8%; text-align:right;">Litros</th>
        <th style="width:12%; text-align:right;">Total (€)</th>
        <th style="width:12%; text-align:right;">Total s/IVA (€)</th>
    </tr>
</thead><tbody>';

$grupo_atual = null;
$subtotal = 0;
$total_geral = 0;

foreach ($dados as $d) {
    if ($d['Grupo'] !== $grupo_atual) {
        if ($grupo_atual !== null) {
            $html .= '<tr style="background-color:#e0f7ff; font-weight:bold;">
                <td colspan="6" style="text-align:right;">Total ' . htmlspecialchars($grupo_atual) . ':</td>
                <td style="text-align:right;">' . number_format($subtotal, 2, ',', '.') . ' €</td>
                <td></td>
            </tr>';
            $subtotal = 0;
        }
        $grupo_atual = $d['Grupo'];
        $html .= '<tr style="background-color:#cce6ff; font-weight:bold;">
            <td colspan="8">Grupo: ' . htmlspecialchars($grupo_atual) . '</td>
        </tr>';
    }

    $html .= '<tr>
        <td>' . htmlspecialchars($d['matricula']) . '</td>
        <td style="text-align:center;">' . htmlspecialchars($d['km']) . '</td>
        <td>' . htmlspecialchars($d['funcionario']) . '</td>
        <td>' . htmlspecialchars($d['local']) . '</td>
        <td>' . ($d['requisicao'] ? htmlspecialchars($d['requisicao']) : '-') . '</td>
        <td style="text-align:right;">' . number_format($d['litros'], 2, ',', '.') . '</td>
        <td style="text-align:right;">' . number_format($d['valor_total'], 2, ',', '.') . '</td>
        <td style="text-align:right;">' . number_format($d['valor_sem_iva'], 2, ',', '.') . '</td>
    </tr>';

    $subtotal += (float)$d['valor_total'];
    $total_geral += (float)$d['valor_total'];
}

// Último subtotal
if ($grupo_atual !== null) {
    $html .= '<tr style="background-color:#e0f7ff; font-weight:bold;">
        <td colspan="6" style="text-align:right;">Total ' . htmlspecialchars($grupo_atual) . ':</td>
        <td style="text-align:right;">' . number_format($subtotal, 2, ',', '.') . ' €</td>
        <td></td>
    </tr>';
}

// Total geral
$html .= '<tr style="background-color:#99ccff; font-weight:bold; font-size: 1.1em;">
    <td colspan="6" style="text-align:right;">Total Geral:</td>
    <td style="text-align:right;">' . number_format($total_geral, 2, ',', '.') . ' €</td>
    <td></td>
</tr>';

$html .= '</tbody></table>';

// Escrever no PDF
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('relatorio_abastecimentos.pdf', 'I');
