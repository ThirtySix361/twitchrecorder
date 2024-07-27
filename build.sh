#!/bin/bash

basedir=$(dirname "$(realpath $0)")

cd $basedir

docker build -t twitchrecorder .
