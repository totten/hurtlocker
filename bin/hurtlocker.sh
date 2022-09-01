#!/bin/bash

## usage: hurtlocker.sh <dbapi> <worker-config>
## example: hurtlocker.sh PDO aaa
## example: hurtlocker.sh PDO abd
## example: hurtlocker.sh DAO aaa
## example: hurtlocker.sh DAO abd

function do_cv() {
  ./bin/cv.phar -v "$@"
}

if [ -z "$1" -o -z "$2" ]; then
  echo "usage: $0 <dbapi> <worker-config>"
  exit 1
fi

DB_API="use$1"
WORKERS="$2"
LOGDIR=/tmp/hurtlocker
TS=$(date '+%Y-%m-%d-%H-%M-%S')
LOG="${LOGDIR}/${DB_API}-${WORKERS}-${TS}.log"
REPORT="${LOGDIR}/${DB_API}-${WORKERS}-${TS}.report"

### Go
trap "trap - SIGTERM && kill -- -$$" SIGINT SIGTERM EXIT
mkdir -p "$LOGDIR"

echo $DB_API init | do_cv scr tmp/hurtlocker.php 2>&1 | tee -a "$LOG"

case "$WORKERS" in
  aaa)
    echo $DB_API worker:1:a | do_cv scr tmp/hurtlocker.php 2>&1 | tee -a "$LOG" &
    echo $DB_API worker:2:a | do_cv scr tmp/hurtlocker.php 2>&1 | tee -a "$LOG" &
    echo $DB_API worker:3:a | do_cv scr tmp/hurtlocker.php 2>&1 | tee -a "$LOG" &
    ;;
  aab)
    echo $DB_API worker:1:a | do_cv scr tmp/hurtlocker.php 2>&1 | tee -a "$LOG" &
    echo $DB_API worker:2:a | do_cv scr tmp/hurtlocker.php 2>&1 | tee -a "$LOG" &
    echo $DB_API worker:3:b | do_cv scr tmp/hurtlocker.php 2>&1 | tee -a "$LOG" &
    ;;
  abd)
    echo $DB_API worker:1:a | do_cv scr tmp/hurtlocker.php 2>&1 | tee -a "$LOG" &
    echo $DB_API worker:2:b | do_cv scr tmp/hurtlocker.php 2>&1 | tee -a "$LOG" &
    echo $DB_API worker:3:d | do_cv scr tmp/hurtlocker.php 2>&1 | tee -a "$LOG" &
    ;;
  *)
    echo "Unrecognized pattern: $WORKERS"
    exit 1
    ;;
esac

wait

echo $DB_API report | do_cv scr tmp/hurtlocker.php | tee "$REPORT"
