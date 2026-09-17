#!/bin/bash

set -e

##############################################################################################

basedir="$(dirname "$(realpath "$0")")"
filename="$(basename "$(realpath "$0")")"
filename_noExt="${filename%.*}"
fullpath="$basedir/$filename"

##############################################################################################

info() { echo $(date +"%Y-%m-%d %H:%M:%S") [INFO] "$0": "$@" >&2 ; }
debug() { echo $(date +"%Y-%m-%d %H:%M:%S") [DEBUG] "$0": "$@" >&2; }
error() { echo $(date +"%Y-%m-%d %H:%M:%S") [ERROR] "$0": "$@" >&2; }

help() { error "try: bash ${filename} \"<inputfile>\" \"<timestamp seconds>\""; }

##############################################################################################

info "thumbnail creator started"

##############################################################################################

if [ -z "$1" ] || [ -z "$2" ]; then
    error "wrong parameter passed"
    help
    exit 1
fi

if [ ! -f "$1" ] ; then
    error "file $1 does not exist"
    exit 1
fi

##############################################################################################

input="$1"
timestamp="$2"
basename="${input%.*}"
thumbnail="${basename}.png"

ffmpeg -y -ss "$timestamp" -i "$input" -vframes 1 -q:v 2 -vf "scale=iw*0.5:ih*0.5" "$thumbnail" 2>&1 | awk 'NF {print strftime("%Y-%m-%d %H:%M:%S"), $0; fflush()}'

##############################################################################################

info "${thumbnail} thumbnail created"
info "thumbnail creator finished"

echo "${thumbnail#/home/twitchrecorder}"

##############################################################################################
