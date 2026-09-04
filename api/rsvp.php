<?php
/**
 * API de Confirmação Pública de Ciência (RSVP do Agraciado)
 * Permite ao agraciado buscar pelo RE ou CPF e registrar sua ciência prévia da formatura
 */

require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDbConnection();

if ($method === 'GET') {
    $termo = trim($_GET['termo'] ?? '');
    if (empty($termo)) {
        sendJsonResponse(['success' => false, 'message' => 'Informe o número do RE ou CPF para consultar o convite.'], 400);
    }

    $stmt = $db->prepare("SELECT id, re, cpf, nome_completo, posto_graduacao, unidade, cargo, medalha, nota_ccomsoc, boletim_publicacao, mesa_setor, foto_url, confirmou_ciencia, data_ciencia 
        FROM agraciados 
        WHERE (re = :termo OR cpf = :termo OR REPLACE(REPLACE(cpf, '.', ''), '-', '') = :termoClean)");
    
    $termoClean = preg_replace('/[^0-9]/', '', $termo);
    $stmt->execute([':termo' => $termo, ':termoClean' => $termoClean]);
    $agraciado = $stmt->fetch();

    if ($agraciado) {
        sendJsonResponse(['success' => true, 'data' => $agraciado]);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Nenhum convite encontrado com o RE ou CPF informado. Verifique se digitou corretamente.'], 404);
    }
}

if ($method === 'POST') {
    $input = getJsonInput();
    $id = $input['id'] ?? null;
    $confirmou = isset($input['confirmou']) ? (int)$input['confirmou'] : 1;

    if (!$id) {
        sendJsonResponse(['success' => false, 'message' => 'ID do convite inválido.'], 400);
    }

    $stmtCheck = $db->prepare("SELECT id, nome_completo, posto_graduacao FROM agraciados WHERE id = ?");
    $stmtCheck->execute([$id]);
    $agraciado = $stmtCheck->fetch();

    if (!$agraciado) {
        sendJsonResponse(['success' => false, 'message' => 'Agraciado não encontrado.'], 404);
    }

    $dataCiencia = $confirmou ? date('Y-m-d H:i:s') : null;

    $stmtUpdate = $db->prepare("UPDATE agraciados SET confirmou_ciencia = :confirmou, data_ciencia = :data_ciencia WHERE id = :id");
    $stmtUpdate->execute([
        ':confirmou' => $confirmou,
        ':data_ciencia' => $dataCiencia,
        ':id' => $id
    ]);

    $mensagem = $confirmou 
        ? "Ciência registrada com sucesso! A Organização da Formatura agradece a sua confirmação, {$agraciado['posto_graduacao']} {$agraciado['nome_completo']}."
        : "Ciência registrada como 'Não poderei comparecer'.";

    sendJsonResponse([
        'success' => true,
        'message' => $mensagem,
        'confirmou_ciencia' => $confirmou,
        'data_ciencia' => $dataCiencia
    ]);
}
