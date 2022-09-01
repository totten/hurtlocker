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

This takes two main parameters:

* `DB_TYPE`: Specifies how to interface with the database. Either:
    * `pdo`: Use `\PDO`. This is a more pristine representation of MySQL-PHP behavior.
    * `dao`: Use `\CRM_Core_DAO` and its parent `\DB_DataObject`. Depending on version/configuration, this may have some extra deadlock logic (*which is subject to review/reconsideration*).
* `WORKER_LIST`: This is a hyphen-delimited list of workers, describing the updates performed by each worker. For example, let's breakdown `abcd-dcba`:
    * `abcd`: Worker #1 will perform a transaction with 4x updates (update table A; then table B; then table C; then table D).
    * `dcba`: Worker #2 will also perform a transaction with 4x updates. However, it does steps in the opposite order (update table D; then table C; then table B; then table A).

> The worker descriptions (eg `abcd`, `dcba`) and table names ("A", "B", "C", "D") are clearly abstract.  This
> particular investigation speaks to the *general* handling of deadlocks.

## Discussion

A deadlock arises when you have two (or more) worker-processes with conflicting lock requirements, eg:

* Worker #1 starts a transaction which updates `civicrm_contact` (A), `civicrm_email` (B), `civicrm_phone` (C), and `civicrm_cache` (D) records (in that order - ABCD).
* Worker #2 starts a transaction which updates the same `civicrm_cache` (D), `civicrm_phone` (C), `civicrm_email` (B), and `civicrm_contact` (A)  records (in this reversed order - DCBA).

This example is ideal for provoking a deadlock -- each process has a conflicting sequence of updates.  However, it is
not _guaranteed_ to provoke a deadlock all the time.  Deadlocks are stochastic (dependent upon the timing of each step
in each process).  To intentionally provoke a deadlock, you must run several trials, and you should apply
timing-controls to increase the odds.

The `hurtlocker.sh` allows you to describe a list of worker-processes and their operations.  It will then run several
trials where the worker-processes compete. Any exceptions (eg deadlocks) are logged. At the end, it generates a report
to see which records have been truly updated (and which have been rolled back to their original/blank form).
These are recorded in the `./log` folder.

## Reports / Logs

Let us take an example:

```
## Trials
+-------+---------------+-------+--------------------------------------------------------------+
| trial | worker        | is_ok | message                                                      |
+-------+---------------+-------+--------------------------------------------------------------+
|     1 | worker:1:abcd |     1 |                                                              |
|     1 | worker:2:dcba |     0 | PDOException: "SQLSTATE[40001]: Serialization failure: 12... |
+-------+---------------+-------+--------------------------------------------------------------+
```

This shows how well each worker behaved in trial #1.  Ideally, all process complete with `is_ok=1`.  However, you can
see that one worker a deadlock exception.

What was the impact of the deadlock?  Did worker:2 write all of its data?  Or was it all rolled back? 
Or did it perform a partial write? The next part of the report ("Data") reveals the final disposition of each update:

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
    * In all samples of `abcd-abcd-abcd`, it always succeeds.
    * In all samples of `abcd-bacd-dcba`, it always has problems (*though the manifestations shift around, as you might expect).
* Comparing drivers
    * `pdo-*` reports deadlocks with exceptions. You see either the entire block succeeds or the entire block fails. This is consistent with a transaction-rollback.
    * `dao-*` (in its current revision of `DB_DataObject`; md5sum=20f0046845ed6551cc02cf0c44c4e8e0) always reports `is_ok=1`. However, it suffers from broken data.
      This is consistent with combining MySQL's transaction-level rollback (*which reverts the earlier update*) with `DB_DataObject`'s statement-level retry (*which 
      does not retry earlier updates*).
