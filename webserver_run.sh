#!/bin/bash

info() { command echo $(date +"%Y-%m-%d %H:%M:%S") [INFO] "$0": "$@" >&2 ; }
error() { command echo $(date +"%Y-%m-%d %H:%M:%S") [ERROR] "$0": "$@" >&2; }

#######################################################################################

basedir=$(dirname "$(realpath $0)")
basefile=$(basename "$(realpath $0)")
basepath=$basedir/$basefile

#######################################################################################

if [ -z "$1" ]; then
    port=8081
else
    port="$1"
fi

mkdir -p $basedir/mounts/archive/
chmod -R 777 $basedir/mounts/archive/

#######################################################################################

docker rm -f twitchrecorder_webserver > /dev/null 2>&1

docker run -d --restart unless-stopped --name twitchrecorder_webserver \
    -p $port:80 \
    -v /etc/timezone:/etc/timezone:ro \
    -v /etc/localtime:/etc/localtime:ro \
    -v $basedir/mounts/archive/:/var/www/html/archive/ \
    thirtysix361/twitchrecorder_webserver > /dev/null 2>&1

#######################################################################################

if [ $? -eq 0 ]; then
    info "webserver published on port: $port"
else
    error "failed to start the webserver"
fi

#######################################################################################
