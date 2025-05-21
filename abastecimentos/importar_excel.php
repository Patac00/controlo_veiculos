<?php
session_start();
if (!isset($_SESSION['id_utilizador'])) {
    header("Location: ../login/login.php");
    exit();
}
include("../php/config.php");

require '../vendor/autoload.php'; 
use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dados'])) {
    // Inserir dados na BD após edição
    $dados = json_decode($_POST['dados'], true);

    foreach ($dados as $linha) {
        $dataObj = DateTime::createFromFormat('d/m/Y', $linha['data']);
        $dataFormatada = $dataObj ? $dataObj->format('Y-m-d') : null;

        // Verifica se já existe esse registo (pelo que tu quiseres: data, hora, numero_reg)
        $sqlCheck = "SELECT 1 FROM tabela_combustivel WHERE data = ? AND hora = ? AND numero_reg = ?";
        $stmtCheck = $con->prepare($sqlCheck);
        $linha['numero_reg'] = trim($linha['numero_reg']);
        $stmtCheck->bind_param("sss", $dataFormatada, $linha['hora'], $linha['numero_reg']);
        $stmtCheck->execute();
        $stmtCheck->store_result();

        if ($stmtCheck->num_rows === 0) {
            // Só insere se não existir
            $sql = "INSERT INTO tabela_combustivel (data, hora, unidade, numero_reg, odometro, motorista, quantidade)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $con->prepare($sql);
            $stmt->bind_param(
                "sssssid",
                $dataFormatada,
                $linha['hora'],
                $linha['unidade'],
                $linha['numero_reg'],
                $linha['odometro'],
                $linha['motorista'],
                $linha['quantidade']
            );
            $stmt->execute();
        }
    }

    echo "Importação concluída!";
    exit;
}

if (isset($_FILES['excel_file'])) {
    $fileTmpPath = $_FILES['excel_file']['tmp_name'];
    $spreadsheet = IOFactory::load($fileTmpPath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();

    // Ignorar cabeçalho e passar dados para JS
    $dadosParaJS = [];
    for ($i = 1; $i < count($rows); $i++) {
        $dadosParaJS[] = [
            'data' => $rows[$i][0],
            'hora' => $rows[$i][1],
            'unidade' => $rows[$i][2],
            'numero_reg' => $rows[$i][3],
            'odometro' => $rows[$i][4],
            'motorista' => $rows[$i][5],
            'quantidade' => $rows[$i][6],
        ];
    }
} else {
    $dadosParaJS = null;
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8" />
    <title>Importar Excel e Editar</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 30px auto;
            padding: 0 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            word-wrap: break-word;
            text-align: center;
        }
        th {
            background: #007bff;
            color: white;
        }
        input[type="text"], input[type="number"], input[type="date"], input[type="time"] {
            width: 100%;
            box-sizing: border-box;
            border: none;
            background: transparent;
            text-align: center;
            font-size: 14px;
            outline: none;
        }
        input[type="submit"] {
            margin-top: 20px;
            background-color: #007bff;
            border: none;
            color: white;
            padding: 10px 25px;
            font-size: 16px;
            cursor: pointer;
            border-radius: 4px;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
        #uploadForm {
            margin-bottom: 30px;
        }
    </style>
</head>
<body>

<?php if (!$dadosParaJS): ?>
    <form id="uploadForm" action="" method="post" enctype="multipart/form-data">
        <label>Seleciona o ficheiro Excel:</label><br>
        <input type="file" name="excel_file" accept=".xls,.xlsx" required />
        <input type="submit" value="Carregar e Editar" />
    </form>
<?php else: ?>
    <form id="editForm" method="post">
        <table id="tableDados">
            <thead>
                <tr>
                    <th>DATA (dd/mm/yyyy)</th>
                    <th>HORA</th>
                    <th>UNIDADE</th>
                    <th>NÚMERO REG.</th>
                    <th>ODÔMETRO</th>
                    <th>MOTORISTA</th>
                    <th>QUANTIDADE (L)</th>
                </tr>
            </thead>
            <tbody>
                <!-- Vai ser preenchido pelo JS -->
            </tbody>
        </table>
        <input type="hidden" name="dados" id="dadosHidden" />
        <input type="submit" value="Guardar na Base de Dados" />
        <br><br>
        <button type="button" onclick="window.location.href='';">Recarregar Ficheiro</button>
        <button type="button" onclick="window.location.href='../html/index.php';">Voltar Atrás</button>


    </form>

    <script>
        const dados = <?php echo json_encode($dadosParaJS); ?>;
        const tbody = document.querySelector("#tableDados tbody");

        function criaInput(value, tipo = "text") {
            const input = document.createElement("input");
            input.type = tipo;
            input.value = value ?? "";
            return input;
        }

        dados.forEach(linha => {
            const tr = document.createElement("tr");

            // Data - deixar em texto para editar (dd/mm/yyyy)
            const tdData = document.createElement("td");
            tdData.appendChild(criaInput(linha.data));
            tr.appendChild(tdData);

            // Hora
            const tdHora = document.createElement("td");
            tdHora.appendChild(criaInput(linha.hora));
            tr.appendChild(tdHora);

            // Unidade
            const tdUni = document.createElement("td");
            tdUni.appendChild(criaInput(linha.unidade));
            tr.appendChild(tdUni);

            // Número Reg
            const tdNum = document.createElement("td");
            tdNum.appendChild(criaInput(linha.numero_reg));
            tr.appendChild(tdNum);

            // Odómetro (número)
            const tdOdo = document.createElement("td");
            tdOdo.appendChild(criaInput(linha.odometro, "number"));
            tr.appendChild(tdOdo);

            // Motorista
            const tdMot = document.createElement("td");
            tdMot.appendChild(criaInput(linha.motorista));
            tr.appendChild(tdMot);

            // Quantidade (número)
            const tdQtd = document.createElement("td");
            tdQtd.appendChild(criaInput(linha.quantidade, "number"));
            tr.appendChild(tdQtd);

            tbody.appendChild(tr);
        });

        // Antes de submeter, recolhe os dados da tabela para JSON
        document.getElementById("editForm").addEventListener("submit", async function(e) {
            e.preventDefault();

            const linhas = [...tbody.querySelectorAll("tr")];
            const dadosEnviar = [];

            for (const tr of linhas) {
                const inputs = tr.querySelectorAll("input");
                const linha = {
                    data: inputs[0].value,
                    hora: inputs[1].value,
                    unidade: inputs[2].value,
                    numero_reg: inputs[3].value,
                    odometro: inputs[4].value,
                    motorista: inputs[5].value,
                    quantidade: inputs[6].value,
                };

                const existe = await verificaExistencia(linha);
                if (!existe) dadosEnviar.push(linha); // Só adiciona se não existir
                else tr.style.backgroundColor = "#ffd1d1"; // Destaca os duplicados
            }

            if (dadosEnviar.length === 0) {
                alert("Todos os registos já existem na base de dados.");
                return;
            }

            document.getElementById("dadosHidden").value = JSON.stringify(dadosEnviar);
            this.submit();
        });



    function criaInput(value, tipo = "text") {
        const input = document.createElement("input");
        input.type = tipo;
        input.value = value ?? "";
        if (tipo === "number") input.step = "0.01"; // <- AQUI
        return input;
    }

    async function verificaExistencia(linha) {
    const params = new URLSearchParams({
        data: linha.data,
        hora: linha.hora,
        numero_reg: linha.numero_reg
    });
    const resposta = await fetch(`verifica_registo.php?${params.toString()}`);
    const resultado = await resposta.json();
    return resultado.existe;
}

    </script>
<?php endif; 
?>

</body>
</html>
