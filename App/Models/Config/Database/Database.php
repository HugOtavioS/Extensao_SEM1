<?php
namespace Config\Database;

use PDO;
use PDOException;
use Config\env;
use Config\Database\Interfaces\DatabaseInterface;

class Database implements DatabaseInterface {

    private static $env;
    private $pdo;

    public function __construct(env $env) {

        self::$env = $env;
        self::$env = self::$env->getenvDB();

    }
    
    public function connect (): PDO|null {
        $dsn = 'mysql:dbname=' . self::$env['DB_NAME'] . ';host=' . self::$env['DB_HOST'];
        $username = self::$env['DB_USER'];
        $password = self::$env['DB_PASS'];

        try {

            $this->pdo = new PDO($dsn, $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            return $this->pdo;

        } catch (PDOException $e) {

            // self::$databaseError->error('Connection failed: ' . $e->getMessage());
            throw new PDOException($e->getMessage());

        }

    }

    public function create(array $data, string $table, ?string $where = null): bool {
        $stmt = $this->connect();
        $columns = implode(", ", array_keys($data));

        foreach ($data as $key => $value) {
            $data[$key] = "'" . $value . "'";
        }

        $values = implode(", ", array_values($data));

        $sql = "INSERT INTO $table ($columns) VALUES ($values)";

        if ($where !== null) {
            $sql .= " WHERE $where";
        }

        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute();
        
        return $result;
    }

    public function update(array $data, string $table, ?string $where = null): bool {

        $stmt = $this->connect();

        // Construir a parte SET da consulta no formato "coluna1 = 'valor1', coluna2 = 'valor2'"
        $setStatements = [];
        foreach ($data as $key => $value) {
            // Escapar os valores para evitar injeção SQL
            $escapedValue = is_null($value) ? "NULL" : "'" . $value . "'";
            $setStatements[] = "$key = $escapedValue";
        }
        
        $setClause = implode(", ", $setStatements);
        $sql = "UPDATE $table SET $setClause";
        
        if ($where !== null) {
            $sql .= " WHERE $where";
        }
        
        try {
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute();
            
            // Para depuração, podemos registrar a consulta SQL
            // error_log("SQL Update Query: $sql");

        return $result; 
        } catch (PDOException $e) {
            // Registrar o erro e lançar novamente a exceção
            error_log("Erro ao executar update: " . $e->getMessage() . " - SQL: $sql");
            throw $e;
        }
    }

    public function delete(string $table, int $id): bool {
        $stmt = $this->connect();
        $sql = "DELETE FROM $table WHERE id = :id";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $result = $stmt->execute();
            
            return $result;
        } catch (PDOException $e) {
            error_log("Erro ao executar delete: " . $e->getMessage() . " - SQL: $sql");
            throw $e;
        }
    }

    public function read(string $table, array $columns, ?string $where = null, ?string $order = null): array {
        $stmt = $this->connect();
        $columns = implode(", ", $columns);
        $sql = "SELECT $columns FROM $table";

        if ($where !== null) {
            $sql .= " WHERE $where";
        }

        if ($order !== null) {
            $sql .= " ORDER BY $order";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $result;
    }
    
}