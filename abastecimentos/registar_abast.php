<?php
include '../php/config.php'; // conexão BD

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $id_veiculo = $_POST['id_veiculo'];
  $id_utilizador = $_POST['id_utilizador'];
  $data_abastecimento = $_POST['data_abastecimento'];
  $km_registados = $_POST['km_registados'];
  $id_posto = $_POST['id_posto'];
  $litros = $_POST['litros'];
  $tipo_combustivel = $_POST['tipo_combustivel_id'];
  $observacoes = $_POST['observacoes'];
  $valor_total = $_POST['valor_total'];

  $sql = "INSERT INTO abastecimentos 
    (id_veiculo, id_utilizador, data_abastecimento, km_registados, id_posto, litros, tipo_combustivel_id, observacoes, valor_total) 
    VALUES 
    ('$id_veiculo', '$id_utilizador', '$data_abastecimento', '$km_registados', '$id_posto', '$litros', '$tipo_combustivel', '$observacoes', '$valor_total')";

  if (mysqli_query($conn, $sql)) {
    echo "<div class='alert alert-success mt-3'>Abastecimento registado com sucesso!</div>";
  } else {
    echo "<div class='alert alert-danger mt-3'>Erro: " . mysqli_error($conn) . "</div>";
  }
}
?>

<div class="container mt-5">
  <div class="col-md-10 mx-auto">
    <div class="card">
      <div class="card-body">
        <div class="card-title d-flex align-items-start justify-content-between mb-3">
          <div class="avatar flex-shrink-0">
            <img src="../assets/img/icons/unicons/gas-pump.png" alt="Abastecimento" class="rounded" />
          </div>
          <h5 class="mb-0">Registar Abastecimento</h5>
        </div>

        <form method="POST" class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Veículo</label>
            <input type="number" name="id_veiculo" class="form-control" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Utilizador</label>
            <input type="number" name="id_utilizador" class="form-control" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Data</label>
            <input type="date" name="data_abastecimento" class="form-control" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">KM Registados</label>
            <input type="number" name="km_registados" class="form-control" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Posto</label>
            <input type="number" name="id_posto" class="form-control" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Litros</label>
            <input type="number" step="0.01" name="litros" class="form-control" required>
          </div>

          <div class="col-md-4">
            <label class="form-label">Tipo de Combustível</label>
            <select name="tipo_combustivel_id" class="form-select" required>
              <option value="">Selecionar...</option>
              <option value="Gasóleo">Gasóleo</option>
              <option value="Gasolina">Gasolina</option>
              <option value="GPL">GPL</option>
              <option value="Elétrico">Elétrico</option>
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Valor Total (€)</label>
            <input type="number" step="0.01" name="valor_total" class="form-control" required>
          </div>

          <div class="col-12">
            <label class="form-label">Observações</label>
            <textarea name="observacoes" class="form-control" rows="2"></textarea>
          </div>

          <div class="col-12 text-end mt-3">
            <button type="submit" class="btn btn-primary">Guardar Abastecimento</button>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>
