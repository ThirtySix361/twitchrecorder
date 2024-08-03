#!/bin/bash

basedir=$(dirname "$(realpath $0)")
basefile=$(basename "$(realpath $0)")
basepath=$basedir/$basefile

if [ -z "$1" ]; then
    echo "error: no channel set"
    echo "try: bash $basepath <channelname>"
    exit 1
fi

mkdir -p $basedir/mounts/archive
chmod -R 777 $basedir/mounts/archive

docker rm -f twitchrecorder_"$1"

docker run -d --restart unless-stopped --name twitchrecorder_"$1" \
  -e channel="$1" \
  -v /etc/timezone:/etc/timezone:ro \
  -v /etc/localtime:/etc/localtime:ro \
  -v $basedir/mounts/archive/:/home/twitchrecorder/archive/ \
  twitchrecorder $1
