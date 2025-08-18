<?php
session_start();
include("../php/config.php");

$erro = "";

if (isset($_POST['submit'])) {
    $nome = mysqli_real_escape_string($con, $_POST['nome']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $password = $_POST['password'];

    // Verifica se o email já existe
    $check = mysqli_query($con, "SELECT * FROM utilizadores WHERE email='$email'");
    if (mysqli_num_rows($check) > 0) {
        $erro = "Email já registado. Por favor, faça login.";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // INSERT sem o campo cargo para deixar NULL
        mysqli_query($con, "INSERT INTO utilizadores (nome, email, password) VALUES ('$nome', '$email', '$hashed_password')")
            or die("Erro ao registar utilizador");

        $_SESSION['nome'] = $nome;
        $_SESSION['id_utilizador'] = mysqli_insert_id($con);
        $_SESSION['cargo'] = null;

        header("Location: ../html/index.php");
        exit();
    }
}
?>


<!DOCTYPE html>
<html lang="pt" class="light-style customizer-hide" dir="ltr" data-theme="theme-default" data-assets-path="../assets/" data-template="vertical-menu-template-free">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
  <title>Registo | Controlo de Veículos</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../assets/vendor/css/core.css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="../assets/vendor/css/pages/page-auth.css" />
</head>
<body>
  <div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
      <div class="authentication-inner">
        <div class="card">
          <div class="card-body">
            <div class="app-brand justify-content-center">
              <span class="app-brand-text fw-bolder">Controlo Veículos</span>
            </div>
            <h4 class="mb-2">Crie a sua conta 🚀</h4>
            <p class="mb-4">Preencha os dados para se registar.</p>

            <?php if (!empty($erro)): ?>
              <div class='alert alert-danger text-center'><?= $erro ?></div>
            <?php endif; ?>

            <form method="POST" id="formRegisto">
              <div class="mb-3">
                <label for="nome" class="form-label">Nome</label>
                <input type="text" class="form-control" id="nome" name="nome" placeholder="Introduza o seu nome" required />
              </div>
              <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" placeholder="Introduza o seu email" required />
              </div>
              <div class="mb-3">
                <label for="password" class="form-label">Palavra-passe</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="********" required />
              </div>

              <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="terms" name="terms" required />
                <label class="form-check-label" for="terms">Aceito a <a href="#">política de privacidade</a></label>
              </div>

              <button type="submit" name="submit" class="btn btn-primary d-grid w-100">Registar</button>
            </form>

            <p class="text-center mt-3">
              Já tem conta? <a href="login.php">Inicie sessão</a>
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../assets/vendor/js/bootstrap.js"></script>
</body>
</html>
