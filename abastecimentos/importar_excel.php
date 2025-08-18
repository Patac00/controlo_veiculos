<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");
require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

// ===========================
// Funções de normalização
// ===========================
function normalizaMotorista($valor) {
    return trim(strtoupper($valor));
}

function normalizaMatricula($valor) {
    $valor = strtoupper(str_replace([' ', '.', ','], '', $valor));
    if (preg_match('/^[A-Z0-9]{6,7}$/', $valor)) {
        return substr($valor, 0, 2) . '-' . substr($valor, 2, 2) . '-' . substr($valor, 4);
    }
    return $valor;
}

function converteData($data) {
    $data = str_replace('/', '-', $data);
    $partes = explode('-', $data);
    if (count($partes) !== 3) return $data;
    if (strlen($partes[0]) === 4) return $data;
    if (strlen($partes[2]) === 4) {
        $dia = (int)$partes[0];
        return $dia > 12 ? "{$partes[2]}-{$partes[1]}-{$partes[0]}" : "{$partes[2]}-{$partes[0]}-{$partes[1]}";
    }
    return $data;
}

// ===========================
// Variáveis
// ===========================
$dadosConvertidos = [];
$errosLinhas = [];

// ===========================
// Upload e leitura do Excel
// ===========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) { 
        echo "<p>Erro no upload do ficheiro.</p>"; exit(); 
    }

    $arquivoTmp = $_FILES['excel_file']['tmp_name'];
    $tipoMime = mime_content_type($arquivoTmp);
    if (!in_array($tipoMime, ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/vnd.ms-excel'])) {
        echo "<p>Ficheiro inválido. Apenas Excel é permitido.</p>"; exit();
    }

    // Mapa de motoristas
    $mapaMotoristas = [];
    $resMoto = $con->query("SELECT codigo_bomba, nome FROM motoristas");
    while ($m = $resMoto->fetch_assoc()) { 
        $mapaMotoristas[strtoupper(trim($m['codigo_bomba']))] = $m['nome']; 
    }

    try {
        $spreadsheet = IOFactory::load($arquivoTmp);
        $sheet = $spreadsheet->getActiveSheet();
        $linhas = $sheet->toArray();

        foreach ($linhas as $i => $linha) {
            if ($i === 0 || empty($linha[0])) continue;
            $dadosConvertidos[] = [
                'data'       => $linha[0] ?? '',
                'hora'       => $linha[1] ?? '',
                'unidade'    => $linha[2] ?? '',
                'matricula'  => normalizaMatricula($linha[3] ?? ''),
                'odometro'   => $linha[4] ?? '',
                'motorista'  => normalizaMotorista($linha[5] ?? ''),
                'quantidade' => $linha[6] ?? '',
            ];
        }

        // Substituir código do motorista pelo nome
        foreach ($dadosConvertidos as &$linha) {
            $codMotorista = strtoupper(trim($linha['motorista']));
            if (isset($mapaMotoristas[$codMotorista])) $linha['motorista'] = $mapaMotoristas[$codMotorista];
        }
        unset($linha);

        $_SESSION['dados_convertidos'] = $dadosConvertidos;

    } catch (Exception $e) { 
        echo "<p>Erro ao ler o ficheiro: " . $e->getMessage() . "</p>"; 
        exit(); 
    }
}

// ===========================
// Guardar dados na Base de Dados
// ===========================
if (isset($_POST['guardar']) && isset($_SESSION['dados_convertidos'])) {
    $dados = $_SESSION['dados_convertidos'];

    // Buscar veículos
    $veiculos = [];
    $res = $con->query("SELECT id_veiculo, Matricula FROM veiculos");
    while ($v = $res->fetch_assoc()) { 
        $veiculos[strtoupper(str_replace(['-', ' '], '', $v['Matricula']))] = $v['id_veiculo']; 
    }

    foreach ($dados as $i => $linha) {
        $erros = [];

        // ===========================
        // Validações obrigatórias
        // ===========================
        if (empty($linha['data'])) $erros[] = "Data é obrigatória";
        if (empty($linha['hora'])) $erros[] = "Hora é obrigatória";
        if (empty($linha['matricula'])) $erros[] = "Matrícula é obrigatória";
        if (empty($linha['odometro'])) $erros[] = "Odómetro é obrigatório";
        if (empty($linha['quantidade'])) $erros[] = "Quantidade é obrigatória";
        if (empty($linha['motorista'])) $erros[] = "Motorista é obrigatório";
        if (empty($linha['unidade'])) $erros[] = "Unidade é obrigatória";

        $matriculaLimpa = strtoupper(str_replace(['-', ' '], '', $linha['matricula']));
        $id_veiculo = $veiculos[$matriculaLimpa] ?? null;
        if (!$id_veiculo) $erros[] = "Matrícula <strong>{$linha['matricula']}</strong> não encontrada.";

        $data = converteData($linha['data']);
        $hora = $linha['hora'];
        $odometro = (int)$linha['odometro'];
        $quantidade = (float)$linha['quantidade'];

        if ($odometro <= 0) $erros[] = "Odómetro deve ser positivo.";
        if ($quantidade <= 0) $erros[] = "Quantidade deve ser positiva.";

        // Id posto
        $res_posto = $con->prepare("SELECT id_posto FROM lista_postos WHERE unidade=? LIMIT 1");
        $res_posto->bind_param("s",$linha['unidade']);
        $res_posto->execute();
        $res_posto->bind_result($id_posto);
        $res_posto->fetch();
        $res_posto->close();
        $id_posto = $id_posto ?? 0;

        // ===========================
        // Verificação de duplicados
        // ===========================
        if (empty($erros) && $id_veiculo) {
            $stmtDup = $con->prepare("SELECT id_bomba FROM bomba WHERE id_veiculo=? AND data=? AND hora=? AND odometro=? LIMIT 1");
            $stmtDup->bind_param("issi", $id_veiculo, $data, $hora, $odometro);
            $stmtDup->execute();
            $stmtDup->store_result();
            if ($stmtDup->num_rows > 0) $erros[] = "⚠️ Registo duplicado detectado!";
            $stmtDup->close();
        }

        // ===========================
        // Inserção
        // ===========================
        if (empty($erros) && $id_veiculo) {
            $stmt = $con->prepare("INSERT INTO bomba (unidade,data,hora,id_veiculo,numero_reg,odometro,motorista,quantidade,id_posto) 
                VALUES (?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("sssisisdi", $linha['unidade'], $data, $hora, $id_veiculo, $linha['matricula'], $odometro, $linha['motorista'], $quantidade, $id_posto);
            if (!$stmt->execute()) $erros[] = "Erro no insert: " . $stmt->error;
            $stmt->close();
        }

        if (!empty($erros)) $errosLinhas[$i] = ['mensagem' => implode('<br>', $erros), 'linha' => $linha];
    }

    if (!empty($errosLinhas)) $_SESSION['erros_linhas'] = $errosLinhas;
    else unset($_SESSION['dados_convertidos']);

    echo "<p style='color: green; text-align:center;'>✅ Processamento concluído!</p>";
}

// ===========================
// Limpar sessão
// ===========================
if (isset($_POST['limpar'])) {
    unset($_SESSION['dados_convertidos'], $_SESSION['erros_linhas']);
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<title>Importar Excel</title>
<style>
body {
    font-family: 'Segoe UI', sans-serif;
    max-width: 1100px;
    margin: 30px auto;
    padding: 20px;
    background: #fff;
    border-radius: 8px;
}
h2,h3 { text-align:center; margin-bottom:20px; }
input,button { margin:5px; padding:8px 15px; font-size:15px; border-radius:5px; }
button,input[type="submit"] { background:#007bff; color:#fff; border:none; cursor:pointer; }
button:hover,input[type="submit"]:hover { background:#0056b3; }
table { width:100%; border-collapse: collapse; font-size:14px; margin-top:20px; }
th, td { border:1px solid #ddd; padding:8px; text-align:center; }
th { background-color:#007bff; color:#fff; }
td { background-color:#f9f9f9; word-wrap:break-word; }
.erro td { background-color:#ffe5e5; }
input[readonly] { background-color:#e9ecef; border:none; text-align:center; }
</style>
</head>
<body>

<form method="post" style="text-align:center;margin-bottom:20px;">
    <button type="submit" name="limpar">Limpar dados</button>
</form>

<h2>Importar Ficheiro Excel</h2>
<form method="post" enctype="multipart/form-data" style="text-align:center;">
    <input type="file" name="excel_file" accept=".xls,.xlsx" required>
    <input type="submit" value="Importar">
</form>

<?php if (!empty($_SESSION['dados_convertidos'])): ?>
<h3>Dados Convertidos:</h3>
<table>
<thead>
<tr>
<th>Data</th><th>Hora</th><th>Matrícula</th><th>Odómetro</th><th>Motorista</th><th>Quantidade</th><th>Unidade</th><th>Erro</th>
</tr>
</thead>
<tbody>
<?php foreach ($_SESSION['dados_convertidos'] as $index => $linha):
$temErro = isset($_SESSION['erros_linhas'][$index]);
?>
<tr class="<?= $temErro ? 'erro' : '' ?>">
<td><input type="text" value="<?= htmlspecialchars($linha['data']) ?>" <?= $temErro?'':'readonly' ?>></td>
<td><input type="text" value="<?= htmlspecialchars($linha['hora']) ?>" <?= $temErro?'':'readonly' ?>></td>
<td><input type="text" value="<?= htmlspecialchars($linha['matricula']) ?>" <?= $temErro?'':'readonly' ?>></td>
<td><input type="number" value="<?= htmlspecialchars($linha['odometro']) ?>" <?= $temErro?'':'readonly' ?>></td>
<td><input type="text" value="<?= htmlspecialchars($linha['motorista']) ?>" <?= $temErro?'':'readonly' ?>></td>
<td><input type="number" value="<?= htmlspecialchars($linha['quantidade']) ?>" <?= $temErro?'':'readonly' ?>></td>
<td><input type="text" value="<?= htmlspecialchars($linha['unidade']) ?>" <?= $temErro?'':'readonly' ?>></td>
<td>
<?php if($temErro): ?>
<?= $_SESSION['erros_linhas'][$index]['mensagem'] ?>
<br><a href="../lista_veiculos/inserir_veiculo.php" target="_blank"><button type="button">Inserir veículo</button></a>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<form method="post" style="text-align:center;margin-top:20px;">
    <button type="submit" name="guardar">Guardar na Base de Dados</button>
</form>
<?php endif; ?>

<form action="../html/index.php" style="text-align:center;margin-top:20px;">
<button>Voltar ao Início</button>
</form>

</body>
</html>
