<?php

namespace Hurtlocker;

interface DatabaseInterface {

  public function transact($callback, ...$args): void;

  public function execute(string $sql, array $params = []): void;

  public function queryValue(string $sql);

  public function queryColumn(string $sql, string $returnColumn): array;

  public function queryMap(string $sql, string $keyCol, string $valueCol): array;

  public function queryAssoc(string $sql): array;

}
