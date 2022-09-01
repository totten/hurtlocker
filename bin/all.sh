#!/usr/bin/env bash
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
## Main

for DBTYPE in dao pdo ; do
for WORKERS in abcd-abcd-abcd abcd-abcd-bacd abcd-bacd-dcba ; do
for N in 1 2 3 ; do
  "$BINDIR"/hurtlocker.sh "$DBTYPE" "$WORKERS"
done
done
done
