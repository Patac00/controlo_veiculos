<?php 
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <title>Login</title>
</head>
<body>
    <div class="container">
        <div class="box form-box">
            <?php 
            include("php/config.php");
            if (isset($_POST['submit'])) {
                $email = mysqli_real_escape_string($con, $_POST['email']);
                $password = $_POST['password']; // Não criptografamos ainda para comparar depois

                // Verifica se o email existe na base de dados
                $result = mysqli_query($con, "SELECT * FROM utilizadores WHERE email='$email'") or die("Erro na consulta");
                $row = mysqli_fetch_assoc($result);

                if ($row) { // Verifica se as funções são iguais
                    if (password_verify($password, $row['password'])) {
                        // Login bem-sucedido
                        $_SESSION['nome'] = $row['nome'];
                        $_SESSION['id_utilizador'] = $row['id_utilizador'];
                        header("Location: dashboard.php"); // Redireciona para o dashboard
                        exit();
                    } else {
                        // Palavra-passe incorreta
                        echo "<div class='message'>
                                <p>Palavra-passe incorreta. Por favor, tente novamente.</p>
                                <a href='javascript:history.back()'><button class='btn'>Back</button></a>
                              </div>";
                    }
                } else {
                    // Email não encontrado
                    echo "<div class='message'>
                            <p>Email não encontrado. Por favor, registe-se primeiro.</p>
                            <a href='javascript:history.back()'><button class='btn'>Back</button></a>
                          </div>";
                }
            } 
            ?>
            <header>Login</header>
            <form action="" method="post">
                <div class="field input">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" autocomplete="on" required>
                </div>

                <div class="field input">
                    <label for="password">Palavra-Passe</label>
                    <input type="password" name="password" id="password" autocomplete="off" required>
                </div>

                <div class="field">
                    <input type="submit" class="btn" name="submit" value="Login" required>
                </div>
                <div class="links">
                    Não tens um conta? <a href="register.php">Regista-te agora!</a>
                </div>
            </form>
        </div>
        <?php 
    ?>
    </div>
</body>
</html>
