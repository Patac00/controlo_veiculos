<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

require('../fpdf/fpdf.php');
include("../php/config.php");

// Recebe os filtros
$ano = $_GET['ano'] ?? date('Y');
$mes = $_GET['mes'] ?? date('m');
$local = $_GET['local'] ?? '';
$grupo = $_GET['grupo'] ?? '';
$tipo = $_GET['tipo'] ?? '';

$inicio = "$ano-$mes-01";
$fim = date("Y-m-t", strtotime($inicio));

// Preparar filtro SQL para veículos
$where_veic = [];
if ($grupo) $where_veic[] = "v.Grupo = '" . mysqli_real_escape_string($con, $grupo) . "'";
if ($tipo) $where_veic[] = "g.tipo = '" . mysqli_real_escape_string($con, $tipo) . "'";
$where_veic_sql = $where_veic ? "WHERE " . implode(" AND ", $where_veic) : "";

// Query para veículos
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

while ($v = mysqli_fetch_assoc($res_veiculos)) {
    $id_veic = (int)$v['id_veiculo'];

    // Query litros e valores da bomba
    $sql_litros_bomba = "
        SELECT 
            SUM(b.quantidade) AS total,
            SUM(b.quantidade * ms.preco_litro) AS total_valor
        FROM bomba b
        LEFT JOIN movimentos_stock ms 
            ON ms.id_posto = b.id_posto 
            AND DATE(ms.data) = DATE(b.data)
        LEFT JOIN lista_postos p ON b.id_posto = p.id_posto
        WHERE b.id_veiculo = $id_veic 
          AND b.data BETWEEN '$inicio' AND '$fim'
    ";
    if ($local) $sql_litros_bomba .= " AND p.local = '" . mysqli_real_escape_string($con, $local) . "'";
    $res_bomba = mysqli_query($con, $sql_litros_bomba);
    $row_bomba = mysqli_fetch_assoc($res_bomba);
    $litros_bomba = (float)($row_bomba['total'] ?? 0);
    $valor_bomba = (float)($row_bomba['total_valor'] ?? 0);

    // Query litros e valores abastecimentos manuais
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

    // Kms/Horas no mês
    $sql_min = "SELECT MIN(odometro) AS min_km FROM bomba WHERE id_veiculo = $id_veic AND data BETWEEN '$inicio' AND '$fim'";
    $sql_max = "SELECT MAX(odometro) AS max_km FROM bomba WHERE id_veiculo = $id_veic AND data BETWEEN '$inicio' AND '$fim'";
    $min_km = (float)(mysqli_fetch_assoc(mysqli_query($con, $sql_min))['min_km'] ?? 0);
    $max_km = (float)(mysqli_fetch_assoc(mysqli_query($con, $sql_max))['max_km'] ?? 0);
    $kms_hrs_mes = max(0, $max_km - $min_km);

    // Consumo 1 mês
    $consumo_1mes = '-';
    if ($litros_total > 0 && $kms_hrs_mes > 0) {
        $consumo_1mes = ($v['tipo'] == 'Máquina' || $v['tipo'] == 'Empilhador')
            ? round($litros_total / $kms_hrs_mes, 2)
            : round(($litros_total / $kms_hrs_mes) * 100, 2);
    }

    $relatorio[] = [
        'descricao' => $v['Descricao'],
        'matricula' => $v['matricula'],
        'empresa' => $v['empresa'],
        'litros' => $litros_total,
        'kms_hrs_mes' => $kms_hrs_mes,
        'consumo_1mes' => $consumo_1mes,
    ];
}

// Agora começa a gerar o PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, "Relatório Mensal de Consumos: $ano-$mes", 0, 1, 'C');

// Cabeçalho da tabela
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(0, 64, 128);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(40, 7, 'Descrição', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Matrícula', 1, 0, 'C', true);
$pdf->Cell(35, 7, 'Empresa', 1, 0, 'C', true);
$pdf->Cell(20, 7, 'Lts (mês)', 1, 0, 'C', true);
$pdf->Cell(20, 7, 'Kms/Hrs', 1, 0, 'C', true);
$pdf->Cell(30, 7, 'Consumo L/100km', 1, 1, 'C', true);

// Conteúdo da tabela
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0, 0, 0);

foreach ($relatorio as $item) {
$pdf->Cell(40, 6, mb_convert_encoding($item['descricao'], 'ISO-8859-1', 'UTF-8'), 1);
$pdf->Cell(25, 6, $item['matricula'], 1);
$pdf->Cell(35, 6, mb_convert_encoding($item['empresa'], 'ISO-8859-1', 'UTF-8'), 1);
$pdf->Cell(20, 6, number_format($item['litros'], 2, ',', '.'), 1, 0, 'R');
$pdf->Cell(20, 6, number_format($item['kms_hrs_mes'], 2, ',', '.'), 1, 0, 'R');
$consumo = is_numeric($item['consumo_1mes']) ? number_format($item['consumo_1mes'], 2, ',', '.') : '-';
$pdf->Cell(30, 6, $consumo, 1, 1, 'R');
}

$pdf->Output('I', 'relatorio_consumos_'.$ano.'_'.$mes.'.pdf');
exit;
?>
