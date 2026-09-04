<?php
/**
 * Configuração de Banco de Dados e Funções Globais da API
 * Compatível com hospedagem HostGator (SQLite/MySQL PDO) e Fallback JSON File DB Local
 */

// Inicia buffer de saída para evitar vazamento de avisos HTML no JSON
ob_start();

// Desativa exibição de erros HTML que corrompem respostas JSON
ini_set('display_errors', '0');
error_reporting(0);

// Permite chamadas CORS e define codificação UTF-8
header("Access-Control-Allow-Origin: https://seudominio.com.br"); // Altere o asterisco para da sua hospedagem:
header("Access-Control-Allow-Origin: *");
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
    @mkdir($uploadsDir, 0777, true);
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
define('JSON_DB_FILE', __DIR__ . '/database.json');

/**
 * Retorna a conexão com o Banco de Dados (PDO ou Fallback JSON DB Driver)
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
        sendJsonResponse(['success' => false, 'message' => 'Erro de Infraestrutura: Extensão PDO não configurada no servidor cPanel.'], 500);
    }
    
    // Remova ou comente as duas linhas abaixo que ativam o JsonDbAdapter:
    // $dbInstance = new JsonDbAdapter(JSON_DB_FILE);
    // return $dbInstance;
}

/**
 * Retorna resposta em formato JSON e encerra o script
 */
function sendJsonResponse($data, $statusCode = 200) {
    if (ob_get_length()) {
        @ob_clean();
    }
    if (!headers_sent()) {
        http_response_code($statusCode);
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

/**
 * Obtém os dados do corpo da requisição JSON
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return is_array($data) ? $data : $_POST;
}

/**
 * Autenticação do Usuário
 */
function requireAuth() {
    if (!isset($_SESSION['usuario_id'])) {
        sendJsonResponse(['success' => false, 'message' => 'Não autorizado. Faça login.'], 401);
    }
    return [
        'id' => $_SESSION['usuario_id'],
        'nome' => $_SESSION['usuario_nome'],
        'funcao' => $_SESSION['usuario_funcao']
    ];
}

function requireAdmin() {
    $user = requireAuth();
    if ($user['funcao'] !== 'ADMIN') {
        sendJsonResponse(['success' => false, 'message' => 'Acesso negado. Requer privilégio de Administrador.'], 403);
    }
    return $user;
}

function requireOrganizador() {
    $user = requireAuth();
    $funcao = strtoupper($user['funcao'] ?? '');
    if ($funcao !== 'ORGANIZADOR' && $funcao !== 'ADMIN') {
        sendJsonResponse(['success' => false, 'message' => 'Acesso negado. O perfil de Observador tem permissão estritamente de visualização e não pode fazer edições.'], 403);
    }
    return $user;
}



/**
 * Driver de Banco de Dados JSON para garantir funcionamento total sem extensão PDO SQLite no Windows
 */
class JsonDbAdapter {
    private $filePath;
    public $data = [
        'usuarios' => [],
        'agraciados' => [],
        'checklist_acoes' => []
    ];
    private $lastInsertId = 0;

    public function __construct($filePath) {
        $this->filePath = $filePath;
        $this->load();
    }

    private function load() {
        if (file_exists($this->filePath)) {
            $content = file_get_contents($this->filePath);
            $parsed = json_decode($content, true);
            if (is_array($parsed)) {
                $this->data = array_merge($this->data, $parsed);
            }
        }
    }

    public function save() {
        file_put_contents($this->filePath, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function exec($sql) {
        return true;
    }

    public function lastInsertId() {
        return $this->lastInsertId;
    }

    public function setLastInsertId($id) {
        $this->lastInsertId = $id;
    }

    public function query($sql) {
        $stmt = $this->prepare($sql);
        $stmt->execute();
        return $stmt;
    }

    public function prepare($sql) {
        return new JsonDbStatement($this, $sql);
    }
}

class JsonDbStatement {
    private $adapter;
    private $sql;
    private $result = [];

    public function __construct($adapter, $sql) {
        $this->adapter = &$adapter;
        $this->sql = $sql;
    }

    public function execute($params = []) {
        $sql = $this->sql;
        $data = &$this->adapter->data;

        // COUNT de Usuarios
        if (stripos($sql, 'SELECT COUNT(*) FROM usuarios') !== false) {
            $reCpf = $params[0] ?? $params[':re_cpf'] ?? null;
            if ($reCpf) {
                $count = 0;
                foreach ($data['usuarios'] as $u) {
                    if (($u['re_cpf'] ?? '') === $reCpf) $count++;
                }
                $this->result = [[$count]];
            } else {
                $this->result = [[count($data['usuarios'])]];
            }
            return true;
        }

        // COUNT de Checklist
        if (stripos($sql, 'SELECT COUNT(*) FROM checklist_acoes') !== false) {
            $this->result = [[count($data['checklist_acoes'])]];
            return true;
        }

        // COUNT de Agraciados
        if (stripos($sql, 'SELECT COUNT(*) FROM agraciados') !== false) {
            $this->result = [[count($data['agraciados'])]];
            return true;
        }

        // SELECT LOGIN USUARIOS
        if (stripos($sql, 'SELECT id, nome, re_cpf, senha_hash, funcao FROM usuarios WHERE re_cpf') !== false) {
            $reCpf = $params[':re_cpf'] ?? $params[0] ?? '';
            $found = null;
            foreach ($data['usuarios'] as $u) {
                if (($u['re_cpf'] ?? '') === $reCpf) {
                    $found = $u;
                    break;
                }
            }
            $this->result = $found ? [$found] : [];
            return true;
        }

        // SELECT ALL USUARIOS
        if (stripos($sql, 'SELECT id, nome, re_cpf, funcao, criado_em FROM usuarios') !== false) {
            $this->result = array_values($data['usuarios']);
            return true;
        }

        // INSERT USUARIO
        if (stripos($sql, 'INSERT INTO usuarios') !== false) {
            $maxId = 0;
            foreach ($data['usuarios'] as $u) { if ($u['id'] > $maxId) $maxId = $u['id']; }
            $newId = $maxId + 1;

            $newUser = [
                'id' => $newId,
                'nome' => $params[':nome'] ?? $params[0] ?? '',
                're_cpf' => $params[':re_cpf'] ?? $params[1] ?? '',
                'senha_hash' => $params[':hash'] ?? $params[2] ?? '',
                'funcao' => $params[':funcao'] ?? $params[3] ?? 'ORGANIZADOR',
                'criado_em' => date('Y-m-d H:i:s')
            ];
            $data['usuarios'][] = $newUser;
            $this->adapter->setLastInsertId($newId);
            $this->adapter->save();
            return true;
        }

        // UPDATE USUARIO
        if (stripos($sql, 'UPDATE usuarios') !== false) {
            $id = $params[':id'] ?? null;
            foreach ($data['usuarios'] as &$u) {
                if ($u['id'] == $id) {
                    $u['nome'] = $params[':nome'] ?? $u['nome'];
                    $u['re_cpf'] = $params[':re_cpf'] ?? $u['re_cpf'];
                    $u['funcao'] = $params[':funcao'] ?? $u['funcao'];
                    if (isset($params[':hash'])) $u['senha_hash'] = $params[':hash'];
                }
            }
            $this->adapter->save();
            return true;
        }

        // DELETE USUARIO
        if (stripos($sql, 'DELETE FROM usuarios') !== false) {
            $id = $params[0] ?? $params[':id'] ?? null;
            $data['usuarios'] = array_values(array_filter($data['usuarios'], fn($u) => $u['id'] != $id));
            $this->adapter->save();
            return true;
        }

        // STATS AGRACIADOS
        if (stripos($sql, 'SELECT') !== false && stripos($sql, 'confirmados_ciencia') !== false && stripos($sql, 'agraciados') !== false) {
            $total = count($data['agraciados']);
            $ciencia = 0;
            $presentes = 0;
            foreach ($data['agraciados'] as $a) {
                if (!empty($a['confirmou_ciencia'])) $ciencia++;
                if (!empty($a['presente_formatura'])) $presentes++;
            }
            $this->result = [[
                'total' => $total,
                'ciencia_confirmada' => $ciencia,
                'confirmados_ciencia' => $ciencia,
                'presentes' => $presentes,
                'presentes_formatura' => $presentes
            ]];
            return true;
        }

        // STATS CHECKLIST
        if (stripos($sql, 'SELECT') !== false && stripos($sql, 'checklist_acoes') !== false && stripos($sql, 'concluidas') !== false) {
            $total = count($data['checklist_acoes']);
            $concluidas = 0;
            $em_andamento = 0;
            $pendentes = 0;
            foreach ($data['checklist_acoes'] as $t) {
                $s = $t['status'] ?? 'PENDENTE';
                if ($s === 'CONCLUIDO') $concluidas++;
                elseif ($s === 'EM_ANDAMENTO') $em_andamento++;
                else $pendentes++;
            }
            $this->result = [[
                'total' => $total,
                'concluidas' => $concluidas,
                'em_andamento' => $em_andamento,
                'pendentes' => $pendentes
            ]];
            return true;
        }

        // SELECT AGRACIADOS BY ID
        if (stripos($sql, 'SELECT * FROM agraciados WHERE id =') !== false) {
            $id = $params[0] ?? $params[':id'] ?? null;
            $found = null;
            foreach ($data['agraciados'] as $a) {
                if ($a['id'] == $id) { $found = $a; break; }
            }
            $this->result = $found ? [$found] : [];
            return true;
        }

        // SELECT LIST AGRACIADOS / PRESENCE FEED / CHECKIN
        if (stripos($sql, 'SELECT') !== false && stripos($sql, 'FROM agraciados') !== false) {
            $list = array_values($data['agraciados']);

            // Filtro por busca
            if (isset($params[':search'])) {
                $term = str_replace('%', '', strtolower($params[':search']));
                $list = array_filter($list, function($a) use ($term) {
                    return (strpos(strtolower($a['nome_completo'] ?? ''), $term) !== false) ||
                           (strpos(strtolower($a['re'] ?? ''), $term) !== false) ||
                           (strpos(strtolower($a['cpf'] ?? ''), $term) !== false) ||
                           (strpos(strtolower($a['unidade'] ?? ''), $term) !== false) ||
                           (strpos(strtolower($a['medalha'] ?? ''), $term) !== false);
                });
            }

            // Filtro por presenca no feed
            if (stripos($sql, 'WHERE presente_formatura = 1') !== false) {
                $list = array_filter($list, fn($a) => !empty($a['presente_formatura']));
            }

            $this->result = array_values($list);
            return true;
        }

        // SELECT RSVP BY TERMO
        if (stripos($sql, 'FROM agraciados') !== false && stripos($sql, 'WHERE (re = :termo OR cpf = :termo') !== false) {
            $termo = $params[':termo'] ?? '';
            $termoClean = $params[':termoClean'] ?? '';
            $found = null;
            foreach ($data['agraciados'] as $a) {
                $cpfClean = preg_replace('/[^0-9]/', '', $a['cpf'] ?? '');
                $reClean = preg_replace('/[^0-9]/', '', $a['re'] ?? '');
                if ($a['re'] === $termo || $a['cpf'] === $termo || $cpfClean === $termoClean || $reClean === $termoClean) {
                    $found = $a;
                    break;
                }
            }
            $this->result = $found ? [$found] : [];
            return true;
        }

        // INSERT AGRACIADOS
        if (stripos($sql, 'INSERT INTO agraciados') !== false) {
            $maxId = 0;
            foreach ($data['agraciados'] as $a) { if ($a['id'] > $maxId) $maxId = $a['id']; }
            $newId = $maxId + 1;

            if (is_array($params) && isset($params[':nome_completo'])) {
                $item = [
                    'id' => $newId,
                    're' => $params[':re'] ?? '',
                    'cpf' => $params[':cpf'] ?? '',
                    'nome_completo' => $params[':nome_completo'] ?? '',
                    'posto_graduacao' => $params[':posto_graduacao'] ?? '',
                    'unidade' => $params[':unidade'] ?? '',
                    'cargo' => $params[':cargo'] ?? '',
                    'medalha' => $params[':medalha'] ?? '',
                    'nota_ccomsoc' => $params[':nota_ccomsoc'] ?? '',
                    'boletim_publicacao' => $params[':boletim_publicacao'] ?? '',
                    'mesa_setor' => $params[':mesa_setor'] ?? '',
                    'foto_url' => $params[':foto_url'] ?? null,
                    'confirmou_ciencia' => 0,
                    'data_ciencia' => null,
                    'presente_formatura' => 0,
                    'data_checkin' => null
                ];
            } else {
                $item = [
                    'id' => $newId,
                    're' => $params[0] ?? '',
                    'cpf' => $params[1] ?? '',
                    'nome_completo' => $params[2] ?? '',
                    'posto_graduacao' => $params[3] ?? '',
                    'unidade' => $params[4] ?? '',
                    'cargo' => $params[5] ?? '',
                    'medalha' => $params[6] ?? '',
                    'nota_ccomsoc' => $params[7] ?? '',
                    'boletim_publicacao' => $params[8] ?? '',
                    'mesa_setor' => $params[9] ?? '',
                    'confirmou_ciencia' => $params[10] ?? 0,
                    'data_ciencia' => $params[11] ?? null,
                    'presente_formatura' => $params[12] ?? 0,
                    'data_checkin' => $params[13] ?? null
                ];
            }

            $data['agraciados'][] = $item;
            $this->adapter->setLastInsertId($newId);
            $this->adapter->save();
            return true;
        }

        // UPDATE AGRACIADOS (CHECK-IN / RSVP / EDIT)
        if (stripos($sql, 'UPDATE agraciados') !== false) {
            $id = $params[':id'] ?? null;
            foreach ($data['agraciados'] as &$a) {
                if ($a['id'] == $id) {
                    if (isset($params[':presente'])) {
                        $a['presente_formatura'] = $params[':presente'];
                        $a['data_checkin'] = $params[':data_checkin'];
                    }
                    if (isset($params[':confirmou'])) {
                        $a['confirmou_ciencia'] = $params[':confirmou'];
                        $a['data_ciencia'] = $params[':data_ciencia'];
                    }
                    if (isset($params[':nome_completo'])) {
                        $a['re'] = $params[':re'];
                        $a['cpf'] = $params[':cpf'];
                        $a['nome_completo'] = $params[':nome_completo'];
                        $a['posto_graduacao'] = $params[':posto_graduacao'];
                        $a['unidade'] = $params[':unidade'];
                        $a['cargo'] = $params[':cargo'];
                        $a['medalha'] = $params[':medalha'];
                        $a['nota_ccomsoc'] = $params[':nota_ccomsoc'];
                        $a['boletim_publicacao'] = $params[':boletim_publicacao'];
                        $a['mesa_setor'] = $params[':mesa_setor'];
                        if (isset($params[':foto_url'])) $a['foto_url'] = $params[':foto_url'];
                    }
                }
            }
            $this->adapter->save();
            return true;
        }

        // DELETE AGRACIADO
        if (stripos($sql, 'DELETE FROM agraciados') !== false) {
            $id = $params[0] ?? $params[':id'] ?? null;
            $data['agraciados'] = array_values(array_filter($data['agraciados'], fn($a) => $a['id'] != $id));
            $this->adapter->save();
            return true;
        }

        // SELECT CHECKLIST ACOES
        if (stripos($sql, 'SELECT * FROM checklist_acoes') !== false) {
            $list = array_values($data['checklist_acoes']);
            if (isset($params[':categoria'])) {
                $cat = $params[':categoria'];
                $list = array_filter($list, fn($t) => ($t['categoria'] ?? '') === $cat);
            }
            $this->result = array_values($list);
            return true;
        }

        // INSERT CHECKLIST
        if (stripos($sql, 'INSERT INTO checklist_acoes') !== false) {
            $maxId = 0;
            foreach ($data['checklist_acoes'] as $t) { if ($t['id'] > $maxId) $maxId = $t['id']; }
            $newId = $maxId + 1;

            if (is_array($params) && isset($params[':titulo'])) {
                $item = [
                    'id' => $newId,
                    'categoria' => $params[':categoria'] ?? 'Pré-Evento',
                    'titulo' => $params[':titulo'] ?? '',
                    'descricao' => $params[':descricao'] ?? '',
                    'responsavel' => $params[':responsavel'] ?? '',
                    'status' => $params[':status'] ?? 'PENDENTE',
                    'resultado_observacoes' => $params[':resultado_observacoes'] ?? '',
                    'atualizado_por' => $params[':atualizado_por'] ?? '',
                    'atualizado_em' => $params[':atualizado_em'] ?? date('Y-m-d H:i:s')
                ];
            } else {
                $item = [
                    'id' => $newId,
                    'categoria' => $params[0] ?? 'Pré-Evento',
                    'titulo' => $params[1] ?? '',
                    'descricao' => $params[2] ?? '',
                    'responsavel' => $params[3] ?? '',
                    'status' => $params[4] ?? 'PENDENTE',
                    'resultado_observacoes' => $params[5] ?? '',
                    'atualizado_em' => $params[6] ?? date('Y-m-d H:i:s')
                ];
            }

            $data['checklist_acoes'][] = $item;
            $this->adapter->setLastInsertId($newId);
            $this->adapter->save();
            return true;
        }

        // UPDATE CHECKLIST
        if (stripos($sql, 'UPDATE checklist_acoes') !== false) {
            $id = $params[':id'] ?? null;
            foreach ($data['checklist_acoes'] as &$t) {
                if ($t['id'] == $id) {
                    $t['categoria'] = $params[':categoria'] ?? $t['categoria'];
                    $t['titulo'] = $params[':titulo'] ?? $t['titulo'];
                    $t['descricao'] = $params[':descricao'] ?? $t['descricao'];
                    $t['responsavel'] = $params[':responsavel'] ?? $t['responsavel'];
                    $t['status'] = $params[':status'] ?? $t['status'];
                    $t['resultado_observacoes'] = $params[':resultado_observacoes'] ?? $t['resultado_observacoes'];
                    $t['atualizado_por'] = $params[':atualizado_por'] ?? $t['atualizado_por'];
                    $t['atualizado_em'] = $params[':atualizado_em'] ?? date('Y-m-d H:i:s');
                }
            }
            $this->adapter->save();
            return true;
        }

        // DELETE CHECKLIST
        if (stripos($sql, 'DELETE FROM checklist_acoes') !== false) {
            $id = $params[0] ?? $params[':id'] ?? null;
            $data['checklist_acoes'] = array_values(array_filter($data['checklist_acoes'], fn($t) => $t['id'] != $id));
            $this->adapter->save();
            return true;
        }

        return true;
    }

    public function fetch() {
        return array_shift($this->result) ?: false;
    }

    public function fetchAll() {
        $res = $this->result;
        $this->result = [];
        return $res;
    }

    public function fetchColumn() {
        $row = array_shift($this->result);
        if (!$row) return 0;
        if (is_array($row)) {
            $vals = array_values($row);
            return $vals[0] ?? 0;
        }
        return 0;
    }
}
