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
    $sql = "SELECT lp.*, e.nome AS nome_empresa 
            FROM lista_postos lp 
            LEFT JOIN empresas e ON lp.empresa_id = e.empresa_id
            WHERE lp.nome LIKE '%$pesquisa%' 
            OR lp.local LIKE '%$pesquisa%' 
            OR lp.contacto LIKE '%$pesquisa%' 
            OR e.nome LIKE '%$pesquisa%'
            ORDER BY lp.nome ASC";
} else {
    $sql = "SELECT lp.*, e.nome AS nome_empresa 
            FROM lista_postos lp 
            LEFT JOIN empresas e ON lp.empresa_id = e.empresa_id
            ORDER BY lp.nome ASC";
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
    button, .btn {
      padding: 6px 10px;
      margin-left: 5px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      color: white;
      text-decoration: none;
      font-size: 14px;
    }
    .btn-editar {
      background-color: #007bff;
    }
    .btn-apagar {
      background-color: #dc3545;
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
  <script>
    function confirmarApagar(id, nome) {
      if(confirm('Tem a certeza que quer apagar o posto "' + nome + '"?')) {
        window.location.href = 'apagar_posto.php?id=' + id;
      }
    }
  </script>
</head>
<body>

<h2>Lista de Postos</h2>

<div style="max-width: 800px; margin: 0 auto 20px; display: flex; justify-content: space-between; align-items: center;">
  <form method="GET" action="" style="flex-grow: 1; display: flex;">
    <input type="text" name="pesquisa" placeholder="Pesquisar posto, local ou contacto" value="<?= htmlspecialchars($pesquisa ?? '') ?>" 
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
    <th>Unidade</th>
    <th>Empresa</th>
    <th>Capacidade</th>
    <th>Ações</th>
  </tr>
  <?php while ($row = mysqli_fetch_assoc($result)): ?>
    <tr>
      <td><?= htmlspecialchars($row['nome'] ?? '') ?></td>
      <td><?= htmlspecialchars($row['local'] ?? '') ?></td>
      <td><?= htmlspecialchars($row['contacto'] ?? '') ?></td>
      <td><?= htmlspecialchars($row['unidade'] ?? '') ?></td>
      <td><?= htmlspecialchars($row['nome_empresa'] ?? '') ?></td>
      <td><?= htmlspecialchars($row['capacidade'] ?? '') ?></td>
      <td>
        <a class="btn btn-editar" href="editar_posto.php?id=<?= $row['id_posto'] ?>">Editar</a>
        <button class="btn btn-apagar" onclick="confirmarApagar(<?= $row['id_posto'] ?>, '<?= addslashes($row['nome']) ?>')">Apagar</button>
      </td>
    </tr>
  <?php endwhile; ?>
</table>

<a class="back-btn" href="../html/index.php">← Voltar ao Início</a>

</body>
</html>