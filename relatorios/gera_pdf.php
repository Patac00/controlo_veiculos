<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");
require_once('../tcpdf/tcpdf.php');

$ano = $_GET['ano'] ?? '';
$mes = $_GET['mes'] ?? '';
$local = $_GET['local'] ?? '';
$grupo = $_GET['grupo'] ?? '';

$inicio = ($ano && $mes) ? "$ano-$mes-01" : null;
$fim = $inicio ? date("Y-m-t", strtotime($inicio)) : null;

$where_abast = [];
if ($inicio && $fim) $where_abast[] = "a.data_abastecimento BETWEEN '$inicio' AND '$fim'";
if ($grupo) $where_abast[] = "v.Grupo = '$grupo'";
if ($local) $where_abast[] = "p.local = '$local'";
$where_sql_abast = $where_abast ? 'WHERE ' . implode(' AND ', $where_abast) : '';

$where_bomba = [];
if ($inicio && $fim) $where_bomba[] = "b.data BETWEEN '$inicio' AND '$fim'";
if ($grupo) $where_bomba[] = "v.Grupo = '$grupo'";
if ($local) $where_bomba[] = "p.local = '$local'";
$where_sql_bomba = $where_bomba ? 'WHERE ' . implode(' AND ', $where_bomba) : '';

$sql_abast = "
    SELECT 
        e.nome AS empresa,
        v.Tipo,
        v.matricula,
        v.Descricao,
        a.km_registados AS km,
        u.nome AS funcionario,
        p.local,
        a.observacoes AS requisicao,
        a.litros,
        a.valor_total,
        ROUND(a.valor_total / 1.23, 2) AS valor_sem_iva,
        v.Grupo
    FROM abastecimentos a
    JOIN veiculos v ON a.id_veiculo = v.id_veiculo
    JOIN empresas e ON v.empresa_atual_id = e.id_empresa
    JOIN utilizadores u ON a.id_utilizador = u.id_utilizador
    JOIN lista_postos p ON a.id_posto = p.id_posto
    $where_sql_abast
";


$sql_bomba = "
    SELECT 
        e.nome AS empresa,
        v.Tipo,
        v.matricula,
        v.Descricao,
        b.odometro AS km,
        m.nome AS funcionario,
        p.local,
        '' AS requisicao,
        b.quantidade AS litros,
        0 AS valor_total,
        0 AS valor_sem_iva,
        v.Grupo
    FROM bomba b
    JOIN veiculos v ON b.id_veiculo = v.id_veiculo
    JOIN empresas e ON v.empresa_atual_id = e.id_empresa
    JOIN lista_postos p ON b.id_posto = p.id_posto
    LEFT JOIN motoristas m ON b.motorista = m.codigo_bomba
    $where_sql_bomba
";



$sql = "$sql_abast UNION ALL $sql_bomba ORDER BY matricula, km ASC";

$res = mysqli_query($con, $sql);
$dados = [];
while ($row = mysqli_fetch_assoc($res)) {
    $dados[] = $row;
}

$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Ambipombal');
$pdf->SetTitle('Relatório de Abastecimentos por Veículo');
$pdf->SetMargins(10, 15, 10);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage('L');
$pdf->SetFont('helvetica', '', 7);

if (empty($dados)) {
    $pdf->Write(0, 'Nenhum dado encontrado para os filtros aplicados.', '', 0, 'L', true, 0, false, false, 0);
    $pdf->Output('relatorio_abastecimentos.pdf', 'I');
    exit;
}

$colunas = [
    ['titulo' => 'Empresa', 'width' => '9%', 'align' => 'left', 'pad' => 'padding-left:5px;'],
    ['titulo' => 'Tipo', 'width' => '6%', 'align' => 'left', 'pad' => 'padding-left:5px;'],
    ['titulo' => 'Matrícula', 'width' => '7%', 'align' => 'left', 'pad' => 'padding-left:5px;'],
    ['titulo' => 'Descrição', 'width' => '10%', 'align' => 'left', 'pad' => 'padding-left:5px;'],
    ['titulo' => 'KM', 'width' => '7%', 'align' => 'center', 'pad' => ''],
    ['titulo' => 'Funcionário', 'width' => '13%', 'align' => 'left', 'pad' => 'padding-left:5px;'],
    ['titulo' => 'Local', 'width' => '10%', 'align' => 'left', 'pad' => 'padding-left:5px;'],
    ['titulo' => 'Requisição', 'width' => '8%', 'align' => 'left', 'pad' => 'padding-left:5px;'],
    ['titulo' => 'LTS', 'width' => '7%', 'align' => 'right', 'pad' => 'padding-right:5px;'],
    ['titulo' => 'Montante (€)', 'width' => '7%', 'align' => 'right', 'pad' => 'padding-right:5px;'],
    ['titulo' => 'Montante (€) s/iva', 'width' => '7%', 'align' => 'right', 'pad' => 'padding-right:5px;'],
];

function renderCabecalho($colunas) {
    $thead = '<thead><tr style="background-color:#004080; color:#fff; font-weight: bold; font-size: 9pt;">';
    foreach ($colunas as $col) {
        $thead .= '<th width="' . $col['width'] . '" style="text-align:' . $col['align'] . '; ' . $col['pad'] . ' border: 1px solid #000;">' . $col['titulo'] . '</th>';
    }
    $thead .= '</tr></thead>';
    return $thead;
}

$html = '
<h2 style="text-align:center; color:#004080; font-family: Arial, sans-serif;">Relatório de Abastecimentos</h2>
<style>
    table {
        border-collapse: collapse;
        width: 100%;
        font-size: 9pt;
        font-family: Arial, sans-serif;
    }
    th, td {
        border: 1px solid #000;
        padding: 4px;
    }
    th {
        background-color: #004080;
        color: #fff;
        font-weight: bold;
        text-align: center;
    }
    td {
        text-align: center;
    }
</style>
';

$html .= '<table border="0" cellpadding="4" cellspacing="0">';
$html .= renderCabecalho($colunas);
$html .= '<tbody>';

$matricula_atual = null;
$subtotal_lts = 0;
$subtotal_montante = 0;
$subtotal_montante_siva = 0;
$total_lts = 0;
$total_montante = 0;
$total_montante_siva = 0;

foreach ($dados as $d) {
    if ($matricula_atual !== null && $matricula_atual !== $d['matricula']) {
        $html .= '<tr style="background-color:#e0f7ff; font-weight:bold; font-size: 9pt;">';
        $html .= '<td colspan="8" style="text-align:right; padding-right:5px; border: 1px solid #000;">Subtotal ' . htmlspecialchars($matricula_atual) . ':</td>';
        $html .= '<td style="text-align:right; border: 1px solid #000;">' . number_format($subtotal_lts, 2, ',', '.') . '</td>';
        $html .= '<td style="text-align:right; border: 1px solid #000;">' . number_format($subtotal_montante, 2, ',', '.') . '</td>';
        $html .= '<td style="text-align:right; border: 1px solid #000;">' . number_format($subtotal_montante_siva, 2, ',', '.') . '</td>';
        $html .= '</tr>';

        $subtotal_lts = $subtotal_montante = $subtotal_montante_siva = 0;
    }

    $matricula_atual = $d['matricula'];
    $requisicao = trim($d['requisicao']) === '' ? '(em branco)' : htmlspecialchars($d['requisicao']);

    $html .= '<tr>';
    foreach ($colunas as $i => $col) {
        switch ($i) {
            case 0: $valor = $d['empresa']; break;
            case 1: $valor = $d['Tipo']; break;
            case 2: $valor = $d['matricula']; break;
            case 3: $valor = $d['Descricao']; break;
            case 4: $valor = $d['km']; break;
            case 5: $valor = $d['funcionario']; break;
            case 6: $valor = $d['local']; break;
            case 7: $valor = $requisicao; break;
            case 8: $valor = number_format($d['litros'], 2, ',', '.'); break;
            case 9: $valor = number_format($d['valor_total'], 2, ',', '.'); break;
            case 10: $valor = number_format($d['valor_sem_iva'], 2, ',', '.'); break;
        }
        $html .= '<td width="' . $col['width'] . '" style="' . $col['pad'] . ' text-align:' . $col['align'] . '; border: 1px solid #000;">' . $valor . '</td>';
    }
    $html .= '</tr>';

    $subtotal_lts += (float)$d['litros'];
    $subtotal_montante += (float)$d['valor_total'];
    $subtotal_montante_siva += (float)$d['valor_sem_iva'];

    $total_lts += (float)$d['litros'];
    $total_montante += (float)$d['valor_total'];
    $total_montante_siva += (float)$d['valor_sem_iva'];
}

// Último subtotal
$html .= '<tr style="background-color:#e0f7ff; font-weight:bold; font-size: 9pt;">';
$html .= '<td colspan="8" style="text-align:right; padding-right:5px; border: 1px solid #000;">Subtotal ' . htmlspecialchars($matricula_atual) . ':</td>';
$html .= '<td style="text-align:right; border: 1px solid #000;">' . number_format($subtotal_lts, 2, ',', '.') . '</td>';
$html .= '<td style="text-align:right; border: 1px solid #000;">' . number_format($subtotal_montante, 2, ',', '.') . '</td>';
$html .= '<td style="text-align:right; border: 1px solid #000;">' . number_format($subtotal_montante_siva, 2, ',', '.') . '</td>';
$html .= '</tr>';

$html .= '</tbody></table>';
$html .= '<p style="font-weight:bold; font-size: 10pt; text-align:right; font-family: Arial, sans-serif; margin-top: 15px;">';
$html .= 'Total Geral: ' . number_format($total_lts, 2, ',', '.') . ' LTS | ' .
         number_format($total_montante, 2, ',', '.') . ' € | ' .
         number_format($total_montante_siva, 2, ',', '.') . ' € (s/IVA)';
$html .= '</p>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('relatorio_abastecimentos.pdf', 'I');
?>
