#!/bin/bash

info() { command echo $(date +"%Y-%m-%d %H:%M:%S") [INFO] "$0": "$@" >&2 ; }
error() { command echo $(date +"%Y-%m-%d %H:%M:%S") [ERROR] "$0": "$@" >&2; }

#######################################################################################

basedir=$(dirname "$(realpath $0)")
basefile=$(basename "$(realpath $0)")
basepath=$basedir/$basefile

#######################################################################################

if [ -z "$1" ]; then
    port=8082
else
    port="$1"
fi

mkdir -p $basedir/mounts/archive
chmod -R 777 $basedir/mounts/archive

#######################################################################################

if docker inspect "twitchrecorder_$port" > /dev/null 2>&1; then
    docker rm -f "twitchrecorder_$port" > /dev/null 2>&1
    info "twitchrecorder container on port $port undeployed"
# else
#     error "failed to undeploy twitchrecorder container on port $port"
#     error "container twitchrecorder_$port does not exist"
fi

if [ -n "$2" ]; then exit 1; fi

#######################################################################################

response=$(
    docker run -d --restart unless-stopped --name "twitchrecorder_$port" \
        -p $port:80 \
        -v /etc/timezone:/etc/timezone:ro \
        -v /etc/localtime:/etc/localtime:ro \
        -v $basedir/src/webpage/.htaccess:/home/twitchrecorder/.htaccess \
        -v $basedir/src/webpage/index.php:/home/twitchrecorder/index.php \
        -v $basedir/src/container/chatlog.js:/home/twitchrecorder/chatlog.js \
        -v $basedir/src/container/entrypoint.sh:/home/twitchrecorder/entrypoint.sh \
        -v $basedir/src/container/getStreamURL.js:/home/twitchrecorder/getStreamURL.js \
        -v $basedir/src/container/record.sh:/home/twitchrecorder/record.sh \
    thirtysix361/twitchrecorder 2>&1
)

if [ $? -eq 0 ]; then
    info "twitchrecorder container on port $port deployed"
else
    error "failed to deploy twitchrecorder container on port $port"
    error $response
fi

#######################################################################################
