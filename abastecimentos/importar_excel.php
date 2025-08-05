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
    $data = str_replace('/', '-', $data);
    $partes = explode('-', $data);

    if (count($partes) !== 3) return $data; // formato inválido, retorna como está

    // tenta detetar o formato
    if (strlen($partes[0]) === 4) {
        // YYYY-MM-DD
        return $data;
    } elseif (strlen($partes[2]) === 4) {
        // DD-MM-YYYY ou MM-DD-YYYY — tenta adivinhar pelo valor do mês
        $dia = (int)$partes[0];
        $mes = (int)$partes[1];
        if ($dia > 12) {
            // claramente DD-MM-YYYY
            return "{$partes[2]}-{$partes[1]}-{$partes[0]}";
        } else {
            // assume MM-DD-YYYY
            return "{$partes[2]}-{$partes[0]}-{$partes[1]}";
        }
    }

    return $data;
}

function validaData($data) {
    $d = DateTime::createFromFormat('Y-m-d', converteData($data));
    return $d && $d->format('Y-m-d') === converteData($data);
}

function validaHora($hora) {
    return preg_match('/^(2[0-3]|[01][0-9]):[0-5][0-9](:[0-5][0-9])?$/', $hora);
}

function validaMatricula($matricula) {
    // Normalizar e validar formato PT: XX-XX-XX ou XX-XXX-XX
    $mat = strtoupper(str_replace([' ', '.', ','], '', $matricula));
    return preg_match('/^[A-Z0-9]{6,7}$/', $mat);
}

function validaNumeroPositivo($num) {
    return is_numeric($num) && $num >= 0;
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
            if ($i == 0 || empty($linha[0])) continue; // ignora cabeçalho

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

        $_SESSION['dados_convertidos'] = $dadosConvertidos;
    } catch (Exception $e) {
        echo "<p>Erro ao ler o ficheiro: " . $e->getMessage() . "</p>";
        exit();
    }
}

// Guardar dados na BD
if (isset($_POST['guardar']) && isset($_SESSION['dados_convertidos'])) {
    $dados = $_SESSION['dados_convertidos'];

    // Buscar veículos
    $veiculos = [];
    $res = $con->query("SELECT id_veiculo, Matricula FROM veiculos");
    while ($v = $res->fetch_assoc()) {
        $matriculaLimpa = strtoupper(str_replace(['-', ' '], '', $v['Matricula']));
        $veiculos[$matriculaLimpa] = $v['id_veiculo'];
    }

foreach ($dados as $linha) {
    $matriculaLimpa = strtoupper(str_replace(['-', ' '], '', $linha['matricula']));
    $id_veiculo = $veiculos[$matriculaLimpa] ?? null;

if (!$id_veiculo) {
    echo "
    <div style='color: red; display: flex; justify-content: space-between; align-items: center; border: 1px solid red; padding: 8px; margin: 5px 0;'>
        <span>Matrícula <strong>{$linha['matricula']}</strong> não encontrada na base de dados.</span>
        <a href='../lista_veiculos/inserir_veiculo.php' target='_blank'>
            <button style='background-color: #dc3545; color: white; border: none; padding: 6px 12px; cursor: pointer;'>Inserir veículo</button>
        </a>
    </div>";
    continue;
}


    $data = converteData(mysqli_real_escape_string($con, $linha['data']));
    $hora = mysqli_real_escape_string($con, $linha['hora']);
    $numero_reg = mysqli_real_escape_string($con, $linha['matricula']);
    $odometro = (int) $linha['odometro'];
    $motorista = mysqli_real_escape_string($con, $linha['motorista']);
    $quantidade = (float) $linha['quantidade'];

    // Novo: obter id_posto a partir da unidade
    $unidade = mysqli_real_escape_string($con, $linha['unidade']);
    $sql_posto = "SELECT id_posto FROM lista_postos WHERE unidade = '$unidade' LIMIT 1";
    $res_posto = $con->query($sql_posto);
    $id_posto = $res_posto && $res_posto->num_rows > 0 ? (int)$res_posto->fetch_assoc()['id_posto'] : 0;

    // Verifica duplicado
    $sql_dup = "SELECT odometro FROM bomba WHERE id_veiculo = $id_veiculo AND data = '$data' AND hora = '$hora' LIMIT 1";
    $res_dup = $con->query($sql_dup);
    if ($res_dup && $res_dup->num_rows > 0) {
        echo "
        <div style='color: red; display: flex; justify-content: space-between; align-items: center; border: 1px solid red; padding: 8px; margin: 5px 0;'>
            <span>Erro: Já existe um registo para o veículo <strong>{$numero_reg}</strong> na data <strong>{$data}</strong> e hora <strong>{$hora}</strong>.</span>
        </div>";
        continue;
    }

    // Verifica anterior e seguinte
    $sql_anterior = "SELECT odometro FROM bomba WHERE id_veiculo = $id_veiculo AND (data < '$data' OR (data = '$data' AND hora < '$hora')) ORDER BY data DESC, hora DESC LIMIT 1";
    $res_anterior = $con->query($sql_anterior);
    $odometro_anterior = $res_anterior && $res_anterior->num_rows > 0 ? (int)$res_anterior->fetch_assoc()['odometro'] : null;

    $sql_seguinte = "SELECT odometro FROM bomba WHERE id_veiculo = $id_veiculo AND (data > '$data' OR (data = '$data' AND hora > '$hora')) ORDER BY data ASC, hora ASC LIMIT 1";
    $res_seguinte = $con->query($sql_seguinte);
    $odometro_seguinte = $res_seguinte && $res_seguinte->num_rows > 0 ? (int)$res_seguinte->fetch_assoc()['odometro'] : null;

    if (!is_null($odometro_anterior) && $odometro <= $odometro_anterior) {
        echo "
        <div style='color: red; display: flex; justify-content: space-between; align-items: center; border: 1px solid red; padding: 8px; margin: 5px 0;'>
            <span>Erro: odómetro <strong>{$odometro}</strong> deve ser superior ao último registo anterior <strong>{$odometro_anterior}</strong> para a matrícula <strong>{$numero_reg}</strong>.</span>
            <a href='../relatorios/odometros.php?matricula={$numero_reg}' target='_blank'>
                <button style='background-color: #dc3545; color: white; border: none; padding: 6px 12px; cursor: pointer;'>Ver Histórico</button>
            </a>
        </div>";
        continue;
    }

    if (!is_null($odometro_seguinte) && $odometro >= $odometro_seguinte) {
        echo "
        <div style='color: red; display: flex; justify-content: space-between; align-items: center; border: 1px solid red; padding: 8px; margin: 5px 0;'>
            <span>Erro: odómetro <strong>{$odometro}</strong> deve ser inferior ao próximo registo <strong>{$odometro_seguinte}</strong> para a matrícula <strong>{$numero_reg}</strong>.</span>
            <a href='../relatorios/odometros.php?matricula={$numero_reg}' target='_blank'>
                <button style='background-color: #dc3545; color: white; border: none; padding: 6px 12px; cursor: pointer;'>Ver Histórico</button>
            </a>
        </div>";
        continue;
    }


    // INSERT
    $sql = "INSERT INTO bomba (
                unidade, data, hora, id_veiculo, numero_reg,
                odometro, motorista, quantidade, id_posto
            ) VALUES (
                '$unidade', '$data', '$hora', $id_veiculo, '$numero_reg',
                $odometro, '$motorista', $quantidade, $id_posto
            )";

    if (!$con->query($sql)) {
        echo "Erro na query: " . $con->error;
    }
}


    echo "<p style='color: green;'>✅ Dados guardados com sucesso!</p>";
    unset($_SESSION['dados_convertidos']);
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8" />
    <title>Importar Ficheiro Excel</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f2f2f2;
            max-width: 1000px;
            margin: 30px auto;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            background: white;
            border-radius: 8px;
        }

        h2, h3 {
            text-align: center;
            color: #333;
        }

        form {
            text-align: center;
            margin-top: 20px;
        }

        input[type="file"], input[type="submit"], button {
            margin: 10px;
            padding: 10px 20px;
            font-size: 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        input[type="submit"], button {
            background-color: #007bff;
            color: white;
            transition: background-color 0.3s;
        }

        input[type="submit"]:hover, button:hover {
            background-color: #0056b3;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }

        th {
            background-color: #007bff;
            color: white;
        }

        td {
            background-color: #f9f9f9;
        }

        .voltar-btn {
            display: inline-block;
            margin-top: 30px;
            background-color: #6c757d;
        }

        .voltar-btn:hover {
            background-color: #5a6268;
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
                <th>Unidade</th>
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
                    <td><?= htmlspecialchars($linha['unidade']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <form method="post">
        <button type="submit" name="guardar">Guardar na Base de Dados</button>
    </form>
<?php endif; ?>

<form action="../html/index.php" method="get" style="text-align: center;">
    <button class="voltar-btn">Voltar ao Início</button>
</form>

</body>
</html>
