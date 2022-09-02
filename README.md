# Hurtlocker

This extension is investigative code that demonstrates deadlocks and other brain-hurting things.

## Usage

Enable the extension:

```bash
cv en hurtlocker
```

Then run the script `hurtlocker.sh`:

```bash
## Formula
./bin/hurtlocker.sh DB_TYPE WORKER_LIST

### Example
./bin/hurtlocker.sh pdo abcd-dcba
```

`hurtlocker.sh` spawns several worker-processes that contend to access the same records. You will see a log of how these workers run. At the end, it shows a report about the successes and failures (exceptions/deadlocks) with a detailed list of the written data.

The script takes two parameters:

* `DB_TYPE`: Specifies how to interface with the database. Either:
    * `pdo`: Use `\PDO`. This is a more pristine representation of MySQL-PHP behavior.
    * `dao`: Use `\CRM_Core_DAO` and its parent `\DB_DataObject`. This is a better representation of what Civi does. Depending on version/configuration, it may have some extra deadlock logic (*which is subject to review/reconsideration*).
* `WORKER_LIST`: This is a hyphen-delimited list of workers, describing the updates performed by each worker. For example, let's breakdown `abcd-dcba`:
    * `abcd`: Worker #1 will perform a transaction with 4x updates (update table A; then table B; then table C; then table D).
    * `dcba`: Worker #2 will also perform a transaction with 4x updates. However, it does steps in the opposite order (update table D; then table C; then table B; then table A).

> The worker descriptions (eg `abcd`, `dcba`) and table names ("A", "B", "C", "D") are clearly abstract.  This particular investigation is
> not about debugging any specific deadlock -- it speaks to the general mechanics of deadlocks.


## Discussion

A deadlock arises when you have two (or more) worker-processes with conflicting lock requirements, eg:

* Worker #1 starts a transaction which updates (A) `civicrm_contact`, (B) `civicrm_email`, (C) `civicrm_phone`, and (D) `civicrm_cache` records (in that order - ABCD).
* Worker #2 starts a transaction which updates the same (D) `civicrm_cache`, (C) `civicrm_phone`, (B) `civicrm_email`, and (A) `civicrm_contact` records (but in reversed order - DCBA).

This example is ideal for provoking a deadlock -- each process has a conflicting sequence of updates.  However, it is
not _guaranteed_ to provoke a deadlock all the time.  Deadlocks are stochastic (dependent upon the timing of each step
in each process).  To intentionally provoke a deadlock, you must run several trials, and you should apply
timing-controls to increase the odds.

The `hurtlocker.sh` allows you to describe a list of worker-processes and their operations.  It will then run several
trials where the worker-processes compete. Any exceptions (eg deadlocks) are logged. At the end, it generates a report
to see which records have been truly updated (and which have been rolled back to their original/blank form).
These are recorded in the `./log` folder.

## Test Schema

The script initializes four example tables. They are designed to make deadlocks more likely. The example tables all look the same:

* `tbl_a` (`id int`, `field_w1 varchar null`, `field_w2 varchar null`, `field_w3 varchar null`)
* `tbl_b` (`id int`, `field_w1 varchar null`, `field_w2 varchar null`, `field_w3 varchar null`)
* `tbl_c` (`id int`, `field_w1 varchar null`, `field_w2 varchar null`, `field_w3 varchar null`)
* `tbl_d` (`id int`, `field_w1 varchar null`, `field_w2 varchar null`, `field_w3 varchar null`)

The tables are prepopulated with empty rows -- one row for each trial.

* In trial #1, there will be contention to fill-in data for record #1 across all tables (`tbl_{a,b,c,d}`).
* In trial #2, there will be contention to fill-in data for record #2 across all tables (`tbl_{a,b,c,d}`).
* In trial #3, there will be contention to fill-in data for record #3 across all tables (`tbl_{a,b,c,d}`).

Every worker tries to update the same records in the same tables. Specifically, the workers target their updates at these columns:

* Worker #1 seeks to update `field_w1` across all of `tbl_{a,b,c,d}`
* Worker #2 seeks to update `field_w2` across all of `tbl_{b,c,d,a}`
* Worker #3 seeks to update `field_w3` across all of `tbl_{d,c,b,a}`

> NOTE: It's not essential that each worker target separate fields. However, this makes it a bit easier to inspect the resulting data and understand what happens. If `field_w2` is blank, that means that Worker #2 failed to write to it.

For any worker to succeed, it will need to lock the contested record in all tables. However, these workers are not very social -- after acquiring locks, they go to `sleep()` to prevent other workers from updating the contested record.

## Reports / Logs

Let us take an example report. Each report includes a list of "Trials":

```
## Trials
+-------+---------------+-------+--------------------------------------------------------------+
| trial | worker        | is_ok | message                                                      |
+-------+---------------+-------+--------------------------------------------------------------+
|     1 | worker:1:abcd |     1 |                                                              |
|     1 | worker:2:dcba |     0 | PDOException: "SQLSTATE[40001]: Serialization failure: 12... |
+-------+---------------+-------+--------------------------------------------------------------+
```

This shows how well each worker behaved in trial #1.  Ideally, all workers in the trial achieve `is_ok=1`.  However, you can
see that one worker encountered a deadlock-exception.

What was the impact of the deadlock?  Did `worker:2:dcba` write all of its data?  Or was it all rolled back?  Or did it perform a partial
write?

The answer comes in the next part of the report ("Data"), which shows the final disposition of each update:

```
## Data
+------+----------------------------------------+
| step | trial id#1                             |
+------+----------------------------------------+
| 1:a  | "worker:1:abcd updated tbl_a.field_w1" |
| 1:b  | "worker:1:abcd updated tbl_b.field_w1" |
| 1:c  | "worker:1:abcd updated tbl_c.field_w1" |
| 1:d  | "worker:1:abcd updated tbl_d.field_w1" |
+------+----------------------------------------+
| 2:d  | null                                   |
| 2:c  | null                                   |
| 2:b  | null                                   |
| 2:a  | null                                   |
+------+----------------------------------------+
```

Here, we see that `worker:1:abcd` succeeded in performing 4x writes (`tbl_a.field_w1`, `tbl_b.field_w1`, `tbl_c.field_w1`, and `tbl_d.field_w1`).

For contrast, `worker:2:dcba` was _supposed to_ perform 4x writes.  But it didn't.  In fact, none of the writes went through.  This is
consistent with a rollback (triggered by the deadlock).

## Initial observations

I've included logs from a few runs on MySQL 8.0.26. Some observations:

* Comparing worker scenarios:
    * In all trials of `abcd-abcd-abcd` ([example](log/dao-abcd-abcd-abcd-2022-09-01-00-44-46.report)), it succeeds (*as you might expect, given that the 3 workers agree on the write sequence*).
    * In all trials of `abcd-bacd-dcba` ([example](log/pdo-abcd-bacd-dcba-2022-09-01-00-51-53.report)), it has problems (*as you might expect, given that the starkly different write sequences*)
* Comparing DB drivers
    * `pdo-*` ([example](log/pdo-abcd-bacd-dcba-2022-09-01-00-51-53.report)) reports deadlocks with exceptions. You see either the entire block succeeds or the entire block fails. This is consistent with a transaction-rollback.
    * `dao-*` ([example](log/dao-abcd-bacd-dcba-2022-09-01-00-45-48.report)) always reports `is_ok=1` (in its current revision of `DB_DataObject`; md5sum=20f0046845ed6551cc02cf0c44c4e8e0), even if there was deadlock. This
      is consistent with `DB_DataObject`s low-level/single-statement retry mechanism. However, you can also see that there is missing data. This is also consistent with the
      low-level/single-statement retry (*the main transaction was rolled back, which removed earlier writes - which are not known to this retry-agent*).
