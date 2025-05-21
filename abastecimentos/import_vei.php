<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

ini_set('memory_limit', '5120M'); // Dá até 5GB de memória
set_time_limit(0); // Sem limite de tempo de execução

include("../php/config.php"); // conexão $con

require '../vendor/autoload.php'; 
use PhpOffice\PhpSpreadsheet\IOFactory;

if (isset($_FILES['excel_file'])) {
    $fileTmpPath = $_FILES['excel_file']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($fileTmpPath);
        $sheet = $spreadsheet->getActiveSheet(); // Usa a única folha
        $rows = $sheet->toArray();

        // Ignora cabeçalho (linha 0)
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            $matricula = trim($row[0]);
            $descricao = trim($row[1]);
            $empresa = trim($row[2]);
            $tipo = trim($row[3]);
            $grupo = trim($row[4]);
            $relatorio = strtolower(trim($row[5])) === 'sim' ? 1 : 0;
            $criterio = trim($row[6]);
            $abastecimentos = (int)$row[7];
            $tot_kms_hrs = floatval(str_replace(',', '.', $row[8]));
            $tot_lts = floatval(str_replace(',', '.', $row[9]));
            $consumo = floatval(str_replace(',', '.', $row[10]));
            $custo_abast = floatval(str_replace(',', '.', $row[11]));

            // Evitar duplicados pela matrícula
            $check = $con->prepare("SELECT id FROM lista_veiculos WHERE matricula = ?");
            $check->bind_param("s", $matricula);
            $check->execute();
            $check->store_result();

            if ($check->num_rows == 0) {
                $sql = "INSERT INTO lista_veiculos (matricula, descricao, empresa, tipo, grupo, relatorio, criterio, abastecimentos, tot_kms_hrs, tot_lts, consumo, custo_abast)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $con->prepare($sql);
                $stmt->bind_param(
                    "sssssiiddddd",
                    $matricula,
                    $descricao,
                    $empresa,
                    $tipo,
                    $grupo,
                    $relatorio,
                    $criterio,
                    $abastecimentos,
                    $tot_kms_hrs,
                    $tot_lts,
                    $consumo,
                    $custo_abast
                );
                $stmt->execute();
            }
        }

        echo "Importação concluída com sucesso!";
    } catch (Exception $e) {
        echo "Erro ao processar o ficheiro: " . $e->getMessage();
    }

} else {
?>

<form action="" method="post" enctype="multipart/form-data">
    <label>Seleciona o ficheiro Excel (com uma única folha):</label><br>
    <input type="file" name="excel_file" accept=".xls,.xlsx" required>
    <input type="submit" value="Importar para a base de dados">
</form>

<?php
}
?>
