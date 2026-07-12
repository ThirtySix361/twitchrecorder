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

filter_log() {
    local input="$1"
    local output="$2"
    local start="$3"
    local end="$4"

    local INPUT="${input%.*}.log"
    local OUTPUT="${output%.*}.log"

    to_seconds() {
        IFS=: read -ra t <<< "$1"
        if [ ${#t[@]} -eq 2 ]; then
            echo $((10#${t[0]} * 60 + 10#${t[1]}))
        else
            echo $((10#${t[0]} * 3600 + 10#${t[1]} * 60 + 10#${t[2]}))
        fi
    }

    start_sec=$(to_seconds "$start")
    end_sec=$(to_seconds "$end")

    awk -v start="$start_sec" -v end="$end_sec" '{
        split($1, t, ":")
        sec = t[1]*3600 + t[2]*60 + t[3]
        if (sec >= start && sec <= end) {
            new = sec - start
            h = int(new / 3600)
            m = int((new % 3600) / 60)
            s = new % 60
            $1 = sprintf("%02d:%02d:%02d", h, m, s)
            print
        }
    }' "$INPUT" > "$OUTPUT"
}

halving() {
    local input="$1"
    echo "$input" | awk -F: '
    {
        split($3, arr, ".");
        hours = $1;
        minutes = $2;
        seconds = arr[1];
        milliseconds = arr[2];

        total_seconds = hours * 3600 + minutes * 60 + seconds + milliseconds / 100;
        half_seconds = total_seconds / 2;

        h = int(half_seconds / 3600);
        m = int((half_seconds % 3600) / 60);
        s = int(half_seconds % 60);
        ms = int((half_seconds - int(half_seconds)) * 100);

        printf "%02d:%02d:%02d.%02d\n", h, m, s, ms;
    }'
}

thumbnail() {
    local input="$1"
    local basename="${input%.*}"
    local INPUT="${basename}.mp4"
    local THUMBNAIL="${basename}.png"

    duration=$(ffmpeg -i "${INPUT}" 2>&1 | grep "Duration" | cut -d ' ' -f 4 | sed s/,//)
    halfduration=$(halving "${duration}")

    ffmpeg -y -ss $halfduration -i "${INPUT}" -vframes 1 -q:v 2 -vf "scale=iw*0.5:ih*0.5" "${THUMBNAIL}" 2>&1 | awk 'NF {print strftime("%Y-%m-%d %H:%M:%S"), $0; fflush()}'

    info "${THUMBNAIL} thumbnail created"
}

format_time() {
    input="$1"
    separator="$2"

    if [[ "$input" =~ ^[0-9]+$ ]]; then
        seconds="$input"
    elif [[ "$input" =~ ^([0-9]+):([0-9]{2}):([0-9]{2})$ ]]; then
        seconds=$(( ${BASH_REMATCH[1]} * 3600 + ${BASH_REMATCH[2]} * 60 + ${BASH_REMATCH[3]} ))
    elif [[ "$input" =~ ^([0-9]+):([0-9]{2})$ ]]; then
        seconds=$(( ${BASH_REMATCH[1]} * 60 + ${BASH_REMATCH[2]} ))
    else
        echo "Ungültiges Format: $input" >&2
        return 1
    fi

    printf "%02d${separator}%02d${separator}%02d\n" $((seconds / 3600)) $((seconds / 60 % 60)) $((seconds % 60))
}

add_time() {
    IFS=- read -r h1 m1 s1 <<< "$1"
    IFS=- read -r h2 m2 s2 <<< "$2"
    seconds=$((h1*3600 + m1*60 + s1 + h2*3600 + m2*60 + s2))
    printf "%02d-%02d-%02d\n" $((seconds/3600)) $(((seconds%3600)/60)) $((seconds%60))
}

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

if [[ $(format_time "$2" ":") > $(format_time "$3" ":") ]]; then
    set -- "$1" "$3" "$2" "${@:4}"
fi

newTime=$(add_time "$time" $(format_time "$2" "-"))
output="${pre}${channel}_${date}_${newTime}.mp4"

ffmpeg -y -ss "$2" -to "$3" -i "$1" -c copy -avoid_negative_ts make_zero "${output}"

thumbnail "${output}"

filter_log "$1" "$output" $(format_time "$2" ":") $(format_time "$3" ":")

##############################################################################################

info "clipper finished"

echo "${output#/home/twitchrecorder}"


##############################################################################################
