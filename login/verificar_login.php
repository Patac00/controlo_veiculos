<?php
session_start();
include("../php/config.php");

if (isset($_POST['submit'])) {
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $password = $_POST['password'];

    $result = mysqli_query($con, "SELECT * FROM utilizadores WHERE email='$email'") or die("Erro na consulta");
    $row = mysqli_fetch_assoc($result);

    if ($row) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['nome'] = $row['nome'];
            $_SESSION['id_utilizador'] = $row['id_utilizador'];
            header("Location: ../index.php"); // <- redireciona para a página principal
            exit();
        } else {
            $_SESSION['erro_login'] = "Palavra-passe incorreta.";
        }
    } else {
        $_SESSION['erro_login'] = "Email não encontrado.";
    }
    header("Location: index.php"); // redireciona de volta para o formulário de login
    exit();
}
?>
