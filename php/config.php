<?php
$host = "localhost";
$user = "root";
$password = "";
$dbname = "controlo_veiculos";

// Criar ligação
$con = new mysqli($host, $user, $password, $dbname);

// Verificar ligação
if ($con->connect_error) {
    die("Erro de ligação: " . $con->connect_error);
}
?>
