<?php
require_once 'App/Models/Config/env.php';
require_once 'App/Models/Config/Database/Database.php';

// Initialize the database connection with proper namespaces
$env = new Config\env();
$db = new Config\Database\Database($env);

// Connect to establish PDO connection first
$db->connect();

// Add the fechamento_caixa_id column directly using PDO
try {
    echo "Adicionando campo fechamento_caixa_id à tabela tb_pedidos...\n";
    
    // As Database class doesn't have a direct method for ALTER statements,
    // we'll use a try-catch to execute the SQL directly via PDO
    $pdo = $db->connect();
    
    // Add column
    $sql_add_field = "ALTER TABLE tb_pedidos ADD COLUMN fechamento_caixa_id INT NULL DEFAULT NULL";
    $pdo->exec($sql_add_field);
    echo "Campo adicionado com sucesso!\n";
    
    // Add index
    $sql_add_index = "CREATE INDEX idx_pedidos_fechamento ON tb_pedidos(fechamento_caixa_id)";
    $pdo->exec($sql_add_index);
    echo "Índice adicionado com sucesso!\n";
    
    echo "Configuração concluída com sucesso!\n";
} catch (PDOException $e) {
    echo "Erro durante a configuração: " . $e->getMessage() . "\n";
    
    // Se o erro for relacionado ao campo já existir, considere como sucesso
    if (strpos($e->getMessage(), 'Duplicate column') !== false || 
        strpos($e->getMessage(), 'already exists') !== false) {
        echo "O campo já existe na tabela. Configuração concluída!\n";
    }
} 