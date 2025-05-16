<?php
session_start();
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: index.php");
    exit();
}

include("php/config.php"); // Conexão à base de dados

if (isset($_POST['submit'])) {
    // Validação e preparação dos dados do formulário
    $nome = mysqli_real_escape_string($con, $_POST['nome']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $password = mysqli_real_escape_string($con, $_POST['password']);
    $hashed_password = password_hash($password, PASSWORD_DEFAULT); // Criptografa a password

    // Verificar se o email já existe
    $verify_query = mysqli_query($con, "SELECT email FROM utilizadores WHERE email='$email'");
    
    if (mysqli_num_rows($verify_query) > 0) {
        // Email duplicado encontrado
        $message = "Email já registado. Por favor, use outro.";
    } else {
        // Inserir os dados no banco
        $query = "INSERT INTO utilizadores (nome, email, password) VALUES ('$nome', '$email', '$hashed_password')";
        
        if (mysqli_query($con, $query)) {
            header("Location: index.php"); // Redireciona para home.html se o registo for bem-sucedido
            exit();
           // $message = "Registo efetuado com sucesso!";
        } else {
            // Mostrar erro de SQL
            $message = "Erro ao registar: " . mysqli_error($con);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <title>Register</title>
</head>
<body>
    <div class="container">
        <div class="box form-box">
            <?php 
            if (isset($message)) {
                echo "<div class='message'>
                        <p>$message</p>
                      </div>";
            }
            ?>
            <header>Sign Up</header>
            <form action="" method="post">
                <div class="field input">
                    <label for="nome">Username</label>
                    <input type="text" name="nome" id="nome" autocomplete="off" required>
                </div>

                <div class="field input">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" autocomplete="off" required>
                </div>
                <div class="field input">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" autocomplete="off" required>
                </div>

                <div class="field">
                    <input type="submit" class="btn" name="submit" value="Register" required>
                </div>
                <div class="links">
                    Already a member? <a href="index.php">Sign In</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
