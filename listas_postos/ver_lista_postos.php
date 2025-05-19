<?php
include("../php/config.php");

$pesquisa = "";
if (isset($_GET['pesquisa'])) {
    $pesquisa = mysqli_real_escape_string($con, $_GET['pesquisa']);
    $sql = "SELECT * FROM lista_postos 
            WHERE nome LIKE '%$pesquisa%' 
            OR local LIKE '%$pesquisa%' 
            OR contacto LIKE '%$pesquisa%' 
            ORDER BY nome ASC";
} else {
    $sql = "SELECT * FROM lista_postos ORDER BY nome ASC";
}

$result = mysqli_query($con, $sql);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <title>Lista de Postos</title>
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

    form {
      text-align: center;
      margin-bottom: 20px;
    }

    input[type="text"] {
      padding: 8px;
      width: 250px;
      border: 1px solid #ccc;
      border-radius: 6px;
    }

    button {
      padding: 8px 12px;
      background-color: #00693e;
      color: white;
      border: none;
      border-radius: 6px;
      margin-left: 10px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background-color: #fff;
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

    .back-btn {
      display: inline-block;
      margin-top: 20px;
      text-decoration: none;
      background-color: #00693e;
      color: white;
      padding: 10px 15px;
      border-radius: 8px;
    }

    .back-btn:hover {
      background-color: #005632;
    }
  </style>
</head>
<body>

<h2>Lista de Postos</h2>

<div style="max-width: 800px; margin: 0 auto 20px; display: flex; justify-content: space-between; align-items: center;">
  <form method="GET" action="" style="flex-grow: 1; display: flex;">
    <input type="text" name="pesquisa" placeholder="Pesquisar posto, local ou contacto" value="<?= htmlspecialchars($pesquisa) ?>" 
      style="flex-grow: 1; padding: 8px; border: 1px solid #ccc; border-radius: 6px 0 0 6px;">
    <button type="submit" style="
      padding: 8px 12px; 
      background-color: #00693e; 
      color: white; 
      border: none; 
      border-radius: 0 6px 6px 0;
      cursor: pointer;
    ">Pesquisar</button>
  </form>

  <a href="inserir_lista.php" title="Adicionar posto" style="
    display: inline-block;
    background-color: #00693e;
    color: white;
    font-size: 16px;
    padding: 8px 15px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: bold;
    margin-left: 15px;
  ">Criar Posto</a>
</div>



<table>
  <tr>
    <th>Nome</th>
    <th>Local</th>
    <th>Contacto</th>
  </tr>
  <?php while ($row = mysqli_fetch_assoc($result)): ?>
    <tr>
      <td><?= htmlspecialchars($row['nome']) ?></td>
      <td><?= htmlspecialchars($row['local']) ?></td>
      <td><?= htmlspecialchars($row['contacto']) ?></td>
    </tr>
  <?php endwhile; ?>
</table>

<a class="back-btn" href="../html/index.php">← Voltar ao Início</a>

</body>
</html>
