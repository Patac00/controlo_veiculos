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
                'matricula' => normalizaMatricula($linha[2] ?? ''),
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

    <form method="post" action="converter_dados.php">
        <button type="submit">Continuar</button>
    </form>
<?php endif; ?>

</body>
</html>
