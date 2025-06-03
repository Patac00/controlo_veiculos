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
    return trim(strtoupper(str_replace(" ", "", $valor)));
}

$dadosConvertidos = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $arquivoTmp = $_FILES['excel_file']['tmp_name'];
    $spreadsheet = IOFactory::load($arquivoTmp);
    $sheet = $spreadsheet->getActiveSheet();
    $linhas = $sheet->toArray();

    foreach ($linhas as $i => $linha) {
        if ($i == 0) continue; // ignora cabeçalho

        $dadosConvertidos[] = [
            'data' => $linha[0] ?? '',
            'hora' => $linha[1] ?? '',
            'numero_reg' => $linha[2] ?? '',
            'odometro' => $linha[3] ?? '',
            'motorista' => normalizaMotorista($linha[4] ?? ''),
            'quantidade' => $linha[5] ?? '',
        ];
    }

    $_SESSION['dados_convertidos'] = $dadosConvertidos;
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
                <th>Nº Registo</th>
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
                    <td><?= htmlspecialchars($linha['numero_reg']) ?></td>
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
