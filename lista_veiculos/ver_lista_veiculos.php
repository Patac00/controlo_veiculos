<?php 
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}
include("../php/config.php");

$pesquisa = "";
if (isset($_GET['pesquisa'])) {
    $pesquisa = mysqli_real_escape_string($con, $_GET['pesquisa']);
    $sql = "SELECT v.*, e.nome_empresa 
            FROM veiculos v
            LEFT JOIN empresas e ON v.empresa_atual_id = e.id_empresa
            WHERE v.matricula LIKE '%$pesquisa%' 
            OR v.marca LIKE '%$pesquisa%' 
            OR v.modelo LIKE '%$pesquisa%' 
            OR v.tipo_veiculo LIKE '%$pesquisa%' 
            OR e.nome_empresa LIKE '%$pesquisa%'
            ORDER BY v.matricula ASC";
} else {
    $sql = "SELECT v.*, e.nome_empresa 
            FROM veiculos v
            LEFT JOIN empresas e ON v.empresa_atual_id = e.id_empresa
            ORDER BY v.matricula ASC";
}

$result = mysqli_query($con, $sql);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8" />
  <title>Lista de Veículos</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f1f3f4;
      padding: 40px;
    }
    h2 {
      text-align: center;
      color: #00693e;
    }
    .search-container {
      max-width: 800px;
      margin: 0 auto 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    form {
      flex-grow: 1;
      display: flex;
    }
    input[type="text"] {
      flex-grow: 1;
      padding: 8px;
      border: 1px solid #ccc;
      border-radius: 6px 0 0 6px;
    }
    button {
      padding: 8px 12px;
      background-color: #00693e;
      color: white;
      border: none;
      border-radius: 0 6px 6px 0;
      cursor: pointer;
    }
    button:hover {
      background-color: #005632;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background-color: #fff;
      max-width: 800px;
      margin: 0 auto;
    }
    th, td {
      padding: 12px;
      border: 1px solid #ccc;
      text-align: left;
    }
    th {
      background-color: #00693e;
      color: white;
    }
    tr:nth-child(even) {
      background-color: #f9f9f9;
    }
    .back-btn, .add-btn {
      display: inline-block;
      margin: 20px auto 0;
      text-decoration: none;
      background-color: #00693e;
      color: white;
      padding: 10px 15px;
      border-radius: 8px;
      max-width: 800px;
      text-align: center;
    }
    .back-btn:hover, .add-btn:hover {
      background-color: #005632;
    }
    .add-btn {
      font-size: 22px;
      width: 30px;
      height: 30px;
      line-height: 28px;
      border-radius: 50%;
      font-weight: bold;
      text-align: center;
      margin-left: 15px;
    }
    .edit-btn {
      background-color: #007bff;
      color: white;
      padding: 5px 10px;
      border-radius: 6px;
      text-decoration: none;
    }
    .edit-btn:hover {
      background-color: #0056b3;
    }
  </style>
</head>
<body>

<h2>Lista de Veículos</h2>

<div class="search-container">
  <form method="GET" action="">
    <input type="text" name="pesquisa" placeholder="Pesquisar matrícula, marca, modelo, tipo ou empresa" value="<?= htmlspecialchars($pesquisa) ?>">
    <button type="submit">Pesquisar</button>
  </form>

  <a href="inserir_veiculo.php" title="Adicionar veículo" class="add-btn">+</a>
</div>

<table>
  <tr>
    <th>Matrícula</th>
    <th>Marca</th>
    <th>Modelo</th>
    <th>Tipo</th>
    <th>Empresa Atual</th>
    <th>KMs</th>
    <th>Estado</th>
    <th>Ações</th>
  </tr>
  <?php while ($row = mysqli_fetch_assoc($result)): ?>
    <tr>
      <td><?= htmlspecialchars($row['matricula']) ?></td>
      <td><?= htmlspecialchars($row['marca']) ?></td>
      <td><?= htmlspecialchars($row['modelo']) ?></td>
      <td><?= htmlspecialchars($row['tipo_veiculo']) ?></td>
      <td><?= htmlspecialchars($row['nome_empresa'] ?? 'Sem empresa') ?></td>
      <td><?= htmlspecialchars($row['km_atual']) ?></td>
      <td><?= htmlspecialchars($row['estado']) ?></td>
      <td>
        <a href="editar_veiculo.php?matricula=<?= urlencode($row['matricula']) ?>" class="edit-btn">Editar</a>
      </td>
    </tr>
  <?php endwhile; ?>
</table>

<a class="back-btn" href="../html/index.php">← Voltar ao Início</a>

</body>
</html>
