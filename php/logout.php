<?php
    session_start();
    session_unset(); //Remove todas as variáveis de sessão
    session_destroy(); // Destroi a sessão
    header("Location: ../login/login.php"); // Redireciona para a página de login
    exit();
?>