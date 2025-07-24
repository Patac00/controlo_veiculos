<?php 
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../php/config.php");

$id_user = $_SESSION['id_utilizador'];

// Buscar empresa do utilizador
$sql = "SELECT empresa_id FROM utilizadores WHERE id_utilizador = ?";
$stmt = $con->prepare($sql);
$stmt->bind_param("i", $id_user);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$empresa_id = $row['empresa_id'] ?? null;
$stmt->close();

// Para evitar erro, declaramos $unidades vazio
$unidades = [];

// Como não tens a tabela 'unidades', não vamos buscar nada aqui

// Stock combustível por unidade (vai ficar vazio)
$nivel_combustivel_unidades = [];

// Buscar postos e stocks para a empresa
$postos = [];
if ($empresa_id) {
  $sql = "
      SELECT 
          p.id_posto, 
          p.nome, 
          p.capacidade,
          p.unidade,
          p.local,
          COALESCE(SUM(m.litros), 0) AS litros
      FROM lista_postos p
      LEFT JOIN movimentos_stock m ON m.id_posto = p.id_posto AND m.empresa_id = ?
      WHERE p.empresa_id = ?
      GROUP BY p.id_posto, p.nome, p.capacidade, p.unidade, p.local
  ";

    $stmt = $con->prepare($sql);
    $stmt->bind_param("ii", $empresa_id, $empresa_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $postos[] = $row;
    }
    $stmt->close();
}

// Preço litro gasóleo (sem unidades, só o último geral)
$preco = 'Erro -_-';
$sql = "SELECT preco_litro FROM fornecimentos_bomba WHERE tipo_combustivel = 'Gasóleo' ORDER BY data DESC LIMIT 1";
$stmt = $con->prepare($sql);
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        $preco = number_format($row['preco_litro'], 2, ',', ' ') . ' €';
    }
    $stmt->close();
}

// Veículos ativos
$total_ativos = 0;
if ($empresa_id) {
    $sql = "SELECT COUNT(*) AS total FROM veiculos WHERE estado = 'Ativo' AND empresa_atual_id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("i", $empresa_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $total_ativos = $row['total'] ?? 0;
    $stmt->close();
}

// Veículos em manutenção
$veiculos_manutencao = 0;
if ($empresa_id) {
    $sql = "SELECT COUNT(*) AS total FROM veiculos WHERE estado = 'Manutenção' AND empresa_atual_id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("i", $empresa_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $veiculos_manutencao = $row['total'] ?? 0;
    $stmt->close();
}

// Capacidade total (exemplo fixo)
$capacidade_total = 10000;

// Total abastecimentos do mês
$total_mes = 0;
if ($empresa_id) {
    $sql = "SELECT COUNT(*) AS total FROM abastecimentos WHERE MONTH(data_abastecimento) = MONTH(CURDATE()) AND YEAR(data_abastecimento) = YEAR(CURDATE()) AND empresa_id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("i", $empresa_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $total_mes = $row['total'] ?? 0;
    $stmt->close();
}

// Mês atual em português
setlocale(LC_TIME, 'pt_PT.UTF-8');
$formatter = new IntlDateFormatter('pt_PT', IntlDateFormatter::LONG, IntlDateFormatter::NONE);
$formatter->setPattern('MMMM');
$mes_atual = ucfirst($formatter->format(new DateTime()));

// Dados JSON para uso em JS
$dados_unidades_json = json_encode($nivel_combustivel_unidades);
$capacidade_total_js = (int)$capacidade_total;

?>


<!DOCTYPE html>
<html
  lang="en"
  class="light-style layout-menu-fixed"
  dir="ltr"
  data-theme="theme-default"
  data-assets-path="../assets/"
  data-template="vertical-menu-template-free"
>
  <head>
    <meta charset="utf-8" />
    <meta
      name="viewport"
      content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"
    />

    <title> Dashboard | Controlo Veiculos</title>
    <meta name="description" content="" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Ambipombal -->
    <link rel="icon" type="image/x-icon" href="../assets/img/ambipombal/ambipombal.ico" />
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet"
    />
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />


    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />
    <script src="../assets/vendor/js/helpers.js"></script>
    <script>
  const dadosUnidades = <?php echo json_encode($nivel_combustivel_unidades); ?>;
  const nomesUnidades = <?php echo json_encode(array_keys($nivel_combustivel_unidades)); ?>;
  const capacidadeTotal = <?php echo (int)$capacidade_total; ?>;
</script>
      <script src="../assets/js/config.js"></script>
        <style>
        body { font-family: Arial, sans-serif; margin: 20px; }  
        .box { border: 1px solid #ccc; padding: 15px; margin-bottom: 10px; width: 300px; }
.bg-verde {
  background-color: #28a745 !important;
}

.bg-laranja {
  background-color: #fd7e14 !important;
}

.bg-vermelho {
  background-color: #dc3545 !important;
}

.progress {
  background-color: #e9ecef;
  border-radius: 12px;
  overflow: hidden;
}

.progress-bar {
  font-weight: 600;
  color: #fff;
  text-align: center;
  border-radius: 12px;
  transition: width 0.6s ease;
  background-image: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(0, 0, 0, 0.05));
}

    </style>
  </head>

  <body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">
      <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
  <div class="app-brand demo">
    <a href="index.php" class="app-brand-link">
      <span class="app-brand-logo demo">
        <img src="../assets/img/ambipombal/ambipombal.png" alt="Logo" style="height: 35px;">
      </span>
      <span class="app-brand-text demo menu-text fw-bolder ms-2">Ambipombal</span>
    </a>

    <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
      <i class="bx bx-chevron-left bx-sm align-middle"></i>
    </a>
  </div>

  <div class="menu-inner-shadow"></div>

  <ul class="menu-inner py-1">
    <!-- Dashboard -->
    <li class="menu-item active">
      <a href="index.php" class="menu-link">
        <i class="menu-icon tf-icons bx bx-home-circle"></i>
        <div data-i18n="Analytics">Dashboard</div>
      </a>
    </li>

    <li class="menu-header small text-uppercase">
      <span class="menu-header-text">Abastecimentos</span>
    </li>

    <li class="menu-item">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon tf-icons bx bx-lock-open-alt"></i>
        <div data-i18n="Authentications">Abastecer</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item">
          <a href="../abastecimentos/lista_abastecimento.php" class="menu-link">
            <div data-i18n="Basic">Lista de Abastecimentos</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="../abastecimentos/registar_abast.php" class="menu-link">
            <div data-i18n="Basic">Inserir Abastecimento</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="../abastecimentos/importar_excel.php" class="menu-link">
            <div data-i18n="Basic">Importar Bomba</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="../abastecimentos/import_vei.php" class="menu-link">
            <div data-i18n="Basic">Veículo</div>
          </a>
        </li>
      </ul>
    </li>

    <li class="menu-header small text-uppercase">
      <span class="menu-header-text">Veículos</span>
    </li>

    <li class="menu-item">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon tf-icons bx bx-lock-open-alt"></i>
        <div data-i18n="Authentications">Veículos</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item">
          <a href="../lista_veiculos/ver_lista_veiculos.php" class="menu-link">
            <div data-i18n="Basic">Lista de Veículos</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="../lista_veiculos/inserir_veiculo.php" class="menu-link">
            <div data-i18n="Basic">Inserir Veículo</div>
          </a>
        </li>
      </ul>
    </li>

    <li class="menu-header small text-uppercase">
      <span class="menu-header-text">Listas de Postos</span>
    </li>

    <li class="menu-item">
      <a href="javascript:void(0);" class="menu-link menu-toggle">
        <i class="menu-icon tf-icons bx bx-lock-open-alt"></i>
        <div data-i18n="Authentications">Listas de postos</div>
      </a>
      <ul class="menu-sub">
        <li class="menu-item">
          <a href="../listas_postos/ver_lista_postos.php" class="menu-link">
            <div data-i18n="Basic">Ver Lista</div>
          </a>
        </li>
        <li class="menu-item">
          <a href="../listas_postos/inserir_lista.php" class="menu-link">
            <div data-i18n="Basic">Inserir Lista</div>
          </a>
        </li>
      </ul>
    </li>

     <div class="mt-auto p-3">
    <a href="../php/logout.php" class="btn btn-danger w-100">
      <i class="bx bx-log-out"></i> Logout
    </a>
  </div>
  </ul>
</aside>
        <!-- / Menu -->
        <div class="layout-page">
          <nav
            class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme"
            id="layout-navbar"
            >
            <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
              <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
                <i class="bx bx-menu bx-sm"></i>
              </a>
            </div>
              <ul class="navbar-nav flex-row align-items-center ms-auto">
                <!-- User -->
                <li class="nav-item navbar-dropdown dropdown-user dropdown">
                  <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <div class="avatar avatar-online">
                      <img src="../assets/img/avatars/1.png" alt class="w-px-40 h-auto rounded-circle" />
                    </div>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                      <a class="dropdown-item" href="#">
                        <div class="d-flex">
                          <div class="flex-shrink-0 me-3">
                            <div class="avatar avatar-online">
                              <img src="../assets/img/avatars/1.png" alt class="w-px-40 h-auto rounded-circle" />
                            </div>
                          </div>
                          <div class="flex-grow-1">
                            <span class="fw-semibold d-block"><?= $_SESSION['nome'] ?? 'Sem nome' ?></span>
                            <small class="text-muted"><?= $_SESSION['cargo'] ?? 'Sem cargo' ?></small>
                          </div>
                        </div>
                      </a>
                    </li>
                    <li>
                      <div class="dropdown-divider"></div>
                    </li>
                    <li>
                      <a class="dropdown-item" href="../perfil/editar_perfil.php">
                        <i class="bx bx-user me-2"></i>
                        <span class="align-middle">Meu Perfil</span>
                      </a>
                    </li>
                    <!--<li>
                      <a class="dropdown-item" href="#">
                        <i class="bx bx-cog me-2"></i>
                        <span class="align-middle">Settings</span>
                      </a>
                    </li>-->
                    <li>
                      <div class="dropdown-divider"></div>
                    </li>
                    <li>
                      <a href="../php/logout.php" class="btn btn-danger w-100">
                        <i class="bx bx-log-out"></i> Logout
                      </a>
                    </li>
                  </ul>
                </li>
              </ul>
          </nav>
          <div class="content-wrapper">
          <div class="container-xxl flex-grow-1 container-p-y">
          <div class="row">

        <div class="row mb-4">
      <!-- Card 1 - Veículos Ativos -->
            <div class="col-md-4 mb-4">
              <div class="card">
                <div class="card-body">
                  <div class="card-title d-flex align-items-start justify-content-between">
                    <div class="avatar flex-shrink-0">
                      <img src="../assets/img/icons/unicons/chart-success.png" alt="chart success" class="rounded" />
                    </div>
                    <div class="dropdown">
                      <button class="btn p-0" type="button" id="cardOpt1" data-bs-toggle="dropdown">
                        <i class="bx bx-dots-vertical-rounded"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="../lista_veiculos/ver_lista_veiculos.php">+ detalhes</a>
                      </div>
                    </div>
                  </div>
                  <span class="fw-semibold d-block mb-1">Veículos Ativos</span>
                  <h3 class="card-title mb-2"><?= $total_ativos ?></h3>
                  <small class="text-info fw-semibold"><i class="bx bx-car"></i> Frota ativa e registada</small>
                </div>
              </div>
            </div>

            <!-- Card 2 - Abastecimentos -->
            <div class="col-md-4 mb-4">
              <div class="card">
                <div class="card-body">
                  <div class="card-title d-flex align-items-start justify-content-between">
                    <div class="avatar flex-shrink-0">
                      <img src="../assets/img/icons/unicons/chart-success.png" alt="chart success" class="rounded" />
                    </div>
                    <div class="dropdown">
                      <button class="btn p-0" type="button" id="cardOpt2" data-bs-toggle="dropdown">
                        <i class="bx bx-dots-vertical-rounded"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="#">Ver mais</a>
                      </div>
                    </div>
                  </div>
                  <span class="fw-semibold d-block mb-1">Abastecimentos este mês</span>
                  <h3 class="card-title mb-2"><?= $total_mes ?></h3>
                  <small class="text-muted"><?= ucfirst($mes_atual) ?></small>
                </div>
              </div>
            </div>

            <!-- Card 3 - Em Manutenção -->
            <div class="col-md-4 mb-4">
              <div class="card">
                <div class="card-body">
                  <div class="card-title d-flex align-items-start justify-content-between">
                    <div class="avatar flex-shrink-0">
                      <img src="../assets/img/icons/unicons/wallet-info.png" alt="wallet info" class="rounded" />
                    </div>
                    <div class="dropdown">
                      <button class="btn p-0" type="button" id="cardOpt3" data-bs-toggle="dropdown">
                        <i class="bx bx-dots-vertical-rounded"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="#">+ Detalhes</a>
                      </div>
                    </div>
                  </div>
                  <span class="fw-semibold d-block mb-1">Veículos em manutenção</span>
                  <h3 class="card-title mb-2"><?= $veiculos_manutencao ?></h3>
                  <small class="text-warning fw-semibold"><i class="bx bx-wrench"></i> Em manutenção</small>
                </div>
              </div>
            </div>
          </div>

         <div class="container mt-5">
          <h1 class="mb-4">Postos Associados</h1>
          <?php if (count($postos) === 0): ?>
            <div class="alert alert-warning">Não foram encontrados postos para a sua unidade.</div>
          <?php else: ?>
            <div class="row">
              <?php foreach ($postos as $posto): 
                $percentagem = ($posto['litros'] && $posto['capacidade']) ? round(($posto['litros'] / $posto['capacidade']) * 100, 1) : 0;
                $percentagem = min(100, max(0, $percentagem));
                $cor = ($percentagem > 70) ? 'bg-success' : (($percentagem > 30) ? 'bg-warning' : 'bg-danger');
              ?>
                <div class="col-md-4 mb-4">
                  <div class="card shadow-sm h-100">
                    <div class="card-body d-flex flex-column justify-content-between">
                      <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                          <h5 class="mb-0"><?= htmlspecialchars($posto['nome']) ?></h5>
                          <a class="btn btn-sm btn-outline-primary" href="../abastecimentos/fornecer_comb.php?posto=<?= urlencode($posto['nome']) ?>">Fornecer</a>
                        </div>

                        <div class="progress mb-2" style="height: 24px; border-radius: 12px; overflow: hidden;">
                          <div class="progress-bar <?= $cor ?>" style="width: <?= $percentagem ?>%; min-width: 40px; font-weight: 600; border-radius: 12px; transition: width 0.6s ease; background-image: linear-gradient(135deg, rgba(255,255,255,0.15), rgba(0,0,0,0.05));">
                            <?= $percentagem ?>%
                          </div>
                        </div>

                        <small class="text-muted">
                          <?= $percentagem ?>% da capacidade total (<?= number_format($posto['capacidade'], 0, ',', '.') ?> L)
                        </small>
                      </div>
                      <div class="mt-3 small text-muted">
                        <?= htmlspecialchars($posto['tipo_combustivel'] ?? 'N/A') ?> · <?= number_format($posto['litros'] ?? 0, 2, ',', '.') ?> L / <?= $posto['capacidade'] ?? 0 ?> L
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <div class="row mt-4">
            <!-- Relatórios -->
            <div class="col-md-6 mb-4">
              <div class="card h-100">
                <div class="card-body d-flex flex-column justify-content-center align-items-center">
                  <h5 class="mb-3">Relatórios</h5>
                  <a href="../relatorios/relt_mensal.php" class="btn btn-primary mb-2 w-100">
                    <i class="bx bx-calendar"></i> Mensal
                  </a>
                  <a href="../relatorios/relt_veiculo.php" class="btn btn-secondary w-100">
                    <i class="bx bx-car"></i> Por Veículo
                  </a>
                </div>
              </div>
            </div>

            <!-- Preço Gasóleo -->
            <div class="col-md-6 mb-4">
              <div class="card h-100 shadow rounded-4 border-0">
                <div class="card-body d-flex flex-column justify-content-center align-items-center text-center">
                  <div class="mb-3 text-primary">
                    <i class="fas fa-gas-pump fa-2x"></i>
                  </div>
                  <h6 class="text-muted mb-1">Preço Litro Gasóleo</h6>
                  <h3 class="fw-bold text-dark"><?= $preco ?></h3>
                </div>
              </div>
            </div>
          </div>
        </div>

      <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/dashboards-analytics.js"></script>
    <script async defer src="https://buttons.github.io/buttons.js"></script>
    <script>
      const dadosUnidades = <?= $dados_unidades_json ?>;
      const capacidadeTotal = <?= $capacidade_total_js ?>;

      const nomesUnidades = Object.keys(dadosUnidades);
      let indiceAtual = 0;

      const unidadeNome = document.getElementById('unidadeNome');
      const nivelLitros = document.getElementById('nivelLitros');
      const percentagemLitros = document.getElementById('percentagemLitros');
      const progressBar = document.getElementById('progressBar');
      const btnPrev = document.getElementById('btnPrev');
      const btnNext = document.getElementById('btnNext');
    </script>
    <script>
const postos = <?= json_encode($postos) ?>;
const capacidadeTotal = 10000; // ajustar se necessário
let indexAtual = 0;

function mostrarPosto(i) {
  if (postos.length === 0) return;

  const posto = postos[i];
  document.getElementById('posto-nome').textContent = posto.nome;
  document.getElementById('posto-unidade').textContent = posto.unidade;
  document.getElementById('posto-local').textContent = posto.local;

  // Para simplificar, tipo_combustivel e litros estão hardcoded - precisas adaptar conforme dados reais
  // Exemplo:
  const tipo = "Gasóleo"; // substituir pelo valor correto se disponível
  const litros = 5000;    // substituir pelo valor correto se disponível

  document.getElementById('posto-tipo').textContent = tipo;
  document.getElementById('posto-litros').textContent = litros;

  const percentagem = ((litros / capacidadeTotal) * 100).toFixed(1);
  document.getElementById('posto-percentagem').textContent = percentagem;

  // Link para a página fornecer_comb.php com o id_posto
  document.getElementById('fornecer-link').href = `fornecer_comb.php?posto_id=${posto.id_posto}`;
}

function anteriorPosto() {
  if (indexAtual > 0) {
    indexAtual--;
    mostrarPosto(indexAtual);
  }
}

function proximoPosto() {
  if (indexAtual < postos.length - 1) {
    indexAtual++;
    mostrarPosto(indexAtual);
  }
}

// Mostrar o primeiro posto ao carregar
mostrarPosto(indexAtual);
</script>
  </body>
</html>
