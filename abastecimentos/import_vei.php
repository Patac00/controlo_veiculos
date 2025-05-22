<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

require '../vendor/autoload.php'; // caminho para o autoload do Composer
use PhpOffice\PhpSpreadsheet\IOFactory;

include("../php/config.php");
$con->set_charset("utf8mb4");

function veiculoExiste($con, $matricula) {
    $sql = "SELECT 1 FROM veiculos WHERE Matricula = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("s", $matricula);
    $stmt->execute();
    $stmt->store_result();
    return $stmt->num_rows > 0;
}

if (isset($_FILES['excel_file'])) {
    $ficheiro = $_FILES['excel_file']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($ficheiro);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        // Ignora a primeira linha (cabeçalho)
        for ($i = 1; $i < count($rows); $i++) {
            $linha = $rows[$i];

            if (count($linha) < 5) continue;

            $matricula = trim($linha[0] ?? '');
            $descricao = trim($linha[1] ?? '');
            $empresa = trim($linha[2] ?? '');
            $tipo = trim($linha[3] ?? '');
            $grupo = trim($linha[4] ?? '');

            if ($matricula === '') continue;

            if (!veiculoExiste($con, $matricula)) {
                $sqlInsert = "INSERT INTO veiculos (Matricula, Descricao, Empresa, Tipo, Grupo) VALUES (?, ?, ?, ?, ?)";
                $stmt = $con->prepare($sqlInsert);
                $stmt->bind_param("sssss", $matricula, $descricao, $empresa, $tipo, $grupo);
                $stmt->execute();
            }
        }
        echo "Importação concluída!";
    } catch (Exception $e) {
        echo "Erro ao ler ficheiro Excel: " . $e->getMessage();
    }
} else {
    ?>
    <!DOCTYPE html>
    <html lang="pt">
    <head><meta charset="UTF-8"><title>Importar Veículos Excel</title></head>
    <body>
        <form action="" method="post" enctype="multipart/form-data">
            <label>Escolha o ficheiro Excel (.xlsx):</label>
            <input type="file" name="excel_file" accept=".xlsx" required>
            <input type="submit" value="Importar">
        </form>
    </body>
    </html>
    <?php
}

$con->close();
?>
