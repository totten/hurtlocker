<?php
namespace Hurtlocker;

class DaoDatabaseAdapter implements DatabaseInterface {

  protected $tx = NULL;

  public function transact($callback, ...$args): void {
    $tx = new \CRM_Core_Transaction();

    try {
      $result = $callback(...$args);
    }
    catch (\Throwable $t) {
      $tx->rollback();
      throw $t;
    }

    if ($result !== TRUE && $result !== FALSE) {
      throw new \RuntimeException("Error: transact() callback should indicate TRUE or FALSE");
    }

    if ($result === FALSE) {
      $tx->rollback();
    }
    $tx->commit();
  }

  public function execute(string $sql, array $params = []): void {
    // fprintf(STDERR, "SQL: %s %s\n", $sql, json_encode($params));
    \CRM_Core_DAO::executeQuery($sql, $params);
  }

  public function queryValue(string $sql) {
    return (int) \CRM_Core_DAO::singleValueQuery($sql);
  }

  public function queryColumn(string $sql, string $returnColumn): array {
    $result = [];
    foreach (\CRM_Core_DAO::executeQuery($sql)->fetchGenerator() as $row) {
      $result[] = $row->{$returnColumn};
    }
    return $result;
  }

  public function queryMap(string $sql, string $keyCol, string $valueCol): array {
    return \CRM_Core_DAO::executeQuery($sql)->fetchMap($keyCol, $valueCol);
  }

  public function queryAssoc(string $sql): array {
    return \CRM_Core_DAO::executeQuery($sql)->fetchAll();
  }

}
