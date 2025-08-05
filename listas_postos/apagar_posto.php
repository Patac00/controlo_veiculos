<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID inválido.");
}

$id = (int)$_GET['id'];

// Verificar se o posto existe antes de apagar
$sql_check = "SELECT * FROM lista_postos WHERE id_posto = $id";
$res_check = $con->query($sql_check);

if ($res_check->num_rows === 0) {
    die("Posto não encontrado.");
}

// Apagar
$sql_delete = "DELETE FROM lista_postos WHERE id_posto = $id";
if ($con->query($sql_delete)) {
    header("Location: ver_lista_postos.php?msg=Posto apagado com sucesso");
} else {
    echo "Erro ao apagar: " . $con->error;
}
?>
