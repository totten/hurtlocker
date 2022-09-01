#!/bin/bash

## usage: hurtlocker.sh <dbapi> <worker-config>
## example: hurtlocker.sh PDO aaa
## example: hurtlocker.sh PDO abd
## example: hurtlocker.sh DAO aaa
## example: hurtlocker.sh DAO abd

###############################################################################
## Bootstrap

## Determine the absolute path of the directory with the file
## usage: absdirname <file-path>
function absdirname() {
  pushd $(dirname $0) >> /dev/null
    pwd
  popd >> /dev/null
}

BINDIR=$(absdirname "$0")
PRJDIR=$(dirname "$BINDIR")

###############################################################################
function do_hurtlocker() {
  #./bin/cv.phar -v scr "$BINDIR"/hurtlocker.php
  cv -v scr "$BINDIR"/hurtlocker.php
}

###############################################################################
if [ -z "$1" -o -z "$2" ]; then
  echo "usage: $0 <dbapi> <worker-config>"
  exit 1
fi

DB_API="use$1"
WORKERS="$2"
LOGDIR="$PRJDIR/log"
TS=$(date '+%Y-%m-%d-%H-%M-%S')
LOG="${LOGDIR}/${1}-${WORKERS}-${TS}.log"
REPORT="${LOGDIR}/${1}-${WORKERS}-${TS}.report"

### Go
trap "trap - SIGTERM && kill -- -$$" SIGINT SIGTERM EXIT
mkdir -p "$LOGDIR"

echo $DB_API init | do_hurtlocker 2>&1 | tee -a "$LOG"

case "$WORKERS" in
  aaa)
    echo $DB_API worker:1:a | do_hurtlocker 2>&1 | tee -a "$LOG" &
    echo $DB_API worker:2:a | do_hurtlocker 2>&1 | tee -a "$LOG" &
    echo $DB_API worker:3:a | do_hurtlocker 2>&1 | tee -a "$LOG" &
    ;;
  aab)
    echo $DB_API worker:1:a | do_hurtlocker 2>&1 | tee -a "$LOG" &
    echo $DB_API worker:2:a | do_hurtlocker 2>&1 | tee -a "$LOG" &
    echo $DB_API worker:3:b | do_hurtlocker 2>&1 | tee -a "$LOG" &
    ;;
  abd)
    echo $DB_API worker:1:a | do_hurtlocker 2>&1 | tee -a "$LOG" &
    echo $DB_API worker:2:b | do_hurtlocker 2>&1 | tee -a "$LOG" &
    echo $DB_API worker:3:d | do_hurtlocker 2>&1 | tee -a "$LOG" &
    ;;
  *)
    echo "Unrecognized pattern: $WORKERS"
    exit 1
    ;;
esac

wait

echo $DB_API report | do_hurtlocker | tee "$REPORT"
