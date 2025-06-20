<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");

// Corrigido: garantir que o parâmetro se chama id_veiculo
if (!isset($_GET['id_veiculo']) || !is_numeric($_GET['id_veiculo'])) {
    exit("ID do veículo inválido.");
}

$id_veiculo = (int)$_GET['id_veiculo'];

// Função para calcular média l/100km ou l/hora
function calcularMedia($con, $id_veiculo, $tipo = 'km', $meses = null) {
    $filtroData = "";
    if ($meses !== null) {
        $filtroData = "AND data >= DATE_SUB(CURDATE(), INTERVAL $meses MONTH)";
    }

    $query = "
        SELECT 
            SUM(litros) AS total_litros,
            SUM(km_depois - km_antes) AS total_km,
            SUM(horas_depois - horas_antes) AS total_horas
        FROM abastecimentos
        WHERE id_veiculo = $id_veiculo $filtroData
    ";

    $res = mysqli_query($con, $query);
    $row = mysqli_fetch_assoc($res);

    $litros = (float)$row['total_litros'];
    $km = (float)$row['total_km'];
    $horas = (float)$row['total_horas'];

    if ($tipo === 'km') {
        return ($km > 0) ? round(($litros / $km) * 100, 2) : 0;
    } elseif ($tipo === 'hora') {
        return ($horas > 0) ? round(($litros / $horas), 2) : 0;
    }

    return 0;
}

// Cálculo das médias
$media_100km = calcularMedia($con, $id_veiculo, 'km');
$media_100km_3m = calcularMedia($con, $id_veiculo, 'km', 3);
$media_100km_12m = calcularMedia($con, $id_veiculo, 'km', 12);
$media_l_hora = calcularMedia($con, $id_veiculo, 'hora');
$media_l_hora_3m = calcularMedia($con, $id_veiculo, 'hora', 3);
$media_l_hora_12m = calcularMedia($con, $id_veiculo, 'hora', 12);

// Verificar se já existe entrada
$existe = mysqli_query($con, "SELECT id FROM medias_abastecimento WHERE id_veiculo = $id_veiculo");

if (mysqli_num_rows($existe) > 0) {
    $sql = "UPDATE medias_abastecimento SET
                media_l_100km = $media_100km,
                media_l_100km_3m = $media_100km_3m,
                media_l_100km_12m = $media_100km_12m,
                media_l_hora = $media_l_hora,
                media_l_hora_3m = $media_l_hora_3m,
                media_l_hora_12m = $media_l_hora_12m,
                ultima_atualizacao = NOW()
            WHERE id_veiculo = $id_veiculo";
} else {
    $sql = "INSERT INTO medias_abastecimento (
                id_veiculo, media_l_100km, media_l_100km_3m, media_l_100km_12m,
                media_l_hora, media_l_hora_3m, media_l_hora_12m
            ) VALUES (
                $id_veiculo, $media_100km, $media_100km_3m, $media_100km_12m,
                $media_l_hora, $media_l_hora_3m, $media_l_hora_12m
            )";
}

if (mysqli_query($con, $sql)) {
    echo "Médias atualizadas com sucesso.";
} else {
    echo "Erro ao atualizar: " . mysqli_error($con);
}
?>
