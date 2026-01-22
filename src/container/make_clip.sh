#!/bin/bash

##############################################################################################

basedir="$(dirname "$(realpath "$0")")"
filename="$(basename "$(realpath "$0")")"
filename_noExt="${filename%.*}"
fullpath="$basedir/$filename"

##############################################################################################

info() { echo $(date +"%Y-%m-%d %H:%M:%S") [INFO] "$0": "$@" >&2 ; }
debug() { echo $(date +"%Y-%m-%d %H:%M:%S") [DEBUG] "$0": "$@" >&2; }
error() { echo $(date +"%Y-%m-%d %H:%M:%S") [ERROR] "$0": "$@" >&2; }

help() { error "try: bash ${filename} \"<inputfile>\" \"<from seconds>\" \"<to seconds>\""; }

##############################################################################################

info "clipper started"

##############################################################################################

if [ -z "$1" ] || [ -z "$2" ] || [ -z "$3" ]; then
    error "wrong parameter passed"
    help
    exit 1
fi

if [ ! -f "$1" ] ; then
    error "file $1 does not exist"
    exit 1
fi

##############################################################################################

pre=""

if [[ "$1" == */* ]]; then
    pre="${1%/*}"
fi

if [[ "$pre" != "" ]]; then
    pre="$pre/"
fi

time="${1%_*}"
filename="${1##*/}"
filename="${filename%.*}"
time="${filename##*_}"
tmp="${filename%_*}"
date="${tmp##*_}"
channel="${tmp%_*}"

output=${pre}${channel}-clip-$2-$3_${date}_${time}.mp4

ffmpeg -y -ss $2 -to $3 -i $1 -c copy "${output}"

##############################################################################################

info "clipper finished"

##############################################################################################
