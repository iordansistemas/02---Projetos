<?php
/**
 * API de Gestão do Checklist de Ações Operacionais da Formatura
 * Permite listar, criar tarefas, atualizar status e registrar os resultados obtidos
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDbConnection();

if ($method === 'GET') {
    $categoria = trim($_GET['categoria'] ?? '');
    $status = trim($_GET['status'] ?? '');

    $sql = "SELECT * FROM checklist_acoes WHERE 1=1";
    $params = [];

    if (!empty($categoria)) {
        $sql .= " AND categoria = :categoria";
        $params[':categoria'] = $categoria;
    }

    if (!empty($status)) {
        $sql .= " AND status = :status";
        $params[':status'] = $status;
    }

    $sql .= " ORDER BY id ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $tarefas = $stmt->fetchAll();

    // Contadores por categoria
    $stmtStats = $db->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'CONCLUIDO' THEN 1 ELSE 0 END) as concluidas,
        SUM(CASE WHEN status = 'EM_ANDAMENTO' THEN 1 ELSE 0 END) as em_andamento,
        SUM(CASE WHEN status = 'PENDENTE' THEN 1 ELSE 0 END) as pendentes
        FROM checklist_acoes");
    $stats = $stmtStats->fetch();

    sendJsonResponse([
        'success' => true,
        'stats' => [
            'total' => (int)$stats['total'],
            'concluidas' => (int)$stats['concluidas'],
            'em_andamento' => (int)$stats['em_andamento'],
            'pendentes' => (int)$stats['pendentes']
        ],
        'data' => $tarefas
    ]);
}

if ($method === 'POST') {
    $user = requireOrganizador();

    $input = getJsonInput();

    $id = $input['id'] ?? null;
    $categoria = trim($input['categoria'] ?? 'Pré-Evento');
    $titulo = trim($input['titulo'] ?? '');
    $descricao = trim($input['descricao'] ?? '');
    $responsavel = trim($input['responsavel'] ?? '');
    $status = trim($input['status'] ?? 'PENDENTE');
    $resultadoObservacoes = trim($input['resultado_observacoes'] ?? '');

    if (empty($titulo)) {
        sendJsonResponse(['success' => false, 'message' => 'O título da ação é obrigatório.'], 400);
    }

    $agora = date('Y-m-d H:i:s');

    if ($id) {
        // Atualização de Ação / Registro de Resultados
        $stmt = $db->prepare("UPDATE checklist_acoes SET 
            categoria = :categoria,
            titulo = :titulo,
            descricao = :descricao,
            responsavel = :responsavel,
            status = :status,
            resultado_observacoes = :resultado_observacoes,
            atualizado_por = :atualizado_por,
            atualizado_em = :atualizado_em
            WHERE id = :id");

        $stmt->execute([
            ':categoria' => $categoria,
            ':titulo' => $titulo,
            ':descricao' => $descricao,
            ':responsavel' => $responsavel,
            ':status' => $status,
            ':resultado_observacoes' => $resultadoObservacoes,
            ':atualizado_por' => $user['nome'],
            ':atualizado_em' => $agora,
            ':id' => $id
        ]);

        sendJsonResponse(['success' => true, 'message' => 'Ação e resultados atualizados com sucesso.', 'id' => $id]);
    } else {
        // Nova Ação no Checklist
        $stmt = $db->prepare("INSERT INTO checklist_acoes 
            (categoria, titulo, descricao, responsavel, status, resultado_observacoes, atualizado_por, atualizado_em) 
            VALUES (:categoria, :titulo, :descricao, :responsavel, :status, :resultado_observacoes, :atualizado_por, :atualizado_em)");

        $stmt->execute([
            ':categoria' => $categoria,
            ':titulo' => $titulo,
            ':descricao' => $descricao,
            ':responsavel' => $responsavel,
            ':status' => $status,
            ':resultado_observacoes' => $resultadoObservacoes,
            ':atualizado_por' => $user['nome'],
            ':atualizado_em' => $agora
        ]);

        $newId = $db->lastInsertId();
        sendJsonResponse(['success' => true, 'message' => 'Nova ação adicionada ao checklist.', 'id' => $newId], 201);
    }
}

if ($method === 'DELETE') {
    requireAdmin();
    $input = getJsonInput();
    $id = $input['id'] ?? $_GET['id'] ?? null;

    if (!$id) {
        sendJsonResponse(['success' => false, 'message' => 'ID da ação não informado.'], 400);
    }

    $stmt = $db->prepare("DELETE FROM checklist_acoes WHERE id = ?");
    $stmt->execute([$id]);

    sendJsonResponse(['success' => true, 'message' => 'Ação excluída do checklist.']);
}
