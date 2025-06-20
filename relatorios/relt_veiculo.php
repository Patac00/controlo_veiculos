<?php 
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");

$veiculos = [];
$dados = null;
$medias = null;
$msg = "";

// Buscar veículos para o dropdown
$sql = "SELECT id_veiculo, matricula FROM veiculos ORDER BY matricula ASC";
$resultado = mysqli_query($con, $sql);
while ($row = mysqli_fetch_assoc($resultado)) {
    $veiculos[] = $row;
}

// Se foi selecionado um veículo (GET)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Buscar dados do veículo
    $query = "SELECT * FROM veiculos WHERE id_veiculo = $id";
    $result = mysqli_query($con, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $dados = mysqli_fetch_assoc($result);
    } else {
        $msg = "Veículo não encontrado.";
    }

    // Buscar médias de abastecimento
    $query2 = "SELECT * FROM medias_abastecimento WHERE id_veiculo = $id";
    $result2 = mysqli_query($con, $query2);
    if ($result2 && mysqli_num_rows($result2) > 0) {
        $medias = mysqli_fetch_assoc($result2);
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Relatório do Veículo</title>
</head>
<body>

<h3>Relatório por Veículo</h3>

<form action="" method="get">
    <label for="id_veiculo">Escolha o veículo:</label>
    <select name="id" id="id_veiculo" required>
        <option value="">-- Selecionar --</option>
        <?php foreach ($veiculos as $v): ?>
            <option value="<?= $v['id_veiculo'] ?>" <?= (isset($_GET['id']) && $_GET['id'] == $v['id_veiculo']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($v['matricula']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <br><br>
    <button type="submit">Ver Dados</button>
</form>

<?php if ($msg): ?>
    <p style="color:red;"><?= $msg ?></p>
<?php endif; ?>

<?php if ($dados): ?>
    <h2>Dados do Veículo</h2>
    <p><strong>Matrícula:</strong> <?= htmlspecialchars($dados['matricula']) ?></p>
    <p><strong>Descrição:</strong> <?= htmlspecialchars($dados['Descricao']) ?></p>
    <p><strong>Tipo:</strong> <?= htmlspecialchars($dados['Tipo']) ?></p>
    <p><strong>Grupo:</strong> <?= htmlspecialchars($dados['Grupo']) ?></p>
    <p><strong>KM Atual:</strong> <?= htmlspecialchars($dados['km_atual']) ?></p>
    <p><strong>Horas:</strong> <?= htmlspecialchars($dados['horas_atual']) ?></p>
    <p><strong>Estado:</strong> <?= htmlspecialchars($dados['estado']) ?></p>

    <h3>Médias de Abastecimento</h3>
    <?php if ($medias): ?>
        <p><strong>L/100km:</strong> <?= $medias['media_l_100km'] ?></p>
        <p><strong>L/100km (3 meses):</strong> <?= $medias['media_l_100km_3m'] ?></p>
        <p><strong>L/100km (12 meses):</strong> <?= $medias['media_l_100km_12m'] ?></p>

        <p><strong>L/hora:</strong> <?= $medias['media_l_hora'] ?></p>
        <p><strong>L/hora (3 meses):</strong> <?= $medias['media_l_hora_3m'] ?></p>
        <p><strong>L/hora (12 meses):</strong> <?= $medias['media_l_hora_12m'] ?></p>

        <?php if (
            $medias['media_l_100km'] == 0 || $medias['media_l_100km_3m'] == 0 || $medias['media_l_100km_12m'] == 0 ||
            $medias['media_l_hora'] == 0 || $medias['media_l_hora_3m'] == 0 || $medias['media_l_hora_12m'] == 0
        ): ?>
            <form method="post" action="../php/calcular_medias.php">
                <input type="hidden" name="id_veiculo" value="<?= $dados['id_veiculo'] ?>">
                <button type="submit">Calcular Médias</button>
            </form>
        <?php endif; ?>

    <?php else: ?>
        <p><strong>Não há médias registadas.</strong></p>
        <form method="post" action="calcular_medias.php">
            <input type="hidden" name="id_veiculo" value="<?= $dados['id_veiculo'] ?>">
            <button type="submit">Calcular Médias</button>
        </form>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>
