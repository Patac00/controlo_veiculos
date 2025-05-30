<?php
session_start();
include("../php/config.php");

// Verifica se existem cookies para preencher o formulário
if (isset($_COOKIE['lembrar_email'])) {
    $email_cookie = $_COOKIE['lembrar_email'];
}
if (isset($_COOKIE['lembrar_password'])) {
    $password_cookie = $_COOKIE['lembrar_password'];
}

if (isset($_POST['submit'])) {
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $password = $_POST['password'];

    $result = mysqli_query($con, "SELECT * FROM utilizadores WHERE email='$email'") or die("Erro na consulta");
    $row = mysqli_fetch_assoc($result);

    if ($row) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['nome'] = $row['nome'];
            $_SESSION['id_utilizador'] = $row['id_utilizador'];
            header("Location: ../html/index.php");
            exit();
        } else {
            $erro = "Palavra-passe incorreta. Por favor, tente novamente.";
        }
    } else {
        $erro = "Email não encontrado. Por favor, registe-se primeiro.";
    }
}

?>

<!DOCTYPE html>
<html lang="pt" class="light-style customizer-hide" dir="ltr" data-theme="theme-default" data-assets-path="../assets/" data-template="vertical-menu-template-free">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />
  <title>Login | Controlo de Veículos</title>
  <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
  <link rel="stylesheet" href="../assets/vendor/css/core.css" />
  <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
  <link rel="stylesheet" href="../assets/css/demo.css" />
  <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="../assets/vendor/css/pages/page-auth.css" />
  <script src="../assets/vendor/js/helpers.js"></script>
  <script src="../assets/js/config.js"></script>
</head>
<body>
  <div class="container-xxl">
    <div class="authentication-wrapper authentication-basic container-p-y">
      <div class="authentication-inner">
        <div class="card">
          <div class="card-body">
            <div class="app-brand justify-content-center">
              <span class="app-brand-logo demo">
                <!-- Logo Ambipombal -->
              </span>
              <span class="app-brand-text demo text-body fw-bolder">Controlo Veículos</span>
            </div>
            <h4 class="mb-2">Bem-vindo!</h4>
            <p class="mb-4">Introduza os seus dados para aceder à plataforma.</p>

            <?php if (!empty($erro)): ?>
              <div class='alert alert-danger text-center'><?= $erro ?></div>
            <?php endif; ?>

            <form id="formAuthentication" class="mb-3" action="" method="POST">
              <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="text" class="form-control" id="email" name="email" placeholder="Introduza o seu email" 
                      value="<?= isset($email_cookie) ? $email_cookie : '' ?>" required />
              </div>

              <div class="mb-3 form-password-toggle">
                <div class="d-flex justify-content-between">
                  <label class="form-label" for="password">Palavra-passe</label>
                  <a href="forgot_password.php"><small>Esqueceu-se?</small></a>
                </div>

                <div class="input-group input-group-merge">
                  <input type="password" id="password" class="form-control" name="password" placeholder="********"
                        value="<?= isset($password_cookie) ? $password_cookie : '' ?>" required />
                  <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                </div>

              </div>

              <div class="mb-3">
                <div class="form-check">
                <input class="form-check-input" type="checkbox" id="remember-me" name="remember" />
                <label class="form-check-label" for="remember-me">Manter sessão iniciada</label>
                </div>
              </div>

              <div class="mb-3">
                <button class="btn btn-primary d-grid w-100" type="submit" name="submit">Entrar</button>
              </div>

            </form>

            <p class="text-center">
              <span>Não tem conta?</span>
              <a href="register.php">Crie uma agora</a>
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="../assets/vendor/libs/jquery/jquery.js"></script>
  <script src="../assets/vendor/libs/popper/popper.js"></script>
  <script src="../assets/vendor/js/bootstrap.js"></script>
  <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
  <script src="../assets/vendor/js/menu.js"></script>
  <script src="../assets/js/main.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
  document.getElementById("formAuthentication").addEventListener("submit", function (e) {
    const form = this;
    const remember = document.getElementById("remember-me").checked;

    if (remember) {
      e.preventDefault(); // bloqueia envio para perguntar

      Swal.fire({
        title: 'Guardar dados de login?',
        text: 'Queres guardar o email e a palavra-passe neste dispositivo?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, guardar',
        cancelButtonText: 'Não'
      }).then((result) => {
        if (result.isConfirmed) {
          setCookie("lembrar_email", email, 30);
          setCookie("lembrar_password", password, 30);
        } else {
          // Apaga os cookies para garantir que não fica nada guardado
          setCookie("lembrar_email", "", -1);
          setCookie("lembrar_password", "", -1);
        }
        form.submit(); // faz submit mesmo se for "Não"
      });
    }
  });



  // Preenche os campos se existirem cookies
  window.onload = () => {
    document.getElementById("email").value = getCookie("lembrar_email");
    document.getElementById("password").value = getCookie("lembrar_password");
  };



  function setCookie(nome, valor, dias) {
    const d = new Date();
    d.setTime(d.getTime() + (dias * 24 * 60 * 60 * 1000));
    const expira = "expires=" + d.toUTCString();
    document.cookie = nome + "=" + valor + ";" + expira + ";path=/";
  }

  function getCookie(nome) {
    const nomeEQ = nome + "=";
    const ca = document.cookie.split(';');
    for (let i = 0; i < ca.length; i++) {
      let c = ca[i];
      while (c.charAt(0) === ' ') c = c.substring(1);
      if (c.indexOf(nomeEQ) === 0) return c.substring(nomeEQ.length, c.length);
    }
    return "";
  }

  // Preenche os campos se existirem cookies
  window.onload = () => {
    document.getElementById("email").value = getCookie("email");
    document.getElementById("password").value = getCookie("password");
  };

</script>

</body>
</html>
