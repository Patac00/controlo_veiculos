<?php
session_start();
include("../php/config.php");
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}
$id = $_SESSION['id_utilizador'];

// Buscar dados do utilizador e empresa para usar no nome da foto
$sql = "SELECT nome, email, empresa_id FROM utilizadores WHERE id_utilizador = $id";
$result = mysqli_query($con, $sql);
$user = mysqli_fetch_assoc($result);

// Buscar nome da empresa
$empresa_nome = '';
if ($user && $user['empresa_id']) {
    $sql_emp = "SELECT nome FROM empresas WHERE empresa_id = " . intval($user['empresa_id']);
    $res_emp = mysqli_query($con, $sql_emp);
    $empresa = mysqli_fetch_assoc($res_emp);
    $empresa_nome = $empresa ? preg_replace('/\s+/', '', strtolower($empresa['nome'])) : '';
}

// Buscar empresas para select — garante que traz a coluna unidades!
$sql_empresas = "SELECT empresa_id, nome, unidades FROM empresas ORDER BY nome";
$result_empresas = mysqli_query($con, $sql_empresas);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = mysqli_real_escape_string($con, $_POST['nome']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $empresa_id = intval($_POST['empresa_id']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];

    $foto_sql = null;
    $erro = '';

    // Atualizar nome da empresa para o novo selecionado (para nome da foto)
    if ($empresa_id) {
        $sql_emp = "SELECT nome FROM empresas WHERE empresa_id = $empresa_id";
        $res_emp = mysqli_query($con, $sql_emp);
        $empresa = mysqli_fetch_assoc($res_emp);
        $empresa_nome = $empresa ? preg_replace('/\s+/', '', strtolower($empresa['nome'])) : '';
    }

    // Foto perfil
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['foto_perfil']['tmp_name'];
        $fileName = $_FILES['foto_perfil']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($fileExtension, $allowedfileExtensions)) {
            $uploadFileDir = '../foto_perfil/';

            // Limpar nome para evitar problemas, sem espaços e tudo minúsculo
            $nome_clean = preg_replace('/[^a-z0-9]/', '', strtolower($nome));
            $empresa_clean = $empresa_nome;

            $newFileName = $nome_clean . '_' . $empresa_clean . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $foto_sql = "foto_perfil = '$newFileName'";
            } else {
                $erro = "Erro ao mover o ficheiro da foto de perfil.";
            }
        } else {
            $erro = "Tipo de ficheiro não permitido. Usa jpg, jpeg, png ou gif.";
        }
    }

    if (empty($erro)) {
        $campos = [
            "nome='$nome'",
            "email='$email'",
            "empresa_id=$empresa_id"
        ];

        if ($foto_sql !== null) {
            $campos[] = $foto_sql;
        }

        if (!empty($password)) {
            if ($password === $password_confirm) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $campos[] = "password='$hash'";
            } else {
                $erro = "As passwords não coincidem.";
            }
        }   

        if (empty($erro)) {
            $sql_update = "UPDATE utilizadores SET " . implode(", ", $campos) . " WHERE id_utilizador=$id";
            if (mysqli_query($con, $sql_update)) {
                $_SESSION['nome'] = $nome;
                $msg = "Perfil atualizado com sucesso.";
                // Atualizar user para mostrar dados atualizados no form
                $user['nome'] = $nome;
                $user['email'] = $email;
                $user['empresa_id'] = $empresa_id;
                if ($foto_sql !== null) {
                    $user['foto_perfil'] = $newFileName;
                }
            } else {
                $erro = "Erro ao atualizar o perfil.";
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="pt" class="light-style layout-menu-fixed" dir="ltr" data-theme="theme-default" data-assets-path="../assets/" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8" />
    <title>Editar Perfil | Controlo Veículos</title>
    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
</head>
<body>
<div class="layout-wrapper layout-content-navbar">
    <div class="layout-container">

        <div class="layout-page">

            <nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme" id="layout-navbar">
                <!-- Conteúdo navbar... -->
            </nav>

            <div class="content-wrapper">
                <div class="container-xxl flex-grow-1 container-p-y">
                    <h4 class="fw-bold py-3 mb-4">Editar Perfil</h4>
                    
                    <?php if(isset($msg)): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
                    <?php elseif(isset($erro)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
                    <?php endif; ?>

                    <form method="post" action="" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Foto de Perfil</label>
                            <input type="file" name="foto_perfil" accept="image/*" class="form-control" />
                            <?php if (!empty($user['foto_perfil'])): ?>
                                <img src="../foto_perfil/<?= htmlspecialchars($user['foto_perfil']) ?>" alt="Foto de Perfil" style="max-width: 100px; margin-top: 10px;">
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nome</label>
                            <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($user['nome']) ?>" required />
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required />
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Empresa</label>
                            <select name="empresa_id" class="form-select" required>
                                <option value="">-- Selecionar --</option>
                                <?php 
                                // Rewind para garantir que o while funciona bem mesmo após vários loops
                                mysqli_data_seek($result_empresas, 0);
                                while($empresa = mysqli_fetch_assoc($result_empresas)): ?>
                                    <option value="<?= $empresa['empresa_id'] ?>" <?= ($empresa['empresa_id'] == $user['empresa_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($empresa['nome']) ?>
                                        <?php if (!empty($empresa['unidades'])): ?>
                                            - Unidade <?= htmlspecialchars($empresa['unidades']) ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nova Password (deixe vazio para manter)</label>
                            <input type="password" name="password" class="form-control" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirmar Password</label>
                            <input type="password" name="password_confirm" class="form-control" />
                        </div>

                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </form>

                    <a href="../html/index.php" class="btn btn-secondary mt-3">Voltar</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../assets/vendor/libs/jquery/jquery.js"></script>
<script src="../assets/vendor/js/bootstrap.js"></script>
<script src="../assets/js/main.js"></script>
</body>
</html>
