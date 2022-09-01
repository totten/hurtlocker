<?php
namespace Hurtlocker;

class Hurtlocker {

  /**
   * Number of example records to put in the DB (during initialization).
   *
   * @var int
   */
  public $recordCount = 10;

  /**
   * Number of times to run the competing workers.
   *
   * @var int
   */
  public $trialCount = 2;

  /**
   * Every $N seconds, we begin a new trial.
   *
   * @var int
   */
  public $trialDuration = 4;

  /**
   * Some workers have the unfortunate habit of locking resources and then sleeping
   * (before releasing them). How long should they hold the lock?
   *
   * @var float
   */
  public $lockDuration = 1.5;

  /**
   * Time at which this process started
   *
   * @var int
   *   Seconds since epoch
   */
  protected $startTime;

  /**
   * Name of the active task (being run by this process).
   *
   * @var string
   */
  protected $activeTask = '';

  /**
   * A list of known write-sequences.
   * @var array
   */
  protected $writeSequences;

  /**
   * The list of config options that describe the workers
   *
   * @var string
   *   ex: 'aab' or 'abd'
   */
  protected $workerSeries;

  /**
   * @var DatabaseInterface
   */
  private $db;

  public function __construct() {
    $this->startTime = time();
    $this->writeSequences = [];
    $this->writeSequences['a'] = ['tbl_a', 'tbl_b', 'tbl_c', 'tbl_d'];
    $this->writeSequences['b'] = ['tbl_b', 'tbl_a', 'tbl_c', 'tbl_d'];
    $this->writeSequences['c'] = ['tbl_c', 'tbl_a', 'tbl_b', 'tbl_d'];
    $this->writeSequences['d'] = ['tbl_d', 'tbl_c', 'tbl_b', 'tbl_a'];
  }

  public function main(string $stdin): int {
    $tasks = explode(" ", trim($stdin));
    foreach ($tasks as $task) {
      $task = trim($task);

      try {
        $taskArgs = explode(":", $task);
        $taskFunc = array_shift($taskArgs);
        if (!is_callable([$this, $taskFunc])) {
          throw new \RuntimeException("Invalid task: \"$task\"");
        }

        $this->activeTask = $task;
        $this->{$taskFunc}(...$taskArgs);
      }
      catch (\Throwable $t) {
        echo \CRM_Core_Error::formatTextException($t);
        throw $t;
      }
      finally {
        $this->activeTask = NULL;
      }
    }

    return 0;
  }

  public function config(string $dbType, string $workerSeries): void {
    switch (strtolower($dbType)) {
      case 'dao':
        $this->db = new DaoDatabaseAdapter();
        break;

      case 'pdo':
        $this->db = new PdoDatabaseAdapter();
        break;
    }

    $this->workerSeries = $workerSeries;
  }

  public function init(): void {
    $this->note("Initialize tables (%s)\n", implode(',', ['tbl_trials']));
    $this->db->execute("DROP TABLE IF EXISTS tbl_trials");
    $this->db->execute("CREATE TABLE tbl_trials (`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, trial INT NOT NULL, worker VARCHAR(64) NOT NULL, is_ok INT NOT NULL, message TEXT, write_seq TEXT) ENGINE=InnoDB");

    $tableSeq = ['tbl_a', 'tbl_b', 'tbl_c', 'tbl_d'];
    $this->note("Initialize tables (%s) with %d records\n", implode(',', $tableSeq), $this->recordCount);
    foreach ($tableSeq as $table) {
      $this->db->execute("DROP TABLE IF EXISTS $table");
      $this->db->execute("DROP TABLE IF EXISTS $table");
      $this->db->execute("CREATE TABLE $table (`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, field_w1 varchar(64) NULL, field_w2 varchar(64) NULL, field_w3 varchar(64) NULL) ENGINE=InnoDB");
      for ($i = 1; $i <= $this->recordCount; $i++) {
        $this->db->execute("INSERT INTO $table () VALUES ()");
      }
    }
  }

  public function worker($workerId): void {
    $writeSeq = $this->getWorkerWriteSequence($workerId);
    $fieldName = "field_w{$workerId}";

    for ($trialId = 1; $trialId <= $this->trialCount; $trialId++) {
      $this->align($trialId);
      try {
        $this->db->transact(function($trialId) use ($writeSeq, $fieldName) {
          $this->note("For trial #%d, run task \"%s\" (sequence: %s)\n", $trialId, $this->activeTask, implode(',', $writeSeq));
          $this->updateRecords($writeSeq, [$trialId], $fieldName, "{$this->activeTask} committed $fieldName");
          $this->sleep();
          return TRUE; /* Commit this unit of work */
        }, $trialId);
        $isOK = 1;
        $data = '';
      }
      catch (\Throwable $t) {
        $data = \CRM_Core_Error::formatTextException($t);
        $this->note("FAILURE: In trial #%d, task \"%s\" raised exception: %s\n", $trialId, $this->activeTask, $data);
        $isOK = 0;
      }

      $this->db->execute("INSERT INTO tbl_trials (trial, worker, is_ok, message, write_seq) VALUES (%1, %2, %3, %4, %5)", [
        1 => [$trialId, 'Positive'],
        2 => [$this->activeTask, 'String'],
        3 => [$isOK, 'Int'],
        4 => [$data, 'String'],
        5 => [json_encode($writeSeq), 'String']
      ]);
    }
  }

  protected function getWorkerWriteSequence(int $workerId): array {
    return $this->writeSequences[$this->workerSeries[$workerId - 1]];
  }

  public function report(): void {
    $config = [];
    foreach (['workerSeries', 'recordCount', 'trialCount', 'trialDuration', 'lockDuration'] as $key) {
      $config[] = ['key' => $key, 'value' => $this->{$key}];
    }
    $config[] = ['key' => 'db', 'value' => get_class($this->db)];
    if ($this->db instanceof DaoDatabaseAdapter) {
      $dbDataObject = new \ReflectionClass('DB_DataObject');
      $config[] = ['key' => 'md5(DB_DataObject)', 'value' => md5(file_get_contents($dbDataObject->getFileName()))];
      $config[] = ['key' => 'CIVICRM_DEADLOCK_RETRIES', 'value' => CIVICRM_DEADLOCK_RETRIES];
    }
    printf("## %s\n%s\n", 'Configuration', TextTable::create($config));

    $trials = array_map(
      function(array $trial) {
        $messageLines = explode("\n", $trial['message'] ?? "");
        $trial['message'] = \CRM_Utils_String::ellipsify($messageLines[0], 60);
        return $trial;
      },
      $this->db->queryAssoc('SELECT trial, worker, write_seq, is_ok, message FROM tbl_trials ORDER BY trial, worker')
    );
    printf("## %s\n%s\n", 'Trials', TextTable::create($trials));

    $tableSeq = ['tbl_a', 'tbl_b', 'tbl_c', 'tbl_d'];
    $rows = [];
    foreach (['field_w1', 'field_w2', 'field_w3'] as $field) {
      foreach ($tableSeq as $table) {
        $values = $this->db->queryMap("SELECT id, $field FROM $table", 'id', $field);

        $row = ['field' => $field, 'table' => $table];
        for ($id = 1; $id <= $this->trialCount; $id++) {
          $row["trial id#$id"] = json_encode($values[$id]);
        }
        $rows[] = $row;
      }
    }
    printf("## %s\n%s\n", 'Data', TextTable::create($rows));
  }

  /**
   * Just verify that commit+rollback work as expected.
   */
  public function validate(): void {
    $tableSeq = ['tbl_a', 'tbl_b', 'tbl_c', 'tbl_d'];
    $this->assertValueCount(0, 'field_w1="committed"', $tableSeq);
    for ($id = 1; $id <= 10; $id++) {
      $this->db->transact(function() use ($tableSeq, $id) {
        $this->updateRecords($tableSeq, [$id], 'field_w1', 'committed');
        return ($id % 5) === 0;
      });
    }
    $this->assertValueCount(2, 'field_w1="committed"', $tableSeq);
    $this->note("OK\n");
  }

  protected function note($message, ...$args): void {
    $pid = getmypid();
    fprintf(STDERR,'[%d:%s] ' . $message, $pid, $this->activeTask, ...$args);
  }

  /**
   * Update a set of records.
   *
   * @param string|string[] $tableSeq
   *   List of tables to update.
   * @param int|int[] $ids
   *   List of record IDs to update.
   * @param string $field
   *   The field to update.
   * @param string $value
   *   The new to apply.
   */
  protected function updateRecords($tableSeq, $ids, string $field, string $value): void {
    $tableSeq = (array) $tableSeq;
    $ids = (array) $ids;
    foreach ($ids as $id) {
      foreach ($tableSeq as $table) {
        $this->db->execute("UPDATE $table SET $field = %1 WHERE id = %2", [
          1 => [$value, 'String'],
          2 => [$id, 'Positive'],
        ]);
      }
    }
  }

  protected function assertValueCount(int $expectCount, string $where, $tableSeq) {
    $tableSeq = (array) $tableSeq;
    foreach ($tableSeq as $table) {
      $actualCount = $this->db->queryValue("SELECT count(*) count FROM $table WHERE $where");
      if ($actualCount !== $expectCount) {
        throw new \RuntimeException("Queried \"$table\" for \"$where\". Expected $expectCount records but found $actualCount records.");
      }
    }
  }

  /**
   * If workers are launched within 1-2 seconds of each other, they should start roughly the same
   * time. Align on the next 5th-second-interval.
   */
  protected function align(int $stepNum = 0): void {
    $target = $this->trialDuration * ceil($this->startTime / $this->trialDuration);
    $target += ($stepNum * $this->trialDuration);

    $this->note("Align for trial #%d circa %s\n", $stepNum, date('Y-m-d H:i:s', $target));
    $this->sleep($target - microtime(1));
  }

  protected function sleep(?float $sec = NULL): void {
    if ($sec !== NULL) {
      usleep(round(1000 * 1000 * $sec));
    }
    else {
      usleep(1000 * 1000 * $this->lockDuration);
    }
  }
}
