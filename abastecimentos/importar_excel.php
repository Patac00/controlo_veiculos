<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");
require '../vendor/autoload.php'; 
use PhpOffice\PhpSpreadsheet\IOFactory;

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
    $partes = explode('/', $data);
    if (count($partes) === 3) {
        return $partes[2] . '-' . $partes[1] . '-' . $partes[0];
    }
    return $data;
}

$dadosConvertidos = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    if ($_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        echo "<p>Erro no upload do ficheiro.</p>";
        exit();
    }

    $arquivoTmp = $_FILES['excel_file']['tmp_name'];
    $tipoMime = mime_content_type($arquivoTmp);
    if (!in_array($tipoMime, [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel'
    ])) {
        echo "<p>Ficheiro inválido. Apenas Excel é permitido.</p>";
        exit();
    }

    try {
        $spreadsheet = IOFactory::load($arquivoTmp);
        $sheet = $spreadsheet->getActiveSheet();
        $linhas = $sheet->toArray();

        foreach ($linhas as $i => $linha) {
            if ($i == 0 || empty($linha[0])) continue; // ignora cabeçalho e linhas vazias

            $dadosConvertidos[] = [
                'data' => $linha[0] ?? '',
                'hora' => $linha[1] ?? '',
                'matricula' => normalizaMatricula($linha[2] ?? ''), // interface mantém "matricula"
                'odometro' => $linha[3] ?? '',
                'motorista' => normalizaMotorista($linha[4] ?? ''),
                'quantidade' => $linha[5] ?? '',
            ];
        }

        $_SESSION['dados_convertidos'] = $dadosConvertidos;
    } catch (Exception $e) {
        echo "<p>Erro ao ler o ficheiro: " . $e->getMessage() . "</p>";
        exit();
    }
}

// Guardar dados na base de dados
if (isset($_POST['guardar']) && isset($_SESSION['dados_convertidos'])) {
    $dados = $_SESSION['dados_convertidos'];

    // Buscar veículos da BD
    $veiculos = [];
    $res = $con->query("SELECT id_veiculo, Matricula FROM veiculos");
    while ($v = $res->fetch_assoc()) {
        $matriculaLimpa = strtoupper(str_replace(['-', ' '], '', $v['Matricula']));
        $veiculos[$matriculaLimpa] = $v['id_veiculo'];
    }

    foreach ($dados as $linha) {
        $matriculaLimpa = strtoupper(str_replace(['-', ' '], '', $linha['matricula']));
        $id_veiculo = $veiculos[$matriculaLimpa] ?? "NULL";

        $data = converteData(mysqli_real_escape_string($con, $linha['data']));
        $hora = mysqli_real_escape_string($con, $linha['hora']);
        $numero_reg = mysqli_real_escape_string($con, $linha['matricula']); // usa aqui para o campo da tabela
        $odometro = (int) $linha['odometro'];
        $motorista = mysqli_real_escape_string($con, $linha['motorista']);
        $quantidade = (float) $linha['quantidade'];

        $sql = "INSERT INTO bomba_redinha (data, hora, id_veiculo, numero_reg, odometro, motorista, quantidade) 
                VALUES ('$data', '$hora', $id_veiculo, '$numero_reg', $odometro, '$motorista', $quantidade)";
        $con->query($sql);
    }

    echo "<p style='color: green;'>Dados guardados com sucesso!</p>";
    unset($_SESSION['dados_convertidos']);
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8" />
    <title>Importar e Mostrar Excel</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 30px auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 6px;
            text-align: center;
        }
        th {
            background-color: #007bff;
            color: white;
        }
        input[type="file"], input[type="submit"], button {
            margin-top: 15px;
            padding: 8px 20px;
            font-size: 16px;
        }
    </style>
</head>
<body>

<h2>Importar Ficheiro Excel</h2>

<form method="post" enctype="multipart/form-data">
    <input type="file" name="excel_file" accept=".xls,.xlsx" required>
    <input type="submit" value="Importar">
</form>

<?php if (!empty($dadosConvertidos)): ?>
    <h3>Dados Convertidos:</h3>
    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Hora</th>
                <th>Matrícula</th>
                <th>Odómetro</th>
                <th>Motorista</th>
                <th>Quantidade</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dadosConvertidos as $linha): ?>
                <tr>
                    <td><?= htmlspecialchars($linha['data']) ?></td>
                    <td><?= htmlspecialchars($linha['hora']) ?></td>
                    <td><?= htmlspecialchars($linha['matricula']) ?></td>
                    <td><?= htmlspecialchars($linha['odometro']) ?></td>
                    <td><?= htmlspecialchars($linha['motorista']) ?></td>
                    <td><?= htmlspecialchars($linha['quantidade']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <form method="post">
        <button type="submit" name="guardar">Guardar na Base de Dados</button>
    </form>

<?php endif; ?>

</body>
</html>
