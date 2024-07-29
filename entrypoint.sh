#!/bin/bash

info() { command echo $(date +"%Y-%m-%d %H:%M:%S") [INFO] "$0": "$@" >&2 ; }
error() { command echo $(date +"%Y-%m-%d %H:%M:%S") [ERROR] "$0": "$@" >&2; }

#######################################################################################

basedir=$(dirname "$(realpath $0)")
basefile=$(basename "$(realpath $0)")
basepath="${basedir}/${basefile}"

#######################################################################################

if [ -z "${channel}" ] && [ -z "${1}" ] ; then
    error "channel var not set"
    exit 1
fi

#######################################################################################

if [ -n "${channel}" ]; then
    CHANNEL_NAME="${channel}"
fi

if [ -n "${1}" ]; then
    CHANNEL_NAME="${1}"
fi

#######################################################################################

OUTPUT_DIR="/home/twitchrecorder/archive/${CHANNEL_NAME}"
mkdir -p "${OUTPUT_DIR}"

#######################################################################################

info "twitchrecorder by thirtysix (dev@36ip.de)"
info "twitchrecorder process started for channelname: ${CHANNEL_NAME}"

#######################################################################################

if [ -n "${startupfix}" ] || [ -n "${2}" ] ; then

    info "${CHANNEL_NAME} fixing files started"

    for file in "${basedir}"/archive/"${CHANNEL_NAME}"/*.mp4; do

        if [ -f "${file}" ]; then

            ffmpeg -i "${file}" -c copy -movflags +faststart "${file}_fixed.mp4" >> "${basedir}"/ffmpeg.log 2>&1
            rm -rf "${file}"
            mv "${file}_fixed.mp4" "${file}"
            info "${file} recording fixed"

        fi

    done

    info "${CHANNEL_NAME} fixing files finished"

fi

#######################################################################################

while true; do

    islive=$(streamlink --json twitch.tv/"${CHANNEL_NAME}" | grep -q "error" && echo 0 || echo 1)

    if [ "${islive}" == "1" ]; then

        info "${CHANNEL_NAME} recording started"

        TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
        OUTPUT_FILE="${OUTPUT_DIR}/${CHANNEL_NAME}_${TIMESTAMP}.mp4"

        streamlink -o - twitch.tv/"${CHANNEL_NAME}" 720p,720p60,best 2>> "${basedir}"/streamlink.log | ffmpeg -fflags +genpts -i pipe:0 -c:v copy -c:a aac -b:a 128k -f mp4 -movflags +frag_keyframe+empty_moov+faststart "${OUTPUT_FILE}" >> "${basedir}"/ffmpeg.log 2>&1

        info "${CHANNEL_NAME} recording finished"

        ffmpeg -i "${OUTPUT_FILE}" -c copy -movflags +faststart "${OUTPUT_FILE}_fixed.mp4" >> "${basedir}"/fix.log 2>&1
        rm -rf "${OUTPUT_FILE}"
        mv "${OUTPUT_FILE}_fixed.mp4" "${OUTPUT_FILE}"

        info "${OUTPUT_FILE} recording fixed"

        sleep 60

    else

        sleep 300

    fi

done

#######################################################################################

info "twitchrecorder process finished for channelname: ${CHANNEL_NAME}"
