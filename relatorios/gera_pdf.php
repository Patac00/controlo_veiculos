<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");
require_once('../tcpdf/tcpdf.php');

// Filtros recebidos por GET (podem estar vazios)
$ano = $_GET['ano'] ?? '';
$mes = $_GET['mes'] ?? '';
$local = $_GET['local'] ?? '';
$grupo = $_GET['grupo'] ?? '';

$inicio = ($ano && $mes) ? "$ano-$mes-01" : null;
$fim = $inicio ? date("Y-m-t", strtotime($inicio)) : null;

// Montar condições SQL para abastecimentos
$where_abast = [];
if ($inicio && $fim) $where_abast[] = "a.data_abastecimento BETWEEN '$inicio' AND '$fim'";
if ($grupo) $where_abast[] = "v.Grupo = '$grupo'";
if ($local) $where_abast[] = "p.local = '$local'";
$where_sql_abast = $where_abast ? 'WHERE ' . implode(' AND ', $where_abast) : '';

// Montar condições SQL para bomba
$where_bomba = [];
if ($inicio && $fim) $where_bomba[] = "b.data BETWEEN '$inicio' AND '$fim'";
if ($grupo) $where_bomba[] = "v.Grupo = '$grupo'";
if ($local) $where_bomba[] = "p.local = '$local'";
$where_sql_bomba = $where_bomba ? 'WHERE ' . implode(' AND ', $where_bomba) : '';

// Query abastecimentos
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

// Query bomba
$sql_bomba = "
    SELECT 
        e.nome AS empresa,
        v.Tipo,
        v.matricula,
        v.Descricao,
        b.odometro AS km,
        b.motorista AS funcionario,
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
    $where_sql_bomba
";

// Junta os resultados
$sql = "$sql_abast UNION ALL $sql_bomba ORDER BY matricula, km ASC";

$res = mysqli_query($con, $sql);

$dados = [];
while ($row = mysqli_fetch_assoc($res)) {
    $dados[] = $row;
}

// Iniciar PDF
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Ambipombal');
$pdf->SetTitle('Relatório de Abastecimentos por Veículo');
$pdf->SetMargins(10, 15, 10);
$pdf->AddPage('L');
$pdf->SetFont('helvetica', '', 7);

// Sem dados
if (empty($dados)) {
    $pdf->Write(0, 'Nenhum dado encontrado para os filtros aplicados.', '', 0, 'L', true, 0, false, false, 0);
    $pdf->Output('relatorio_abastecimentos.pdf', 'I');
    exit;
}

// Cabeçalho da tabela (com larguras fixas e alinhamentos)
$thead = '<thead>
    <tr style="background-color:#004080; color:#fff; font-weight: bold; font-size: 9pt;">
        <th width="12%" style="text-align:left; padding-left:5px;">Empresa</th>
        <th width="12%" style="text-align:left; padding-left:5px;">Tipo</th>
        <th width="10%" style="text-align:left; padding-left:5px;">Matrícula</th>
        <th width="10%" style="text-align:left; padding-left:5px;">Descrição</th>
        <th width="7%" style="text-align:center;">KM</th>
        <th width="13%" style="text-align:left; padding-left:5px;">Funcionário</th>
        <th width="10%" style="text-align:left; padding-left:5px;">Local</th>
        <th width="8%" style="text-align:left; padding-left:5px;">Requisição</th>
        <th width="7%" style="text-align:right; padding-right:5px;">LTS</th>
        <th width="7%" style="text-align:right; padding-right:5px;">Montante (€) </th>
        <th width="7%" style="text-align:right; padding-right:5px;">Montante (€) s/iva</th>
    </tr>
</thead>';

// Começar o HTML da tabela
$html = '<h2 style="text-align:center; color:#004080; font-family: Arial, sans-serif;">Relatório de Abastecimentos</h2>';
$html .= '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse: collapse; font-family: Arial, sans-serif; font-size: 9pt; width: 100%;">';
$html .= $thead . '<tbody>';

$matricula_atual = null;
$subtotal_lts = 0;
$subtotal_montante = 0;
$subtotal_montante_siva = 0;
$total_lts = 0;
$total_montante = 0;
$total_montante_siva = 0;

foreach ($dados as $d) {
    if ($d['matricula'] !== $matricula_atual) {
        if ($matricula_atual !== null) {
            // Linha subtotal da matrícula anterior alinhada
            $html .= '<tr style="background-color:#e0f7ff; font-weight:bold; font-size: 9pt;">
                <td colspan="8" style="text-align:right; padding-right:5px;">' . htmlspecialchars($matricula_atual) . ' Total:</td>
                <td width="7%" style="text-align:right; padding-right:5px;">' . number_format($subtotal_lts, 2, ',', '.') . '</td>
                <td width="7%" style="text-align:right; padding-right:5px;">' . number_format($subtotal_montante, 2, ',', '.') . '</td>
                <td width="7%" style="text-align:right; padding-right:5px;">' . number_format($subtotal_montante_siva, 2, ',', '.') . '</td>
            </tr>';
            $subtotal_lts = 0;
            $subtotal_montante = 0;
            $subtotal_montante_siva = 0;

            $html .= '</tbody></table><br><table border="1" cellpadding="4" cellspacing="0" style="border-collapse: collapse; font-family: Arial, sans-serif; font-size: 9pt; width: 100%;">';
            $html .= $thead . '<tbody>';
        }
        $matricula_atual = $d['matricula'];
    }

    $requisicao = trim($d['requisicao']);
    if ($requisicao === '') $requisicao = '(em branco)';

    $html .= '<tr>
        <td width="12%" style="padding-left:5px; text-align:left;">' . htmlspecialchars($d['empresa']) . '</td>
        <td width="12%" style="padding-left:5px; text-align:left;">' . htmlspecialchars($d['Tipo']) . '</td>
        <td width="10%" style="padding-left:5px; text-align:left;">' . htmlspecialchars($d['matricula']) . '</td>
        <td width="10%" style="padding-left:5px; text-align:left;">' . htmlspecialchars($d['Descricao']) . '</td>
        <td width="7%" style="text-align:center;">' . htmlspecialchars($d['km']) . '</td>
        <td width="13%" style="padding-left:5px; text-align:left;">' . htmlspecialchars($d['funcionario']) . '</td>
        <td width="10%" style="padding-left:5px; text-align:left;">' . htmlspecialchars($d['local']) . '</td>
        <td width="8%" style="padding-left:5px; text-align:left;">' . htmlspecialchars($requisicao) . '</td>
        <td width="7%" style="text-align:right; padding-right:5px;">' . number_format($d['litros'] ?? 0, 2, ',', '.') . '</td>
        <td width="7%" style="text-align:right; padding-right:5px;">' . number_format($d['valor_total'] ?? 0, 2, ',', '.') . '</td>
        <td width="7%" style="text-align:right; padding-right:5px;">' . number_format($d['valor_sem_iva'] ?? 0, 2, ',', '.') . '</td>
    </tr>';

    $subtotal_lts += (float)$d['litros'];
    $subtotal_montante += (float)$d['valor_total'];
    $subtotal_montante_siva += (float)$d['valor_sem_iva'];

    $total_lts += (float)$d['litros'];
    $total_montante += (float)$d['valor_total'];
    $total_montante_siva += (float)$d['valor_sem_iva'];
}

// Último subtotal matrícula
if ($matricula_atual !== null) {
    $html .= '<tr style="background-color:#e0f7ff; font-weight:bold; font-size: 9pt;">
        <td colspan="8" style="text-align:right; padding-right:5px;">' . htmlspecialchars($matricula_atual) . ' Total:</td>
        <td width="7%" style="text-align:right; padding-right:5px;">' . number_format($subtotal_lts, 2, ',', '.') . '</td>
        <td width="7%" style="text-align:right; padding-right:5px;">' . number_format($subtotal_montante, 2, ',', '.') . '</td>
        <td width="7%" style="text-align:right; padding-right:5px;">' . number_format($subtotal_montante_siva, 2, ',', '.') . '</td>
    </tr>';
}

$html .= '</tbody></table>';

// Total geral
$html .= '<p style="font-weight:bold; font-size: 10pt; text-align:right; font-family: Arial, sans-serif; margin-top: 15px;">';
$html .= 'Total Geral: ' . number_format($total_lts, 2, ',', '.') . ' LTS | ';
$html .= number_format($total_montante, 2, ',', '.') . ' € | ';
$html .= number_format($total_montante_siva, 2, ',', '.') . ' € (s/IVA)';
$html .= '</p>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('relatorio_abastecimentos.pdf', 'I');
?>
