<?php
/**
 * Clase para construcción de consultas SQL
 */
namespace Core\Database;

class QueryBuilder {
    /**
     * Instancia de PDO
     * @var \PDO
     */
    private \PDO $pdo;
    
    /**
     * Tabla a consultar
     * @var string
     */
    private string $table;
    
    /**
     * Constructor
     *
     * @param \PDO $pdo Instancia de PDO
     */
    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Establece la tabla para operaciones
     *
     * @param string $table Nombre de la tabla
     * @return self
     */
    public function table(string $table): self {
        $this->table = $table;
        return $this;
    }
    
    /**
     * Selecciona todos los registros
     *
     * @param array $columns Columnas a seleccionar
     * @return array Registros encontrados
     */
    public function findAll(array $columns = ['*']): array {
        $columnList = implode(', ', $columns);
        $sql = "SELECT {$columnList} FROM {$this->table}";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Busca registros por condiciones
     *
     * @param array $conditions Condiciones WHERE [columna => valor]
     * @param array $columns Columnas a seleccionar
     * @return array Registros encontrados
     */
    public function find(array $conditions, array $columns = ['*']): array {
        $columnList = implode(', ', $columns);
        $whereClause = $this->buildWhereClause($conditions);
        
        $sql = "SELECT {$columnList} FROM {$this->table} WHERE {$whereClause}";
        
        $stmt = $this->pdo->prepare($sql);
        $this->bindValues($stmt, $conditions);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Busca un solo registro por condiciones
     *
     * @param array $conditions Condiciones WHERE [columna => valor]
     * @param array $columns Columnas a seleccionar
     * @return array|null Registro encontrado o null
     */
    public function findOne(array $conditions, array $columns = ['*']): ?array {
        $columnList = implode(', ', $columns);
        $whereClause = $this->buildWhereClause($conditions);
        
        $sql = "SELECT {$columnList} FROM {$this->table} WHERE {$whereClause} LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $this->bindValues($stmt, $conditions);
        $stmt->execute();
        
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }
    
    /**
     * Inserta un nuevo registro
     *
     * @param array $data Datos a insertar [columna => valor]
     * @return int|false ID insertado o false en error
     */
    public function insert(array $data) {
        $columns = array_keys($data);
        $columnsList = implode(', ', $columns);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        
        $sql = "INSERT INTO {$this->table} ({$columnsList}) VALUES ({$placeholders})";
        
        $stmt = $this->pdo->prepare($sql);
        $i = 1;
        foreach ($data as $value) {
            $stmt->bindValue($i++, $value);
        }
        
        if ($stmt->execute()) {
            return $this->pdo->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Actualiza registros
     *
     * @param array $data Datos a actualizar [columna => valor]
     * @param array $conditions Condiciones WHERE [columna => valor]
     * @return int Número de filas afectadas
     */
    public function update(array $data, array $conditions): int {
        $setClauses = [];
        foreach (array_keys($data) as $column) {
            $setClauses[] = "{$column} = :{$column}";
        }
        $setClause = implode(', ', $setClauses);
        
        $whereClause = $this->buildWhereClause($conditions, 'where_');
        
        $sql = "UPDATE {$this->table} SET {$setClause} WHERE {$whereClause}";
        
        $stmt = $this->pdo->prepare($sql);
        
        // Bind SET values
        foreach ($data as $column => $value) {
            $stmt->bindValue(":{$column}", $value);
        }
        
        // Bind WHERE values
        foreach ($conditions as $column => $value) {
            $stmt->bindValue(":where_{$column}", $value);
        }
        
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    /**
     * Elimina registros
     *
     * @param array $conditions Condiciones WHERE [columna => valor]
     * @return int Número de filas afectadas
     */
    public function delete(array $conditions): int {
        $whereClause = $this->buildWhereClause($conditions);
        
        $sql = "DELETE FROM {$this->table} WHERE {$whereClause}";
        
        $stmt = $this->pdo->prepare($sql);
        $this->bindValues($stmt, $conditions);
        $stmt->execute();
        
        return $stmt->rowCount();
    }
    
    /**
     * Cuenta registros según condiciones
     *
     * @param array $conditions Condiciones WHERE [columna => valor]
     * @return int Conteo de registros
     */
    public function count(array $conditions = []): int {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        
        if (!empty($conditions)) {
            $whereClause = $this->buildWhereClause($conditions);
            $sql .= " WHERE {$whereClause}";
        }
        
        $stmt = $this->pdo->prepare($sql);
        
        if (!empty($conditions)) {
            $this->bindValues($stmt, $conditions);
        }
        
        $stmt->execute();
        $result = $stmt->fetch();
        
        return (int) ($result['count'] ?? 0);
    }
    
    /**
     * Construye la cláusula WHERE para consultas
     *
     * @param array $conditions Condiciones [columna => valor]
     * @param string $prefix Prefijo para parámetros named
     * @return string Cláusula WHERE generada
     */
    private function buildWhereClause(array $conditions, string $prefix = ''): string {
        $clauses = [];
        foreach (array_keys($conditions) as $column) {
            $clauses[] = "{$column} = :{$prefix}{$column}";
        }
        
        return implode(' AND ', $clauses);
    }
    
    /**
     * Vincula valores a una consulta preparada
     *
     * @param \PDOStatement $stmt Declaración preparada
     * @param array $values Valores a vincular [columna => valor]
     * @param string $prefix Prefijo para parámetros named
     * @return void
     */
    private function bindValues(\PDOStatement $stmt, array $values, string $prefix = ''): void {
        foreach ($values as $column => $value) {
            $stmt->bindValue(":{$prefix}{$column}", $value);
        }
    }
    
    /**
     * Ejecuta una consulta SQL personalizada
     *
     * @param string $sql Consulta SQL
     * @param array $params Parámetros para bind
     * @return array Resultados de la consulta
     */
    public function raw(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $param => $value) {
            // Detectar si es un número o un named parameter
            if (is_numeric($param)) {
                $stmt->bindValue($param + 1, $value);
            } else {
                $stmt->bindValue(":{$param}", $value);
            }
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
}