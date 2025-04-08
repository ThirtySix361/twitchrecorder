#!/bin/bash

info() { command echo $(date +"%Y-%m-%d %H:%M:%S") [INFO] "$0": "$@" >&2 ; }
error() { command echo $(date +"%Y-%m-%d %H:%M:%S") [ERROR] "$0": "$@" >&2; }

#######################################################################################

basedir=$(dirname "$(realpath $0)")
basefile=$(basename "$(realpath $0)")
basepath=$basedir/$basefile

#######################################################################################

if [ -z "$1" ]; then
    port=8080
else
    port="$1"
fi

mkdir -p $basedir/mounts/
chmod -R 777 $basedir/mounts/

#######################################################################################

docker rm -f twitchrecorder__webserver 2>/dev/null

docker run -d --restart unless-stopped --name twitchrecorder__webserver \
  -p $port:80 \
  -v /etc/timezone:/etc/timezone:ro \
  -v /etc/localtime:/etc/localtime:ro \
  -v $basedir/mounts/:/var/www/html \
  php:7.2-apache

if [ $? -eq 0 ]; then
    info "webserver published on port: $port"
else
    error "failed to start the webserver"
fi

#######################################################################################
