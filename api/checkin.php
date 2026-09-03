<?php
/**
 * API de Controle de Presença e Check-in no Dia da Formatura
 * Exibe feed em tempo real com foto, nome e posto/graduação dos agraciados presentes
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDbConnection();

if ($method === 'GET') {
    // Retorna a lista de presença recente para o feed em tempo real
    $limit = (int)($_GET['limit'] ?? 50);
    $onlyPresent = isset($_GET['only_present']) ? (int)$_GET['only_present'] : 1;

    $sql = "SELECT id, re, cpf, nome_completo, posto_graduacao, unidade, cargo, medalha, mesa_setor, foto_url, confirmou_ciencia, presente_formatura, data_checkin 
            FROM agraciados";
    
    if ($onlyPresent === 1) {
        $sql .= " WHERE presente_formatura = 1 ORDER BY data_checkin DESC LIMIT " . $limit;
    } else {
        $sql .= " ORDER BY presente_formatura DESC, nome_completo ASC";
    }

    $stmt = $db->query($sql);
    $presentes = $stmt->fetchAll();

    // Contadores estatísticos
    $stmtStats = $db->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN confirmou_ciencia = 1 THEN 1 ELSE 0 END) as confirmados_ciencia,
        SUM(CASE WHEN presente_formatura = 1 THEN 1 ELSE 0 END) as presentes_formatura
        FROM agraciados");
    $stats = $stmtStats->fetch();

    sendJsonResponse([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'stats' => [
            'total' => (int)$stats['total'],
            'ciencia' => (int)$stats['confirmados_ciencia'],
            'presentes' => (int)$stats['presentes_formatura'],
            'ausentes' => (int)($stats['total'] - $stats['presentes_formatura'])
        ],
        'data' => $presentes
    ]);
}

if ($method === 'POST') {
    $user = requireOrganizador();

    $input = getJsonInput();

    $id = $input['id'] ?? null;
    $presente = isset($input['presente']) ? (int)$input['presente'] : 1;

    if (!$id) {
        sendJsonResponse(['success' => false, 'message' => 'ID do agraciado não informado.'], 400);
    }

    // Busca o agraciado para validação
    $stmtFind = $db->prepare("SELECT id, nome_completo, posto_graduacao, presente_formatura FROM agraciados WHERE id = ?");
    $stmtFind->execute([$id]);
    $agraciado = $stmtFind->fetch();

    if (!$agraciado) {
        sendJsonResponse(['success' => false, 'message' => 'Agraciado não encontrado.'], 404);
    }

    $dataCheckin = $presente ? date('Y-m-d H:i:s') : null;

    $stmtUpdate = $db->prepare("UPDATE agraciados SET 
        presente_formatura = :presente, 
        data_checkin = :data_checkin,
        registrado_por_usuario_id = :user_id 
        WHERE id = :id");

    $stmtUpdate->execute([
        ':presente' => $presente,
        ':data_checkin' => $dataCheckin,
        ':user_id' => $user['id'],
        ':id' => $id
    ]);

    $mensagem = $presente 
        ? "Presença confirmada para {$agraciado['posto_graduacao']} {$agraciado['nome_completo']}."
        : "Presença desmarcada para {$agraciado['posto_graduacao']} {$agraciado['nome_completo']}.";

    sendJsonResponse([
        'success' => true,
        'message' => $mensagem,
        'presente' => $presente,
        'data_checkin' => $dataCheckin,
        'operador' => $user['nome']
    ]);
}
