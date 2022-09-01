#!/usr/bin/env bash

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
## Utilities

function do_cv() {
  ## ../../bin/cv.phar -v "$@"
  cv -v "$@"
}

###############################################################################
## Load options

if [ -z "$1" -o -z "$2" ]; then
  echo "usage: $0 <dbapi> <worker-config>"
  exit 1
fi

export DB_API="$1"
export WORKERS="$2"
LOGDIR="$PRJDIR/log"
TS=$(date '+%Y-%m-%d-%H-%M-%S')
LOG="${LOGDIR}/${1}-${WORKERS}-${TS}.log"
REPORT="${LOGDIR}/${1}-${WORKERS}-${TS}.report"
INSTANCE='hurtlocker(getenv("DB_API"),getenv("WORKERS"))'

###############################################################################
### Main
trap "trap - SIGTERM && kill -- -$$" SIGINT SIGTERM EXIT
mkdir -p "$LOGDIR"

do_cv ev "$INSTANCE->init();" 2>&1 | tee -a "$LOG"

do_cv ev "$INSTANCE->worker(1);" 2>&1 | tee -a "$LOG" &
do_cv ev "$INSTANCE->worker(2);" 2>&1 | tee -a "$LOG" &
do_cv ev "$INSTANCE->worker(3);" 2>&1 | tee -a "$LOG" &
wait

do_cv ev "$INSTANCE->report();" 2>&1 | tee "$REPORT"
