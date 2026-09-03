<?php
/**
 * API de Autenticação de Usuários
 * Endpoint para Login, Logout e Verificação de Sessão
 */

require_once __DIR__ . '/config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

if ($action === 'login') {
    $input = getJsonInput();
    $re_cpf = trim($input['re_cpf'] ?? '');
    $senha = trim($input['senha'] ?? '');

    if (empty($re_cpf) || empty($senha)) {
        sendJsonResponse(['success' => false, 'message' => 'Informe o usuário/RE/CPF e a senha.'], 400);
    }

    $db = getDbConnection();
    $stmt = $db->prepare("SELECT id, nome, re_cpf, senha_hash, funcao FROM usuarios WHERE re_cpf = :re_cpf");
    $stmt->execute([':re_cpf' => $re_cpf]);
    $user = $stmt->fetch();

    if ($user && password_verify($senha, $user['senha_hash'])) {
        // Regenera ID de sessão para segurança
        session_regenerate_id(true);

        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario_nome'] = $user['nome'];
        $_SESSION['usuario_re_cpf'] = $user['re_cpf'];
        $_SESSION['usuario_funcao'] = $user['funcao'];

        sendJsonResponse([
            'success' => true,
            'message' => 'Login realizado com sucesso.',
            'usuario' => [
                'id' => $user['id'],
                'nome' => $user['nome'],
                're_cpf' => $user['re_cpf'],
                'funcao' => $user['funcao']
            ]
        ]);
    } else {
        sendJsonResponse(['success' => false, 'message' => 'Usuário ou senha inválidos.'], 401);
    }
}

if ($action === 'logout') {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    sendJsonResponse(['success' => true, 'message' => 'Sessão encerrada com sucesso.']);
}

if ($action === 'status') {
    if (isset($_SESSION['usuario_id'])) {
        sendJsonResponse([
            'authenticated' => true,
            'usuario' => [
                'id' => $_SESSION['usuario_id'],
                'nome' => $_SESSION['usuario_nome'],
                're_cpf' => $_SESSION['usuario_re_cpf'] ?? '',
                'funcao' => $_SESSION['usuario_funcao']
            ]
        ]);
    } else {
        sendJsonResponse(['authenticated' => false]);
    }
}
