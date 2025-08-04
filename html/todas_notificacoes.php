<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");
$id_utilizador = $_SESSION['id_utilizador'];

// Filtros
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$page = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// SQL com filtros
$sql = "SELECT * FROM notificacoes WHERE utilizador_id = ?";
$params = [$id_utilizador];
$types = "i";

if (!empty($tipo)) {
    $sql .= " AND tipo = ?";
    $params[] = $tipo;
    $types .= "s";
}

$sql .= " ORDER BY data DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $con->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Total para paginação
$count_sql = "SELECT COUNT(*) FROM notificacoes WHERE utilizador_id = ?" . (!empty($tipo) ? " AND tipo = ?" : "");
$count_stmt = $con->prepare($count_sql);
if (!empty($tipo)) {
    $count_stmt->bind_param("is", $id_utilizador, $tipo);
} else {
    $count_stmt->bind_param("i", $id_utilizador);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total = $count_result->fetch_row()[0];
$total_pages = ceil($total / $limit);

// Marcar como lidas (se vier via GET)
if (isset($_GET['marcar_lidas']) && $_GET['marcar_lidas'] == 1) {
    $con->query("UPDATE notificacoes SET lida = 1 WHERE utilizador_id = $id_utilizador");
    header("Location: todas_notificacoes.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <title>Todas as Notificações</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="mb-0">Notificações</h2>
      <a href="?marcar_lidas=1" class="btn btn-outline-primary btn-sm">Marcar todas como lidas</a>
    </div>

    <!-- Filtros -->
    <form class="mb-3" method="GET">
      <div class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Tipo de Notificação:</label>
          <select name="tipo" class="form-select">
            <option value="">Todas</option>
            <option value="combustivel" <?= $tipo == 'combustivel' ? 'selected' : '' ?>>Combustível</option>
            <option value="manutencao" <?= $tipo == 'manutencao' ? 'selected' : '' ?>>Manutenção</option>
            <!-- Adiciona mais tipos -->
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary">Filtrar</button>
        </div>
      </div>
    </form>

    <!-- Lista -->
    <?php if ($result->num_rows > 0): ?>
      <ul class="list-group">
        <?php while ($row = $result->fetch_assoc()): ?>
          <li class="list-group-item <?= $row['lida'] ? '' : 'list-group-item-warning' ?>">
            <h5 class="mb-1"><?= htmlspecialchars($row['titulo']) ?> 
              <?= !$row['lida'] ? '<span class="badge bg-danger">Nova</span>' : '' ?>
            </h5>
            <p class="mb-1"><?= htmlspecialchars($row['descricao']) ?></p>
            <small class="text-muted"><?= date('d/m/Y H:i', strtotime($row['data'])) ?> — <?= htmlspecialchars($row['tipo']) ?></small>
          </li>
        <?php endwhile; ?>
      </ul>
    <?php else: ?>
      <div class="alert alert-info mt-4">Sem notificações.</div>
    <?php endif; ?>

    <!-- Paginação -->
    <?php if ($total_pages > 1): ?>
      <nav class="mt-4">
        <ul class="pagination">
          <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
              <a class="page-link" href="?pagina=<?= $i ?>&tipo=<?= $tipo ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>
        </ul>
      </nav>
    <?php endif; ?>

    <a href="../html/index.php" class="btn btn-secondary mt-4">Voltar ao Painel</a>
  </div>
</body>
</html>
