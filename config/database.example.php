<?php
// Database Configuration
// INSTRUCTIONS: Copy this file to database.php and update the values with your actual database credentials

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Add your password here
define('DB_NAME', 'ams_db');

class Database
{
  private static $instance = null;
  private $connection;

  private function __construct()
  {
    try {
      $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

      if ($this->connection->connect_error) {
        throw new Exception("Connection failed: " . $this->connection->connect_error);
      }

      $this->connection->set_charset("utf8mb4");
    } catch (Exception $e) {
      die("Database connection error: " . $e->getMessage());
    }
  }

  public static function getInstance()
  {
    if (self::$instance === null) {
      self::$instance = new Database();
    }
    return self::$instance;
  }

  public function getConnection()
  {
    return $this->connection;
  }

  public function query($sql)
  {
    return $this->connection->query($sql);
  }

  public function prepare($sql)
  {
    return $this->connection->prepare($sql);
  }

  public function escape($value)
  {
    return $this->connection->real_escape_string($value);
  }

  public function lastInsertId()
  {
    return $this->connection->insert_id;
  }

  public function close()
  {
    if ($this->connection) {
      $this->connection->close();
    }
  }
}
