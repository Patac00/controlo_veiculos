<?php
echo "Início do script<br>";

// Mostrar erros para debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("../php/config.php");

require '../vendor/autoload.php'; 
use PhpOffice\PhpSpreadsheet\IOFactory;

function normalizaMotorista($valor) {
    return trim(strtoupper($valor));
}

function converteData($dataExcel) {
    if (is_numeric($dataExcel)) {
        $timestamp = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($dataExcel);
    } else {
        $timestamp = strtotime(str_replace('/', '-', $dataExcel));
    }
    if ($timestamp === false) return null;
    return date('d/m/Y', $timestamp);
}

// Buscar todos os veículos da BD
$veiculos = [];
$result = $con->query("SELECT id_veiculo, Matricula, Descricao FROM veiculos");
while ($row = $result->fetch_assoc()) {
    $veiculos[] = $row;
}

function normalizaIdentificacao($valor, $veiculos) {
    $limpo = strtoupper(str_replace(['.', '-', ' '], '', $valor));

    foreach ($veiculos as $veiculo) {
        $matriculaLimpa = strtoupper(str_replace(['-', ' '], '', $veiculo['Matricula']));
        if ($limpo === $matriculaLimpa) {
            return ['id_veiculo' => $veiculo['id_veiculo'], 'numero_reg' => $veiculo['Matricula']];
        }

        $descricaoLimpa = strtoupper(str_replace(['-', ' '], '', $veiculo['Descricao']));
        if ($limpo === $descricaoLimpa) {
            return ['id_veiculo' => $veiculo['id_veiculo'], 'numero_reg' => $veiculo['Descricao']];
        }
    }

    return ['id_veiculo' => null, 'numero_reg' => strtoupper($valor)];
}

$dadosConvertidos = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $arquivoTmp = $_FILES['excel_file']['tmp_name'];
    $spreadsheet = IOFactory::load($arquivoTmp);
    echo "Excel carregado com sucesso<br>";

    $sheet = $spreadsheet->getActiveSheet();
    $linhas = $sheet->toArray();

    foreach ($linhas as $i => $linha) {
        if ($i == 0) continue; // Ignora cabeçalho

        $infoVeiculo = normalizaIdentificacao($linha[2] ?? '', $veiculos);

        $dadosConvertidos[] = [
            'data' => converteData($linha[0] ?? ''),
            'hora' => $linha[1] ?? '',
            'id_veiculo' => $infoVeiculo['id_veiculo'],
            'numero_reg' => $infoVeiculo['numero_reg'],
            'odometro' => is_numeric($linha[3]) ? (int)$linha[3] : 0,
            'motorista' => normalizaMotorista($linha[4] ?? ''),
            'quantidade' => is_numeric($linha[5]) ? (float)$linha[5] : 0.0,
        ];
    }
    

    $_SESSION['dados_convertidos'] = $dadosConvertidos;
}

// Mostrar tabela (mesmo sem novo upload)
if (isset($_SESSION['dados_convertidos']) && empty($dadosConvertidos)) {
    $dadosConvertidos = $_SESSION['dados_convertidos'];
}

if (!empty($dadosConvertidos)) {
    echo "<h2>Dados Convertidos</h2>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr>
            <th>Data</th>
            <th>Hora</th>
            <th>Nº Registo</th>
            <th>Odómetro</th>
            <th>Motorista</th>
            <th>Quantidade</th>
          </tr>";

    foreach ($dadosConvertidos as $linha) {
        echo "<tr>";
        echo "<td>{$linha['data']}</td>";
        echo "<td>{$linha['hora']}</td>";
        echo "<td>{$linha['numero_reg']}</td>";
        echo "<td>{$linha['odometro']}</td>";
        echo "<td>{$linha['motorista']}</td>";
        echo "<td>{$linha['quantidade']}</td>";
        echo "</tr>";
    }

    echo "</table>";
}
?>
