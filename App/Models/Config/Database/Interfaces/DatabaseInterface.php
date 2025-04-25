<?php
namespace Config\Database\Interfaces;

interface DatabaseInterface {

    // public function connect (): PDO|null; 
    public function create (array $data, string $table, ?string $where = null): bool;
    public function update (array $data, string $table): bool;
    public function delete (string $table, int $id): bool;
    public function read (string $table, array $columns, ?string $where = null, ?string $order = "ASC"): array;
}