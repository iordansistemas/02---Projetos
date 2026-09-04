<?php
/**
 * Configuração de Banco de Dados e Funções Globais da API
 * Compatível com hospedagem HostGator (SQLite/MySQL PDO)
 */

// Inicia buffer de saída para evitar vazamento de avisos HTML no JSON
ob_start();

// Desativa exibição de erros HTML que corrompem respostas JSON
ini_set('display_errors', '0');
error_reporting(0);

// Permite chamadas CORS de forma dinâmica (funciona no Localhost e na HostGator)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");


$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Garantir que a pasta de uploads exista
$uploadsDir = __DIR__ . '/../uploads';
if (!file_exists($uploadsDir)) {
    @mkdir($uploadsDir, 0755, true); // Corrigido a permissão 0777 para 0755 por segurança
}

// Inicia sessão de forma segura com suporte a SameSite e persistência de cookies
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @ini_set('session.cookie_httponly', '1');
    @ini_set('session.use_only_cookies', '1');
    @session_start();
}


// Configurações do Banco de Dados
define('DB_DRIVER', 'sqlite'); // Opções: 'sqlite', 'mysql'
define('DB_HOST', 'localhost');
define('DB_NAME', 'formatura_pm');
define('DB_USER', 'root');
define('DB_PASS', '');
define('SQLITE_FILE', __DIR__ . '/database.sqlite');


/**
 * Retorna a conexão com o Banco de Dados (PDO)
 */
function getDbConnection() {
    static $dbInstance = null;
    if ($dbInstance !== null) {
        return $dbInstance;
    }

    // Tenta conectar via PDO (SQLite ou MySQL)
    try {
        if (DB_DRIVER === 'sqlite' && extension_loaded('pdo_sqlite')) {
            $pdo = new PDO("sqlite:" . SQLITE_FILE);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec("PRAGMA foreign_keys = ON;");
            $dbInstance = $pdo;
            return $dbInstance;
        } elseif (DB_DRIVER === 'mysql' && extension_loaded('pdo_mysql')) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            $dbInstance = $pdo;
            return $dbInstance;
        }
     } catch (Exception $e) {
        // Interrompe a execução e avisa que o servidor não atende aos requisitos
        sendJsonResponse(['success' => false, 'message' => 'Erro de Infraestrutura: Extensão PDO não configurada no servidor.'], 500);
    }
}