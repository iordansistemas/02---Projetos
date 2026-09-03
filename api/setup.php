<?php
/**
 * Script de Instalação e Inicialização do Banco de Dados
 * Cria as tabelas necessárias e o usuário Administrador padrão
 */

require_once __DIR__ . '/config.php';

try {
    $db = getDbConnection();

    // 1. Tabela de Usuários
    if (DB_DRIVER === 'sqlite') {
        $db->exec("CREATE TABLE IF NOT EXISTS usuarios (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            re_cpf TEXT UNIQUE NOT NULL,
            senha_hash TEXT NOT NULL,
            funcao TEXT NOT NULL DEFAULT 'ORGANIZADOR',
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        );");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            re_cpf VARCHAR(50) UNIQUE NOT NULL,
            senha_hash VARCHAR(255) NOT NULL,
            funcao VARCHAR(30) NOT NULL DEFAULT 'ORGANIZADOR',
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    // 2. Tabela de Agraciados / Convidados
    if (DB_DRIVER === 'sqlite') {
        $db->exec("CREATE TABLE IF NOT EXISTS agraciados (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            re TEXT,
            cpf TEXT,
            nome_completo TEXT NOT NULL,
            posto_graduacao TEXT NOT NULL,
            unidade TEXT NOT NULL,
            cargo TEXT,
            medalha TEXT NOT NULL,
            nota_ccomsoc TEXT,
            boletim_publicacao TEXT,
            mesa_setor TEXT,
            foto_url TEXT,
            confirmou_ciencia INTEGER DEFAULT 0,
            data_ciencia DATETIME,
            presente_formatura INTEGER DEFAULT 0,
            data_checkin DATETIME,
            registrado_por_usuario_id INTEGER,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (registrado_por_usuario_id) REFERENCES usuarios(id)
        );");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS agraciados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            re VARCHAR(30),
            cpf VARCHAR(20),
            nome_completo VARCHAR(150) NOT NULL,
            posto_graduacao VARCHAR(50) NOT NULL,
            unidade VARCHAR(100) NOT NULL,
            cargo VARCHAR(100),
            medalha VARCHAR(100) NOT NULL,
            nota_ccomsoc VARCHAR(50),
            boletim_publicacao VARCHAR(50),
            mesa_setor VARCHAR(50),
            foto_url VARCHAR(255),
            confirmou_ciencia INT DEFAULT 0,
            data_ciencia DATETIME,
            presente_formatura INT DEFAULT 0,
            data_checkin DATETIME,
            registrado_por_usuario_id INT,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (registrado_por_usuario_id) REFERENCES usuarios(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    // 3. Tabela de Checklist de Ações da Organização
    if (DB_DRIVER === 'sqlite') {
        $db->exec("CREATE TABLE IF NOT EXISTS checklist_acoes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            categoria TEXT NOT NULL, -- 'Pré-Evento', 'Dia da Formatura', 'Pós-Evento'
            titulo TEXT NOT NULL,
            descricao TEXT,
            responsavel TEXT,
            status TEXT DEFAULT 'PENDENTE', -- 'PENDENTE', 'EM_ANDAMENTO', 'CONCLUIDO'
            resultado_observacoes TEXT,
            atualizado_por TEXT,
            atualizado_em DATETIME
        );");
    } else {
        $db->exec("CREATE TABLE IF NOT EXISTS checklist_acoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            categoria VARCHAR(50) NOT NULL,
            titulo VARCHAR(150) NOT NULL,
            descricao TEXT,
            responsavel VARCHAR(100),
            status VARCHAR(30) DEFAULT 'PENDENTE',
            resultado_observacoes TEXT,
            atualizado_por VARCHAR(100),
            atualizado_em DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    // Garante a criação do diretório de uploads
    $uploadDir = __DIR__ . '/../uploads';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // 4. Criação do Usuário Administrador Padrão (se não existir)
    $stmt = $db->prepare("SELECT COUNT(*) FROM usuarios");
    $stmt->execute();
    $countUsers = $stmt->fetchColumn();

    if ($countUsers == 0) {
        $senhaPadrao = 'admin123';
        $hash = password_hash($senhaPadrao, PASSWORD_DEFAULT);
        $stmtInsert = $db->prepare("INSERT INTO usuarios (nome, re_cpf, senha_hash, funcao) VALUES (:nome, :re_cpf, :hash, :funcao)");
        $stmtInsert->execute([
            ':nome' => 'Administrador da Seção',
            ':re_cpf' => 'admin',
            ':hash' => $hash,
            ':funcao' => 'ADMIN'
        ]);
        
        // Cadastra um organizador padrão para testes
        $stmtInsert->execute([
            ':nome' => 'Equipe de Apoio / Recepção',
            ':re_cpf' => 'recepcao',
            ':hash' => password_hash('recepcao123', PASSWORD_DEFAULT),
            ':funcao' => 'ORGANIZADOR'
        ]);

        // Cadastra um observador padrão para testes
        $stmtInsert->execute([
            ':nome' => 'Visitante / Observador',
            ':re_cpf' => 'observador',
            ':hash' => password_hash('observador123', PASSWORD_DEFAULT),
            ':funcao' => 'OBSERVADOR'
        ]);
    }


    // 5. Inserção de Tarefas Iniciais no Checklist (se estiver vazio)
    $stmtChecklist = $db->prepare("SELECT COUNT(*) FROM checklist_acoes");
    $stmtChecklist->execute();
    if ($stmtChecklist->fetchColumn() == 0) {
        $tarefasIniciais = [
            ['Pré-Evento', 'Conferência do Livro de Ouro', 'Verificar relação de agraciados com portarias e decretos', 'Subtenente P3', 'CONCLUIDO', 'Todas as 45 concessões validadas com o Boletim Geral.'],
            ['Pré-Evento', 'Confecção dos Diplomas e Condecorações', 'Imprimir diplomas em papel supremo e montar medalhas nas caixas', 'Sargento Seção de Medalhas', 'CONCLUIDO', 'Diplomas impressos e assinados pelo Comandante.'],
            ['Pré-Evento', 'Envio de Convites e Ciência', 'Disponibilizar o link do PWA para os agraciados confirmarem ciência', 'Cabo Recepção', 'EM_ANDAMENTO', 'Link divulgado via intranet e grupos oficiais.'],
            ['Dia da Formatura', 'Montagem do Som e Microfones', 'Testar sistema de som, microfone sem fio da mestre de cerimônias e PAs', 'Equipe de Comunicação', 'PENDENTE', ''],
            ['Dia da Formatura', 'Recepção e Check-in dos Agraciados', 'Recepcionar agraciados na entrada com o aplicativo PWA no celular', 'Equipe de Recepção', 'EM_ANDAMENTO', 'Recepção iniciada no portão principal.'],
            ['Dia da Formatura', 'Disposição das Medalhas na Mesa de Outorga', 'Organizar condecorações na ordem exata de chamada da nota de solenidade', 'Mestre de Cerimônias', 'PENDENTE', ''],
            ['Pós-Evento', 'Publicação do Boletim Interno de Solenidade', 'Registrar presença de autoridades e agraciados em ata oficial', 'P3 Seção de Comunicação', 'PENDENTE', ''],
            ['Pós-Evento', 'Agradecimento e Arquivamento', 'Encaminhar ofícios de agradecimento e arquivar livro de registro', 'Chefia da Seção', 'PENDENTE', '']
        ];

        $stmtInsTask = $db->prepare("INSERT INTO checklist_acoes (categoria, titulo, descricao, responsavel, status, resultado_observacoes, atualizado_em) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $agora = date('Y-m-d H:i:s');
        foreach ($tarefasIniciais as $t) {
            $stmtInsTask->execute([$t[0], $t[1], $t[2], $t[3], $t[4], $t[5], $agora]);
        }
    }

    // 6. Insere alguns Agraciados de Exemplo caso a tabela esteja vazia
    $stmtAgraciados = $db->prepare("SELECT COUNT(*) FROM agraciados");
    $stmtAgraciados->execute();
    if ($stmtAgraciados->fetchColumn() == 0) {
        $exemplos = [
            ['150.256-8', '987.654.321-00', 'Maria Silva Santos', 'Capitão PM', '1º BPM/M', 'Comandante de Companhia', 'Medalha Mérito Pessoal 1º Grau', 'Nota CCOMSOC 123/24', 'Bol G PM 045/24', 'Mesa 01 - Assento 04', 1, '2026-09-01 14:30:00', 1, '2026-09-03 08:15:00'],
            ['123.456-7', '123.456.789-00', 'Antônio Ferreira Pinto', 'Secretário de Estado', 'SSP', 'Secretário de Segurança Pública', 'Medalha Brigadheiro Tobias', 'Nota CCOMSOC 150/24', 'Bol G PM 050/24', 'Mesa das Autoridades', 1, '2026-09-02 10:00:00', 0, null],
            ['188.900-1', '456.789.123-11', 'Carlos Eduardo Oliveira', '1º Tenente PM', '2º BPTran', 'Oficial de Operações', 'Medalha Láurea de Mérito Pessoal 2º Grau', 'Nota CCOMSOC 125/24', 'Bol G PM 046/24', 'Setor A - Fila 2', 1, '2026-09-02 16:20:00', 1, '2026-09-03 08:35:00'],
            ['195.432-0', '789.123.456-22', 'Juliana Rocha Lima', 'Subtenente PM', 'CPA/M-1', 'Encarregada de Seção', 'Medalha Centenário do 1º BPChoque', 'Nota CCOMSOC 128/24', 'Bol G PM 047/24', 'Setor B - Fila 1', 0, null, 0, null]
        ];

        $stmtInsAgr = $db->prepare("INSERT INTO agraciados (re, cpf, nome_completo, posto_graduacao, unidade, cargo, medalha, nota_ccomsoc, boletim_publicacao, mesa_setor, confirmou_ciencia, data_ciencia, presente_formatura, data_checkin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($exemplos as $e) {
            $stmtInsAgr->execute($e);
        }
    }

    sendJsonResponse([
        'success' => true,
        'message' => 'Instalação concluída com sucesso! Banco de dados e tabelas prontos.',
        'credenciais_admin' => [
            'login' => 'admin',
            'senha' => 'admin123'
        ],
        'credenciais_recepcao' => [
            'login' => 'recepcao',
            'senha' => 'recepcao123'
        ]
    ]);

} catch (Exception $e) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Erro na instalação do banco de dados: ' . $e->getMessage()
    ], 500);
}
