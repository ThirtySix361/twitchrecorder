#!/bin/bash

info() { command echo $(date +"%Y-%m-%d %H:%M:%S") [INFO] "$0": "$@" >&2 ; }
error() { command echo $(date +"%Y-%m-%d %H:%M:%S") [ERROR] "$0": "$@" >&2; }

#######################################################################################

basedir=$(dirname "$(realpath $0)")
basefile=$(basename "$(realpath $0)")
basepath=$basedir/$basefile

#######################################################################################

port=8081
mountpath="$basedir/mnt/archive/"

for arg in "$@"; do
    case $arg in
        port=*) port="${arg#*=}" ;;
        mount=*) mountpath="${arg#*=}" ;;
        auth=*)
            auth="${arg#*=}"
            if [[ "$auth" == *:* ]]; then
                user="${auth%%:*}"
                pass="${auth##*:}"
            fi ;;
        r) remove_only=true ;;
        *) echo "Unbekannter Parameter: $arg" ;;
    esac
done

#######################################################################################

if docker inspect "twitchrecorder_$port" > /dev/null 2>&1; then
    docker rm -f "twitchrecorder_$port" > /dev/null 2>&1
    info "twitchrecorder container on port $port undeployed"
# else
#     error "failed to undeploy twitchrecorder container on port $port"
#     error "container twitchrecorder_$port does not exist"
fi

if [ -n "$remove_only" ]; then exit 1; fi

mkdir -p $mountpath
chmod -R 777 $mountpath

#######################################################################################

response=$(docker run -d --restart unless-stopped --name "twitchrecorder_$port" \
    -p $port:80 \
    -e USER="$user" \
    -e PASS="$pass" \
    -v /etc/timezone:/etc/timezone:ro \
    -v /etc/localtime:/etc/localtime:ro \
    -v $mountpath:/home/twitchrecorder/archive/ \
    thirtysix361/twitchrecorder 2>&1)

if [ $? -eq 0 ]; then
    info "twitchrecorder container on port $port deployed"
else
    error "failed to deploy twitchrecorder container on port $port"
    error $response
fi

#######################################################################################
