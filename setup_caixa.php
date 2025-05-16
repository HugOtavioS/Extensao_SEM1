<?php
// Script para criar tabelas do sistema de caixa

// Define o caminho para o .env
define("__ENV__", __DIR__ . '/.env');

echo "Iniciando criação de tabelas para o sistema de caixa...\n";

// Ler configurações do arquivo .env
if (file_exists(__ENV__)) {
    $envFile = file_get_contents(__ENV__);
    $lines = explode("\n", $envFile);
    $dbConfig = [];
    
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, 'DB') === 0) {
            list($key, $value) = explode('=', $line, 2);
            $dbConfig[trim($key)] = trim($value);
        }
    }
    
    if (empty($dbConfig['DB_HOST']) || empty($dbConfig['DB_NAME']) || 
        empty($dbConfig['DB_USER']) || !isset($dbConfig['DB_PASS'])) {
        echo "ERRO: Configurações de banco de dados incompletas no arquivo .env\n";
        exit(1);
    }
} else {
    echo "ERRO: Arquivo .env não encontrado. Por favor, crie um arquivo .env com as configurações de banco de dados.\n";
    echo "Exemplo de configuração:\n";
    echo "DB_HOST=localhost\n";
    echo "DB_NAME=nome_do_banco\n";
    echo "DB_USER=usuario\n";
    echo "DB_PASS=senha\n";
    exit(1);
}

// Conectar ao banco de dados
try {
    $dsn = "mysql:host={$dbConfig['DB_HOST']};dbname={$dbConfig['DB_NAME']}";
    $pdo = new PDO($dsn, $dbConfig['DB_USER'], $dbConfig['DB_PASS']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Conexão com o banco de dados estabelecida com sucesso.\n";

    // Verificar se a tabela de pedidos existe
    $tablesStmt = $pdo->query("SHOW TABLES LIKE 'tb_pedidos'");
    $pedidosExists = $tablesStmt->rowCount() > 0;

    // Criar as definições das tabelas
    $sql = "
    -- Tabela para armazenar abertura e fechamento de caixa
    CREATE TABLE IF NOT EXISTS `tb_fechamentos_caixa` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `data_abertura` datetime NOT NULL,
        `usuario_abertura` varchar(255) NOT NULL,
        `data_fechamento` datetime DEFAULT NULL,
        `usuario_fechamento` varchar(255) DEFAULT NULL,
        `valor_inicial` decimal(10,2) NOT NULL DEFAULT 0.00,
        `valor_final` decimal(10,2) DEFAULT NULL,
        `valor_esperado` decimal(10,2) DEFAULT NULL,
        `diferenca` decimal(10,2) DEFAULT NULL,
        `observacao` text DEFAULT NULL,
        `status` enum('aberto','fechado') NOT NULL DEFAULT 'aberto',
        PRIMARY KEY (`id`),
        KEY `status` (`status`),
        KEY `usuario_abertura` (`usuario_abertura`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

    -- Tabela para armazenar movimentos do caixa (vendas, sangrias, suprimentos, etc)
    CREATE TABLE IF NOT EXISTS `tb_movimentos_caixa` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `id_fechamento` int(11) NOT NULL,
        `tipo` enum('venda','sangria','suprimento','cancelamento') NOT NULL,
        `valor` decimal(10,2) NOT NULL,
        `metodo_pagamento` enum('dinheiro','cartao_credito','cartao_debito','pix') NOT NULL DEFAULT 'dinheiro',
        `observacao` text DEFAULT NULL,
        `data_hora` datetime NOT NULL,
        `usuario` varchar(255) NOT NULL,
        PRIMARY KEY (`id`),
        KEY `id_fechamento` (`id_fechamento`),
        KEY `tipo` (`tipo`),
        KEY `metodo_pagamento` (`metodo_pagamento`),
        CONSTRAINT `fk_movimento_fechamento` FOREIGN KEY (`id_fechamento`) REFERENCES `tb_fechamentos_caixa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";

    // Se a tabela de pedidos existe, adicione a alteração
    if ($pedidosExists) {
        $sql .= "
        -- Verificar se a coluna já existe antes de adicioná-la
        SET @exist := (
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = '{$dbConfig['DB_NAME']}'
            AND TABLE_NAME = 'tb_pedidos'
            AND COLUMN_NAME = 'id_fechamento'
        );

        SET @sqlstmt := IF(@exist = 0,
            'ALTER TABLE `tb_pedidos` 
            ADD COLUMN `id_fechamento` int(11) DEFAULT NULL,
            ADD KEY `id_fechamento` (`id_fechamento`),
            ADD CONSTRAINT `fk_pedido_fechamento` FOREIGN KEY (`id_fechamento`) REFERENCES `tb_fechamentos_caixa` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
            'SELECT \"Coluna id_fechamento já existe na tabela tb_pedidos.\"'
        );

        PREPARE stmt FROM @sqlstmt;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        ";
    }

    // Executar cada instrução SQL
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
            echo "Executado: " . substr($statement, 0, 50) . "...\n";
        }
    }

    echo "\nTabelas criadas com sucesso!\n";
    echo "As seguintes tabelas foram criadas ou atualizadas:\n";
    echo "- tb_fechamentos_caixa\n";
    echo "- tb_movimentos_caixa\n";
    
    if ($pedidosExists) {
        echo "- tb_pedidos (verificada para adicionar coluna id_fechamento)\n";
    } else {
        echo "AVISO: A tabela tb_pedidos não foi encontrada. A relação entre pedidos e caixa não foi configurada.\n";
    }
    
    echo "\nO sistema de caixa está pronto para uso.\n";

} catch (PDOException $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}

echo "\nPressione Enter para sair...";
fgets(STDIN); 