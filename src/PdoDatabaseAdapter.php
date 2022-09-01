<?php
namespace Hurtlocker;

use PDO;

class PdoDatabaseAdapter implements DatabaseInterface {

  /**
   * @var \PDO
   */
  protected $pdo;

  public function __construct() {
    $dsninfo = \Civi\Test::dsn();

    $host = $dsninfo['hostspec'];
    $port = @$dsninfo['port'];
    $pdoDsn = "mysql:host={$host}" . ($port ? ";port=$port" : "") . ';dbname=' . $dsninfo['database'];
    $this->pdo = new PDO($pdoDsn, $dsninfo['username'], $dsninfo['password'], [
      PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => TRUE,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
  }

  public function transact($callback, ...$args): void {
    $this->pdo->exec('BEGIN');

    try {
      $result = $callback(...$args);
    }
    catch (\Throwable $t) {
      $this->pdo->exec('ROLLBACK');
      throw $t;
    }

    if ($result !== TRUE && $result !== FALSE) {
      throw new \RuntimeException("Error: transact() callback should indicate TRUE or FALSE");
    }

    if ($result === FALSE) {
      $this->pdo->exec('ROLLBACK');
    }
    else {
      $this->pdo->exec('COMMIT');
    }
  }

  public function execute(string $sql, array $params = []): void {
    $raw = \CRM_Core_DAO::composeQuery($sql, $params);
    $this->pdo->exec($raw);
  }

  public function queryValue(string $sql) {
    $query = $this->pdo->query($sql);
    $query->execute();
    foreach ($query as $row) {
      foreach ($row as $key => $value) {
        return $value;
      }
    }
  }

  public function queryColumn(string $sql, string $returnColumn): array {
    $query = $this->pdo->query($sql);
    $query->execute();
    $results = [];
    foreach ($query as $row) {
      $results[] = $row[$returnColumn];
    }
    return $results;
  }

  public function queryMap(string $sql, string $keyCol, string $valueCol): array {
    $query = $this->pdo->query($sql);
    $query->execute();
    $results = [];
    foreach ($query as $row) {
      $results[$row[$keyCol]] = $row[$valueCol];
    }
    return $results;
  }

  public function queryAssoc(string $sql): array {
    $query = $this->pdo->query($sql);
    $query->execute();
    $results = [];
    foreach ($query as $row) {
      $results[] = $row;
    }
    return $results;
  }

}
