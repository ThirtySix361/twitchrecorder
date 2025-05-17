#!/bin/bash

basedir=$(dirname "$(realpath $0)")

cd $basedir

docker push thirtysix361/twitchrecorder_webserver:latest
docker rmi thirtysix361/twitchrecorder_webserver:latest
