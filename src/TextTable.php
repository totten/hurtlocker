<?php
namespace Hurtlocker;

class TextTable {

  const SEPARATOR = '-';

  public static function create(array $rows): string {
    $columns = array_keys($rows[0]);
    $widths = [];
    $isNumeric = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      foreach ($row as $column => $value) {
        $widths[$column] = max($widths[$column] ?? 0, strlen($value), strlen($column));
        $isNumeric[$column] = ($isNumeric[$column] ?? TRUE) && is_numeric($value);
      }
    }
    $fmt = '| ' . implode(' | ', array_map(
        function($column) use ($widths, $isNumeric) {
          $n = $widths[$column];
          return $isNumeric[$column] ? "%{$n}s" : "%-{$n}s";
        },
        $columns
      )) . " |\n";
    $hr = '+-' . implode('-+-', array_map(
        function($column) use ($widths, $isNumeric) {
          return str_repeat('-', $widths[$column]);
        },
        $columns
      )) . "-+\n";

    $buf = '';
    $buf .= $hr;
    $buf .= sprintf($fmt, ...$columns);
    $buf .= $hr;
    foreach ($rows as $row) {
      if (is_array($row)) {
        $buf .= sprintf($fmt, ...array_values(\CRM_Utils_Array::subset($row, $columns)));
      }
      elseif ($row === static::SEPARATOR) {
        $buf .= $hr;
      }
      else {
        $row .= '(MALFORMED ROW)';
      }
    }
    $buf .= $hr;
    return $buf;
  }

}
