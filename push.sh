#!/bin/bash

basedir=$(dirname "$(realpath $0)")

cd $basedir

docker tag twitchrecorder thirtysix361/twitchrecorder:latest
docker push thirtysix361/twitchrecorder:latest
docker rmi thirtysix361/twitchrecorder:latest
