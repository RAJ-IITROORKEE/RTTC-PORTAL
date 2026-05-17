<?php
/**
 * RTTC 2026 - Database Connection (Singleton)
 */
if (!defined('APP_INIT')) die('Direct access not permitted');

class Database
{
    private static ?Database $instance = null;
    private ?mysqli $connection = null;

    private function __construct()
    {
        $this->connection = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

        if ($this->connection->connect_error) {
            $msg = "DB Connection failed: " . $this->connection->connect_error;
            error_log($msg);
            if (APP_ENV === 'development') die($msg);
            die("Service temporarily unavailable. Please try again later.");
        }

        $this->connection->set_charset(DB_CHARSET);
        $this->connection->query("SET time_zone = '+05:30'");
    }

    public static function getInstance(): static
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    public function getConnection(): mysqli
    {
        return $this->connection;
    }

    /** Shorthand */
    public function prepare(string $sql): mysqli_stmt|false
    {
        return $this->connection->prepare($sql);
    }

    public function query(string $sql): mysqli_result|bool
    {
        return $this->connection->query($sql);
    }

    public function escape(string $value): string
    {
        return $this->connection->real_escape_string($value);
    }

    public function lastInsertId(): int
    {
        return (int) $this->connection->insert_id;
    }

    public function affectedRows(): int
    {
        return (int) $this->connection->affected_rows;
    }

    private function __clone() {}
    public function __wakeup(): never { throw new \Exception("Cannot unserialize DB singleton"); }
}

/** Global helper: returns mysqli connection */
function db(): mysqli
{
    return Database::getInstance()->getConnection();
}
