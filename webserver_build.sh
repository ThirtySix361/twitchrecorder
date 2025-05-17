#!/bin/bash

info() { command echo $(date +"%Y-%m-%d %H:%M:%S") [INFO] "$0": "$@" >&2 ; }
error() { command echo $(date +"%Y-%m-%d %H:%M:%S") [ERROR] "$0": "$@" >&2; }

#######################################################################################

basedir=$(dirname "$(realpath $0)")
basefile=$(basename "$(realpath $0)")
basepath="${basedir}/${basefile}"

#######################################################################################

info "started"

docker rm -f twitchrecorder_webserver
docker build -t thirtysix361/twitchrecorder_webserver $basedir/build/webserver/
docker system prune -f
bash $basedir/webserver_run.sh

info "finished"

#######################################################################################
