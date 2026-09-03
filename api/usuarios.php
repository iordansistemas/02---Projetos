<?php
/**
 * API de Gerenciamento de Usuários e Privilégios (RBAC)
 * Exclusivo para Administradores da Seção
 */

require_once __DIR__ . '/config.php';

$user = requireAdmin(); // Somente Administradores podem acessar esta API
$method = $_SERVER['REQUEST_METHOD'];
$db = getDbConnection();

if ($method === 'GET') {
    $stmt = $db->query("SELECT id, nome, re_cpf, funcao, criado_em FROM usuarios ORDER BY nome ASC");
    $usuarios = $stmt->fetchAll();
    sendJsonResponse(['success' => true, 'data' => $usuarios]);
}

if ($method === 'POST') {
    $input = getJsonInput();

    $id = $input['id'] ?? null;
    $nome = trim($input['nome'] ?? '');
    $reCpf = trim($input['re_cpf'] ?? '');
    $senha = trim($input['senha'] ?? '');
    $funcao = trim($input['funcao'] ?? 'ORGANIZADOR');

    if (empty($nome) || empty($reCpf)) {
        sendJsonResponse(['success' => false, 'message' => 'Nome e RE/CPF são obrigatórios.'], 400);
    }

    if ($id) {
        // Atualização de usuário existente
        if (!empty($senha)) {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE usuarios SET nome = :nome, re_cpf = :re_cpf, senha_hash = :hash, funcao = :funcao WHERE id = :id");
            $stmt->execute([':nome' => $nome, ':re_cpf' => $reCpf, ':hash' => $hash, ':funcao' => $funcao, ':id' => $id]);
        } else {
            $stmt = $db->prepare("UPDATE usuarios SET nome = :nome, re_cpf = :re_cpf, funcao = :funcao WHERE id = :id");
            $stmt->execute([':nome' => $nome, ':re_cpf' => $reCpf, ':funcao' => $funcao, ':id' => $id]);
        }
        sendJsonResponse(['success' => true, 'message' => 'Usuário atualizado com sucesso.']);
    } else {
        // Criação de novo usuário
        if (empty($senha)) {
            $senha = '123456';
        }

        // Verifica se RE/CPF já existe
        $stmtCheck = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE re_cpf = ?");
        $stmtCheck->execute([$reCpf]);
        if ($stmtCheck->fetchColumn() > 0) {
            sendJsonResponse(['success' => false, 'message' => 'Já existe um usuário cadastrado com este RE/CPF.'], 400);
        }

        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO usuarios (nome, re_cpf, senha_hash, funcao) VALUES (:nome, :re_cpf, :hash, :funcao)");
        $stmt->execute([':nome' => $nome, ':re_cpf' => $reCpf, ':hash' => $hash, ':funcao' => $funcao]);

        $newId = $db->lastInsertId();
        sendJsonResponse(['success' => true, 'message' => 'Novo usuário cadastrado com sucesso com a senha configurada.', 'id' => $newId], 201);
    }

}

if ($method === 'DELETE') {
    $input = getJsonInput();
    $id = $input['id'] ?? $_GET['id'] ?? null;

    if (!$id) {
        sendJsonResponse(['success' => false, 'message' => 'ID do usuário não informado.'], 400);
    }

    if ((int)$id === (int)$user['id']) {
        sendJsonResponse(['success' => false, 'message' => 'Você não pode excluir o seu próprio usuário logado.'], 400);
    }

    $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->execute([$id]);

    sendJsonResponse(['success' => true, 'message' => 'Usuário removido com sucesso.']);
}
