<?php
include("../php/config.php");
$con->set_charset("utf8mb4");

// Função para calcular médias
function calcularMedia($con, $id, $tipo, $inicio = null) {
    $filtro1 = $inicio ? "AND data_abastecimento >= ?" : "";
    $filtro2 = $inicio ? "AND data >= ?" : "";

    $sql = "
        SELECT data_abastecimento AS data, km_registados AS km, litros FROM abastecimentos WHERE id_veiculo = ? $filtro1
        UNION ALL
        SELECT data, odometro AS km, quantidade AS litros FROM bomba WHERE id_veiculo = ? $filtro2
        ORDER BY data ASC
    ";

    $stmt = $con->prepare($sql);
    if ($inicio) {
        $stmt->bind_param("isis", $id, $inicio, $id, $inicio);
    } else {
        $stmt->bind_param("ii", $id, $id);
    }
    $stmt->execute();
    $res = $stmt->get_result();

    $dados = [];
    while ($row = $res->fetch_assoc()) $dados[] = $row;

    if (count($dados) < 2) return null;

    $ini = $dados[0]['km'];
    $fim = $dados[count($dados) - 1]['km'];
    if ($fim <= $ini) return null;

    $litros = 0;
    for ($i = 1; $i < count($dados); $i++) {
        $litros += $dados[$i]['litros'];
    }

    $diferenca = $fim - $ini;
    return $tipo === 'maquina' ? $litros / $diferenca : ($litros / $diferenca) * 100;
}

// Buscar todos os veículos
$res = $con->query("SELECT id_veiculo, tipo FROM veiculos");

while ($v = $res->fetch_assoc()) {
    $id = $v['id_veiculo'];
    $tipo = $v['tipo'];

    $mG = calcularMedia($con, $id, $tipo);
    $m3 = calcularMedia($con, $id, $tipo, date('Y-m-d', strtotime('-3 months')));
    $m12 = calcularMedia($con, $id, $tipo, date('Y-m-d', strtotime('-12 months')));

    $l100 = $tipo !== 'maquina' ? round($mG, 2) : 0;
    $l100_3 = $tipo !== 'maquina' ? round($m3, 2) : 0;
    $l100_12 = $tipo !== 'maquina' ? round($m12, 2) : 0;

    $lh = $tipo === 'maquina' ? round($mG, 2) : 0;
    $lh_3 = $tipo === 'maquina' ? round($m3, 2) : 0;
    $lh_12 = $tipo === 'maquina' ? round($m12, 2) : 0;

    $stmt = $con->prepare("
        INSERT INTO medias_abastecimento (
            id_veiculo, media_l_100km, media_l_100km_3m, media_l_100km_12m,
            media_l_hora, media_l_hora_3m, media_l_hora_12m
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            media_l_100km = VALUES(media_l_100km),
            media_l_100km_3m = VALUES(media_l_100km_3m),
            media_l_100km_12m = VALUES(media_l_100km_12m),
            media_l_hora = VALUES(media_l_hora),
            media_l_hora_3m = VALUES(media_l_hora_3m),
            media_l_hora_12m = VALUES(media_l_hora_12m)
    ");

    $stmt->bind_param("idddddd", $id, $l100, $l100_3, $l100_12, $lh, $lh_3, $lh_12);
    $stmt->execute();
}

echo "✅ Médias atualizadas com sucesso!";
?>
