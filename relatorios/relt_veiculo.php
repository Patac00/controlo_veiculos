<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}
include("../php/config.php");

// Buscar veículos
$sql = "SELECT id_veiculo, matricula FROM veiculos ORDER BY matricula ASC";
$resultado = $con->query($sql);
$veiculos = [];
while ($row = $resultado->fetch_assoc()) {
    $veiculos[] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    


<h3>Relatório por Veículo</h3>

<form action="gerar_relatorio_veiculo.php" method="get">
  <label for="id_veiculo">Escolha o veículo:</label>
  <select name="id" id="id_veiculo" class="form-control" required>
    <option value="">-- Selecionar --</option>
    <?php foreach ($veiculos as $v): ?>
      <option value="<?= $v['id_veiculo'] ?>"><?= htmlspecialchars($v['matricula']) ?></option>
    <?php endforeach; ?>
  </select>
  <br>
  <button type="submit" class="btn btn-primary">
    <i class="bx bx-file"></i> Gerar Relatório
  </button>
</form>
</body>
</html>