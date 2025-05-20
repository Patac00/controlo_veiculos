<?php 
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}
include("../php/config.php");
$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = mysqli_real_escape_string($con, $_POST['nome']);
    $local = mysqli_real_escape_string($con, $_POST['local']);
    $contacto = mysqli_real_escape_string($con, $_POST['contacto']);

    // Verificar se já existe o posto
    $check_sql = "SELECT * FROM lista_postos WHERE nome = '$nome' AND local = '$local' AND contacto = '$contacto'";
    $check_result = mysqli_query($con, $check_sql);

    if (mysqli_num_rows($check_result) > 0) {
        $msg = "Erro: Este posto já está registado.";
    } else {
        $sql = "INSERT INTO lista_postos (nome, local, contacto) VALUES ('$nome', '$local', '$contacto')";

        if (mysqli_query($con, $sql)) {
            $msg = "Registo inserido com sucesso.";
        } else {
            $msg = "Erro: " . mysqli_error($con);
        }
    }
}



?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <title>Inserir Posto</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f1f3f4;
      margin: 0;
      padding: 0;
    }

    .container {
      max-width: 500px;
      margin: 80px auto;
      background-color: #fff;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 0 15px rgba(0,0,0,0.1);
    }

    .header-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 10px;
    }

    h2 {
      color: #00693e;
      margin: 0;
    }

    .ver-lista-btn {
      background-color: #00693e;
      color: white;
      padding: 6px 12px;
      border-radius: 50%;
      text-decoration: none;
      font-size: 20px;
      font-weight: bold;
      display: inline-block;
      line-height: 1;
    }

    .ver-lista-btn:hover {
      background-color: #005632;
    }

    label {
      font-weight: bold;
      display: block;
      margin-top: 15px;
    }

    input[type="text"] {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      border: 1px solid #ccc;
      border-radius: 8px;
    }

    button {
      background-color: #00693e;
      color: white;
      padding: 10px 20px;
      margin-top: 20px;
      border: none;
      border-radius: 8px;
      width: 100%;
      cursor: pointer;
      font-size: 16px;
    }

    button:hover {
      background-color: #005632;
    }

    .msg {
      text-align: center;
      margin-top: 10px;
      font-weight: bold;
    }

    .back-btn {
      display: block;
      text-align: center;
      margin-top: 20px;
      text-decoration: none;
      color: #00693e;
      font-weight: bold;
    }

    .back-btn:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>

<div class="container">
  <div class="header-bar">
    <h2>Inserir Posto</h2>
    <a class="ver-lista-btn" href="ver_lista_postos.php">Ver Listagem</a>
  </div>

  <?php if ($msg): ?>
    <div class="msg"><?= $msg ?></div>
  <?php endif; ?>

  <form action="" method="POST">
    <label>Nome do Posto:</label>
    <input type="text" name="nome" required>

    <label>Local:</label>
    <input type="text" name="local" required>

    <label>Contacto:</label>
    <input type="text" name="contacto" required>

    <button type="submit">Guardar</button>
  </form>

  <a class="back-btn" href="../html/index.php">← Voltar ao Início</a>
</div>

</body>
</html>
