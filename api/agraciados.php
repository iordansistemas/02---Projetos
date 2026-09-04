<?php
/**
 * API de Gestão de Agraciados e Convidados
 * Suporta CRUD, upload de fotos (câmera ou galeria) e importação/exportação
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDbConnection();

// Processa requisições de acordo com a Ação ou Método HTTP

if ($method === 'GET') {
    // Exportação em CSV
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        requireAuth();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="agraciados_formatura_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        // Adiciona BOM para UTF-8 no Excel
        fputs($output, "\xEF\xBB\xBF");
        
        fputcsv($output, ['ID', 'RE', 'CPF', 'Nome Completo', 'Posto/Graduação', 'Unidade', 'Cargo', 'Medalha', 'Nota CCOMSOC', 'Boletim', 'Mesa/Setor', 'Ciência Confirmada', 'Data Ciência', 'Presente Formatura', 'Data Check-in'], ';');

        $stmt = $db->query("SELECT * FROM agraciados ORDER BY nome_completo ASC");
        while ($row = $stmt->fetch()) {
            fputcsv($output, [
                $row['id'],
                $row['re'],
                $row['cpf'],
                $row['nome_completo'],
                $row['posto_graduacao'],
                $row['unidade'],
                $row['cargo'],
                $row['medalha'],
                $row['nota_ccomsoc'],
                $row['boletim_publicacao'],
                $row['mesa_setor'],
                $row['confirmou_ciencia'] ? 'SIM' : 'NÃO',
                $row['data_ciencia'],
                $row['presente_formatura'] ? 'SIM' : 'NÃO',
                $row['data_checkin']
            ], ';');
        }
        fclose($output);
        exit();
    }

    // Busca individual por ID
    if (isset($_GET['id'])) {
        $stmt = $db->prepare("SELECT * FROM agraciados WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $agraciado = $stmt->fetch();
        if ($agraciado) {
            sendJsonResponse(['success' => true, 'data' => $agraciado]);
        } else {
            sendJsonResponse(['success' => false, 'message' => 'Agraciado não encontrado.'], 404);
        }
    }

    // Listagem com Filtros
    $search = trim($_GET['search'] ?? '');
    $statusCiencia = $_GET['ciencia'] ?? '';
    $statusPresenca = $_GET['presenca'] ?? '';

    $sql = "SELECT * FROM agraciados WHERE 1=1";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (nome_completo LIKE :search OR re LIKE :search OR cpf LIKE :search OR unidade LIKE :search OR medalha LIKE :search)";
        $params[':search'] = "%{$search}%";
    }

    if ($statusCiencia !== '') {
        $sql .= " AND confirmou_ciencia = :ciencia";
        $params[':ciencia'] = (int)$statusCiencia;
    }

    if ($statusPresenca !== '') {
        $sql .= " AND presente_formatura = :presenca";
        $params[':presenca'] = (int)$statusPresenca;
    }

    $sql .= " ORDER BY nome_completo ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $lista = $stmt->fetchAll();

    // Calcula métricas para o resumo
    $totalCount = count($lista);
    $confirmadosCount = 0;
    $presentesCount = 0;
    
    $stmtStats = $db->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN confirmou_ciencia = 1 THEN 1 ELSE 0 END) as ciencia_confirmada,
        SUM(CASE WHEN presente_formatura = 1 THEN 1 ELSE 0 END) as presentes
        FROM agraciados");
    $stats = $stmtStats->fetch();

    sendJsonResponse([
        'success' => true,
        'data' => $lista,
        'stats' => [
            'total' => (int)$stats['total'],
            'confirmados_ciencia' => (int)$stats['ciencia_confirmada'],
            'presentes_formatura' => (int)$stats['presentes']
        ]
    ]);
}

// Processa salvamento (POST / PUT)
if ($method === 'POST') {
    $user = requireOrganizador();


    // Trata upload de imagem em formato FormData ou Base64
    $fotoUrl = null;
    $id = $_POST['id'] ?? null;

    // Upload via arquivo no FormData
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower($type[1]);
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed)) {
            sendJsonResponse(['success' => false, 'message' => 'Formato inválido.'], 400);
}
        if (in_array($ext, $allowed)) {
            $fileName = 'agraciado_' . time() . '_' . uniqid() . '.' . $ext;
            $targetPath = __DIR__ . '/../uploads/' . $fileName;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $targetPath)) {
                $fotoUrl = 'uploads/' . $fileName;
            }
        }
    }

    // Upload via Base64 (Webcam no Celular)
    if (!$fotoUrl && !empty($_POST['foto_base64'])) {
        $base64Data = $_POST['foto_base64'];

        if (preg_match('/^data:image?/\(|w+):base64,/'. $base64data, $type)) {
            $ext = strtolower($type[1]);
            $allowed = ['jpg', 'jpeg', 'png', 'webp'];

            //travava de segurança para evitar upload de arquivos maliciosos
            if (!in_array($ext, $allowed)) {
                sendJsonResponse(['success' => false, 'message' => 'Formato de imagem inválido.'], 400);
            }
            
            $data = substr($base64Data, strpos($base64Data, ',') + 1);
            $data = base64_decode($data);
            if ($data === false) {
                $fileName = 'agraciado_' . time() . '_' . uniqid() . '.' . $ext;
                $targetPath = __DIR__ . '/../uploads/' . $fileName;
                if (file_put_contents($targetPath, $data)) {
                    $fotoUrl = 'uploads/' . $fileName;
                }
            
            }
        }
        
    }

    $re = trim($_POST['re'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $nomeCompleto = trim($_POST['nome_completo'] ?? '');
    $postoGraduacao = trim($_POST['posto_graduacao'] ?? '');
    $unidade = trim($_POST['unidade'] ?? '');
    $cargo = trim($_POST['cargo'] ?? '');
    $medalha = trim($_POST['medalha'] ?? '');
    $notaCcomsoc = trim($_POST['nota_ccomsoc'] ?? '');
    $boletimPublicacao = trim($_POST['boletim_publicacao'] ?? '');
    $mesaSetor = trim($_POST['mesa_setor'] ?? '');

    if (empty($nomeCompleto) || empty($postoGraduacao) || empty($unidade) || empty($medalha)) {
        sendJsonResponse(['success' => false, 'message' => 'Preencha os campos obrigatórios (Nome, Posto/Graduação, Unidade e Medalha).'], 400);
    }

    if ($id) {
        // Atualização (UPDATE)
        $sql = "UPDATE agraciados SET 
            re = :re, cpf = :cpf, nome_completo = :nome_completo, 
            posto_graduacao = :posto_graduacao, unidade = :unidade, cargo = :cargo, 
            medalha = :medalha, nota_ccomsoc = :nota_ccomsoc, boletim_publicacao = :boletim_publicacao, 
            mesa_setor = :mesa_setor";
        
        $params = [
            ':re' => $re,
            ':cpf' => $cpf,
            ':nome_completo' => $nomeCompleto,
            ':posto_graduacao' => $postoGraduacao,
            ':unidade' => $unidade,
            ':cargo' => $cargo,
            ':medalha' => $medalha,
            ':nota_ccomsoc' => $notaCcomsoc,
            ':boletim_publicacao' => $boletimPublicacao,
            ':mesa_setor' => $mesaSetor,
            ':id' => $id
        ];

        if ($fotoUrl) {
            $sql .= ", foto_url = :foto_url";
            $params[':foto_url'] = $fotoUrl;
        }

        $sql .= " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        sendJsonResponse(['success' => true, 'message' => 'Dados do agraciado atualizados com sucesso.', 'id' => $id]);
    } else {
        // Inserção (INSERT)
        $stmt = $db->prepare("INSERT INTO agraciados 
            (re, cpf, nome_completo, posto_graduacao, unidade, cargo, medalha, nota_ccomsoc, boletim_publicacao, mesa_setor, foto_url, registrado_por_usuario_id) 
            VALUES (:re, :cpf, :nome_completo, :posto_graduacao, :unidade, :cargo, :medalha, :nota_ccomsoc, :boletim_publicacao, :mesa_setor, :foto_url, :user_id)");
        
        $stmt->execute([
            ':re' => $re,
            ':cpf' => $cpf,
            ':nome_completo' => $nomeCompleto,
            ':posto_graduacao' => $postoGraduacao,
            ':unidade' => $unidade,
            ':cargo' => $cargo,
            ':medalha' => $medalha,
            ':nota_ccomsoc' => $notaCcomsoc,
            ':boletim_publicacao' => $boletimPublicacao,
            ':mesa_setor' => $mesaSetor,
            ':foto_url' => $fotoUrl,
            ':user_id' => $user['id']
        ]);

        $newId = $db->lastInsertId();
        sendJsonResponse(['success' => true, 'message' => 'Agraciado cadastrado com sucesso.', 'id' => $newId], 201);
    }
}

if ($method === 'DELETE') {
    requireAdmin();
    $input = getJsonInput();
    $id = $input['id'] ?? $_GET['id'] ?? null;

    if (!$id) {
        sendJsonResponse(['success' => false, 'message' => 'ID do agraciado não informado.'], 400);
    }

    $stmt = $db->prepare("DELETE FROM agraciados WHERE id = ?");
    $stmt->execute([$id]);

    sendJsonResponse(['success' => true, 'message' => 'Agraciado excluído com sucesso.']);
}
