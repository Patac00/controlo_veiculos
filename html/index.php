<?php 
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}
include("../php/config.php");

// Contar veículos ativos
$sql = "SELECT COUNT(*) AS total_ativos FROM veiculos WHERE estado = 'Ativo'";
$result = mysqli_query($con, $sql);
$row = mysqli_fetch_assoc($result);
$total_ativos = $row['total_ativos'] ?? 0;

// Contar veículos em manutenção
$sql = "SELECT COUNT(*) AS total_manutencao FROM veiculos WHERE estado = 'Manutenção'";
$result = mysqli_query($con, $sql);
$row = mysqli_fetch_assoc($result);
$veiculos_manutencao = $row['total_manutencao'] ?? 0;

// Buscar nível de combustível da Redinha
$query = "SELECT litros FROM stock_combustivel 
          WHERE localizacao = 'Redinha' 
          LIMIT 1";
$res = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($res);
$nivel_combustivel = $row['litros'] ?? 0;

// Capacidade total da Redinha
$capacidade_total = 10000; // ajusta conforme necessário
$percentagem = ($nivel_combustivel / $capacidade_total) * 100;

// Definir cor
if ($percentagem > 50) {
    $cor = "#00BFFF";  // Azul
} elseif ($percentagem > 35) {
    $cor = "#FFFF00";  // Amarelo
} else {
    $cor = "#FF0000";  // Vermelho
}
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

    <!-- Ambipombal -->
    <link rel="icon" type="image/x-icon" href="../assets/img/ambipombal/ambipombal.ico" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
      rel="stylesheet"
    />

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

    <!-- Core CSS -->
    <link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
    <link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

    <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />

    <!-- Page CSS -->

    <!-- Helpers -->
    <script src="../assets/vendor/js/helpers.js"></script>

    <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
    <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
    <script src="../assets/js/config.js"></script>

        <style>
        body { font-family: Arial, sans-serif; margin: 20px; }  
        .box { border: 1px solid #ccc; padding: 15px; margin-bottom: 10px; width: 300px; }
        .progress-bar {
            background-color: #ddd;
            border-radius: 5px;
            overflow: hidden;
            height: 20px;
            width: 100%;
        }
        .progress {
            background-color: #4caf50;
            height: 100%;
            width: 0;
            transition: width 0.5s ease;
        }

        
    </style>
  </head>

  <body>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
      <div class="layout-container">

        <!-- Menu -->

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

        <!-- Layout container -->
        <div class="layout-page">
          <!-- Navbar -->
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
                            <span class="fw-semibold d-block">John Doe</span>
                            <small class="text-muted">Admin</small>
                          </div>
                        </div>
                      </a>
                    </li>
                    <li>
                      <div class="dropdown-divider"></div>
                    </li>
                    <li>
                      <a class="dropdown-item" href="#">
                        <i class="bx bx-user me-2"></i>
                        <span class="align-middle">My Profile</span>
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item" href="#">
                        <i class="bx bx-cog me-2"></i>
                        <span class="align-middle">Settings</span>
                      </a>
                    </li>
                    <li>
                      <a class="dropdown-item" href="#">
                        <span class="d-flex align-items-center align-middle">
                          <i class="flex-shrink-0 bx bx-credit-card me-2"></i>
                          <span class="flex-grow-1 align-middle">Billing</span>
                          <span class="flex-shrink-0 badge badge-center rounded-pill bg-danger w-px-20 h-px-20">4</span>
                        </span>
                      </a>
                    </li>
                    <li>
                      <div class="dropdown-divider"></div>
                    </li>
                    <li>
                      <a class="dropdown-item" href="auth-login-basic.php">
                        <i class="bx bx-power-off me-2"></i>
                        <span class="align-middle">Log Out</span>
                      </a>
                    </li>
                  </ul>
                </li>
                <!--/ User -->
              </ul>
          </nav>
          <!-- / Navbar -->

          
          <!-- Content wrapper -->
          <div class="content-wrapper">
            <!-- Content -->

          <div class="container-xxl flex-grow-1 container-p-y">
          <div class="row">
        <!-- Card 1 -->
        <div class="col-md-4 mb-4">
          <div class="card">
            <div class="card-body">
              <div class="card-title d-flex align-items-start justify-content-between">
                <div class="avatar flex-shrink-0">
                  <img src="../assets/img/icons/unicons/chart-success.png" alt="chart success" class="rounded" />
                </div>
                <div class="dropdown">
                  <button class="btn p-0" type="button" id="cardOpt1" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-dots-vertical-rounded"></i>
                  </button>
                  <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt1">
                    <a class="dropdown-item" href="../lista_veiculos\ver_lista_veiculos.php">+ detalhes</a>
                  </div>
                </div>
              </div>
              <span class="fw-semibold d-block mb-1">Nº Veículos Ativos</span>
              <h3 class="card-title mb-2"><?= $total_ativos ?></h3>
              <small class="text-info fw-semibold">
        <i class="bx bx-car"></i> Frota ativa e registada
      </small>
            </div>
          </div>
        </div>


        <!-- Card 2 -->
        <div class="col-md-4 mb-4">
          <div class="card">
            <div class="card-body">
              <div class="card-title d-flex align-items-start justify-content-between">
                <div class="avatar flex-shrink-0">
                  <img src="../assets/img/icons/unicons/chart-success.png" alt="chart success" class="rounded" />
                </div>
                <div class="dropdown">
                  <button class="btn p-0" type="button" id="cardOpt2" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-dots-vertical-rounded"></i>
                  </button>
                  <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt2">
                    <a class="dropdown-item" href="#">View More</a>
                    <a class="dropdown-item" href="#">Delete</a>
                  </div>
                </div>
              </div>
              <span class="fw-semibold d-block mb-1">Nº Abastecimentos este mês</span>
              <h3 class="card-title mb-2"></h3>
              <small class="text-success fw-semibold"><i class="bx bx-up-arrow-alt"></i>%</small>
            </div>
          </div>
        </div>

        <!-- Card 3 -->
        <div class="col-md-4 mb-4">
          <div class="card">
            <div class="card-body">
              <div class="card-title d-flex align-items-start justify-content-between">
                <div class="avatar flex-shrink-0">
                  <img src="../assets/img/icons/unicons/wallet-info.png" alt="Credit Card" class="rounded" />
                </div>
                <div class="dropdown">
                  <button class="btn p-0" type="button" id="cardOpt3" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="bx bx-dots-vertical-rounded"></i>
                  </button>
                  <div class="dropdown-menu dropdown-menu-end" aria-labelledby="cardOpt3">
                    <a class="dropdown-item" href="#">+ Detalhes</a>
                  </div>
                </div>
              </div>
                <span>Nº Veículos em manutenção</span>
                <h3 class="card-title text-nowrap mb-1"><?php echo $veiculos_manutencao; ?></h3>
                <small class="text-warning fw-semibold"><i class="bx bx-wrench"></i> em manutenção</small>
              </div>
            </div>
          </div>
        </div>

          <!-- Card Combustível -->
          <div class="col-md-4 mb-4">
            <div class="card">
              <div class="card-body">
                <div class="card-title d-flex align-items-start justify-content-between">
                        <h2>Redinha</h2>
                    <button >
                      <a class="dropdown-item" href="../abastecimentos/fornecer_comb.php">+ detalhes</a>
                    </button>
                </div>
                <strong>Nível de Combustível:</strong> <?php echo number_format($nivel_combustivel, 2); ?> litros<br />
                <div class="progress-bar" style="background-color: #eee;">
                  <div class="progress" style="width: <?php echo $percentagem; ?>%; background-color: <?php echo $cor; ?>;"></div>
                </div>
                <small><?php echo number_format($percentagem, 2); ?>% da capacidade total</small>
              </div>
            </div>
          </div>          
        </div>
      </div>

      <!-- Overlay -->
      <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

    <!-- Core JS -->
    <!-- build:js assets/vendor/js/core.js -->
    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>

    <script src="../assets/vendor/js/menu.js"></script>
    <!-- endbuild -->

    <!-- Vendors JS -->
    <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>

    <!-- Main JS -->
    <script src="../assets/js/main.js"></script>

    <!-- Page JS -->
    <script src="../assets/js/dashboards-analytics.js"></script>

    <!-- Place this tag in your head or just before your close body tag. -->
    <script async defer src="https://buttons.github.io/buttons.js"></script>
  </body>
</html>
