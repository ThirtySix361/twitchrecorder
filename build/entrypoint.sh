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

#######################################################################################

info "twitchrecorder by thirtysix (dev@36ip.de)"
info "twitchrecorder process started for channelname: ${CHANNEL_NAME}"

#######################################################################################

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
    local OUTPUT_FILE="${basename}.mp4"
    local PREVIEW_IMAGE="${basename}.png"

    duration=$(ffmpeg -i "${OUTPUT_FILE}" 2>&1 | grep "Duration" | cut -d ' ' -f 4 | sed s/,//)
    halfduration=$(halving "${duration}")
    ffmpeg -y -ss $halfduration -i "${OUTPUT_FILE}" -vframes 1 -q:v 2 "${PREVIEW_IMAGE}" >> "${basedir}"/thumbnail.log 2>&1

    info "${PREVIEW_IMAGE} thumbnail created"
}

fix() {
    local input="$1"
    local basename="${input%.*}"
    local INPUT_FILE="${basename}.m3u8"
    local INIT_FILE="${basename}.init"
    local OUTPUT_FILE="${basename}.mp4"
    local TEMP_FILE="${basename}.processing"
    local RAW="${basename}.raw"

    info "${OUTPUT_FILE} fixing started"

    rm -rf "${TEMP_FILE}"

    ffmpeg -allowed_extensions ALL -fflags +genpts -copyts -start_at_zero -i "${INPUT_FILE}" -c copy -bsf:a aac_adtstoasc -movflags +faststart -vsync 2 -async 1 -f mp4 "${TEMP_FILE}" >> "${basedir}"/fix.log 2>&1

    mv "${TEMP_FILE}" "${OUTPUT_FILE}"

    rm -rf "${INPUT_FILE}"
    rm -rf "${INIT_FILE}"
    rm -rf "${basename}"*.m4s

    info "${OUTPUT_FILE} fixing finished"

    thumbnail "${OUTPUT_FILE}"

    rm "${RAW}"
}

fix_crashed() {
    local input="$1"
    local basename="${input%.*}"
    local INPUT_FILE="${basename}.m3u8"
    local INIT_FILE="${basename}.init"
    local OUTPUT_FILE="${basename}.mp4"
    local TEMP_FILE="${basename}.processing"
    local RAW="${basename}.raw"
    local TEMP_LIST_FILE="${basename}.list.txt"
    local NEW_INPUT_FILE="${basename}.new.m3u8"

    info "${OUTPUT_FILE} fixing started"

    rm -rf "${TEMP_FILE}"
    rm -rf "${NEW_INPUT_FILE}"

    ls -v "${basename}"*.m4s > "${TEMP_LIST_FILE}"

    {
        echo "#EXTM3U"
        echo "#EXT-X-VERSION:7"
        echo "#EXT-X-TARGETDURATION:10"
        echo "#EXT-X-MAP:URI=\"${INIT_FILE}\""
        while read f; do
            echo "#EXTINF:10.0,"
            echo "$f"
        done < "${TEMP_LIST_FILE}"
        echo "#EXT-X-ENDLIST"
    } > "${NEW_INPUT_FILE}"

    ffmpeg -allowed_extensions ALL -fflags +genpts -copyts -start_at_zero -i "${NEW_INPUT_FILE}" -c copy -bsf:a aac_adtstoasc -movflags +faststart -vsync 2 -async 1 -f mp4 "${TEMP_FILE}" >> "${basedir}"/fix.log 2>&1

    mv "${TEMP_FILE}" "${OUTPUT_FILE}"

    rm -rf "${INPUT_FILE}"
    rm -rf "${INIT_FILE}"
    rm -rf "${basename}"*.m4s
    rm -rf "${TEMP_LIST_FILE}"
    rm -rf "${NEW_INPUT_FILE}"

    info "${OUTPUT_FILE} fixing finished"

    thumbnail "${OUTPUT_FILE}"

    rm "${RAW}"
}

#######################################################################################

for file in "${basedir}"/archive/"${CHANNEL_NAME}"/*.raw; do
    if [ -f "$file" ]; then
        fix_crashed "$file" &
    fi
done

#######################################################################################

while true; do

    islive=$(streamlink --json twitch.tv/"${CHANNEL_NAME}" | grep -q "error" && echo 0 || echo 1)

    if [ "${islive}" == "1" ]; then

        mkdir -p "${OUTPUT_DIR}"

        TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
        OUTPUT_FILE="${OUTPUT_DIR}/${CHANNEL_NAME}_${TIMESTAMP}.m3u8"
        CHAT_FILE="${OUTPUT_DIR}/${CHANNEL_NAME}_${TIMESTAMP}.log"
        PREVIEW_IMAGE="${OUTPUT_DIR}/${CHANNEL_NAME}_${TIMESTAMP}.png"
        INIT_FILE="${CHANNEL_NAME}_${TIMESTAMP}.init"
        RAW="${OUTPUT_DIR}/${CHANNEL_NAME}_${TIMESTAMP}.raw"

        info "${OUTPUT_FILE} recording started"

        touch "${RAW}"
        cp "${basedir}"/raw36.png "${PREVIEW_IMAGE}"

        node "${basedir}"/chatlog.js "${CHANNEL_NAME}" "${CHAT_FILE}" &
        NODE_PID=$!

        streamlink -o - twitch.tv/"${CHANNEL_NAME}" 720p,720p60,best 2>> "${basedir}"/streamlink.log | \
            ffmpeg -fflags +genpts -i pipe:0 \
                -c copy -bsf:a aac_adtstoasc \
                -vsync 2 -async 1 \
                -f hls \
                -start_at_zero \
                -hls_time 60 \
                -hls_list_size 0 \
                -hls_flags append_list+delete_segments \
                -hls_fmp4_init_filename "${INIT_FILE}" \
                -hls_segment_type fmp4 \
                "${OUTPUT_FILE}" >> "${basedir}"/ffmpeg.log 2>&1

        kill $NODE_PID

        info "${OUTPUT_FILE} recording finished"

        fix "${RAW}" &

    fi

    sleep 60

done

#######################################################################################

info "twitchrecorder process finished for channelname: ${CHANNEL_NAME}"
