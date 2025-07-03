<?php
function calcularMedia($con, $id, $tipo, $inicio = null) {
    $filtroData = $inicio ? "AND data_abastecimento >= ?" : "";
    $filtroDataBomba = $inicio ? "AND data >= ?" : "";

    $sql = "
        SELECT data_abastecimento AS data, km_registados AS km, litros FROM abastecimentos WHERE id_veiculo = ? $filtroData
        UNION ALL
        SELECT data, odometro AS km, quantidade AS litros FROM bomba WHERE id_veiculo = ? $filtroDataBomba
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
    while ($row = $res->fetch_assoc()) {
        $dados[] = $row;
    }

    if (count($dados) < 2) return "—";

    $valor_inicial = $dados[0]['km'];
    $valor_final = $dados[count($dados) - 1]['km'];
    if ($valor_final <= $valor_inicial) return "Erro";

    $litros_total = 0;
    for ($i = 1; $i < count($dados); $i++) {
        $litros_total += $dados[$i]['litros'];
    }

    $diferenca = $valor_final - $valor_inicial;

    if ($tipo === 'maquina') {
        $media = $litros_total / $diferenca;
        return round($media, 2) . " L/hora";
    } else {
        $media = ($litros_total / $diferenca) * 100;
        return round($media, 2) . " L/100km";
    }
}

?>
