#!/bin/bash

info() { command echo $(date +"%Y-%m-%d %H:%M:%S") [INFO] "$0": "$@" >&2 ; }
error() { command echo $(date +"%Y-%m-%d %H:%M:%S") [ERROR] "$0": "$@" >&2; }

#######################################################################################

basedir=$(dirname "$(realpath $0)")
basefile=$(basename "$(realpath $0)")
basepath=$basedir/$basefile

#######################################################################################

if [ -z "$1" ]; then
    error "no channel set"
    info "try: bash $basepath <channelname>"
    exit 1
fi

mkdir -p $basedir/mounts/archive
chmod -R 777 $basedir/mounts/archive

#######################################################################################

docker rm -f twitchrecorder_"$1" > /dev/null 2>&1

docker run -d --restart unless-stopped --name twitchrecorder_"$1" \
  -e channel="$1" \
  -v /etc/timezone:/etc/timezone:ro \
  -v /etc/localtime:/etc/localtime:ro \
  -v $basedir/mounts/archive/:/home/twitchrecorder/archive/ \
  twitchrecorder $1 > /dev/null 2>&1

if [ $? -eq 0 ]; then
    info "twitchrecorder container deployed for $1"
else
    error "failed to deploy container for $1"
fi

#######################################################################################
