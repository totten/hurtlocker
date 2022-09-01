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
## Utilities

function do_hurtlocker() {
  #./bin/cv.phar -v scr "$BINDIR"/hurtlocker.php
  echo "$CONFIG" "$@" | cv -v scr "$BINDIR"/hurtlocker.php
}

###############################################################################
## Load options

if [ -z "$1" -o -z "$2" ]; then
  echo "usage: $0 <dbapi> <worker-config>"
  exit 1
fi

DB_API="$1"
WORKERS="$2"
LOGDIR="$PRJDIR/log"
TS=$(date '+%Y-%m-%d-%H-%M-%S')
LOG="${LOGDIR}/${1}-${WORKERS}-${TS}.log"
REPORT="${LOGDIR}/${1}-${WORKERS}-${TS}.report"
CONFIG="config:$DB_API:$WORKERS"

###############################################################################
### Main
trap "trap - SIGTERM && kill -- -$$" SIGINT SIGTERM EXIT
mkdir -p "$LOGDIR"

do_hurtlocker 'init' 2>&1 | tee -a "$LOG"

do_hurtlocker 'worker:1' 2>&1 | tee -a "$LOG" &
do_hurtlocker 'worker:2' 2>&1 | tee -a "$LOG" &
do_hurtlocker 'worker:3' 2>&1 | tee -a "$LOG" &
wait

do_hurtlocker 'report' | tee "$REPORT"
