#!/bin/bash

info() { command echo $(date +"%Y-%m-%d %H:%M:%S") [INFO] "$0": "$@" >&2 ; }
error() { command echo $(date +"%Y-%m-%d %H:%M:%S") [ERROR] "$0": "$@" >&2; }

#######################################################################################

basedir=$(dirname "$(realpath $0)")
basefile=$(basename "$(realpath $0)")
basepath="${basedir}/${basefile}"

#######################################################################################

info "started"

running=$(docker ps --format "{{.Names}}" | grep "twitchrecorder" | grep -v "twitchrecorder_webserver" | sed 's/^twitchrecorder_//')
echo "$running" | xargs -I {} docker rm -f twitchrecorder_{}
docker build -t thirtysix361/twitchrecorder $basedir/build/recorder/
docker system prune -f
echo "$running" | xargs -I {} bash "$basedir"/run.sh {}

info "finished"

#######################################################################################
