#!/bin/bash

info() { command echo $(date +"%Y-%m-%d %H:%M:%S") [INFO] "$0": "$@" >&2; }
error() { command echo $(date +"%Y-%m-%d %H:%M:%S") [ERROR] "$0": "$@" >&2; }
log_info() { command echo $(date +"%Y-%m-%d %H:%M:%S") [INFO] "$0": "$@"; }
log_error() { command echo $(date +"%Y-%m-%d %H:%M:%S") [ERROR] "$0": "$@"; }

#######################################################################################

basedir=$(dirname "$(realpath $0)")
basefile=$(basename "$(realpath $0)")
basepath="${basedir}/${basefile}"

#######################################################################################

if [ -z "${1}" ] ; then
    error "channel var not set"
    exit 1
fi

CHANNEL_NAME="${1}"

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
    local INPUT="${basename}.mp4"
    local THUMBNAIL="${basename}.png"
    local LOG="/tmp/${CHANNEL_NAME}-thumbnail.log"

    log_info "${THUMBNAIL} started" > "${LOG}"

    duration=$(ffmpeg -i "${INPUT}" 2>&1 | grep "Duration" | cut -d ' ' -f 4 | sed s/,//)
    halfduration=$(halving "${duration}")

    ffmpeg -y -ss $halfduration -i "${INPUT}" -vframes 1 -q:v 2 -vf "scale=iw*0.5:ih*0.5" "${THUMBNAIL}" 2>&1 | awk 'NF {print strftime("%Y-%m-%d %H:%M:%S"), $0; fflush()}' >> "${LOG}"

    info "${THUMBNAIL} thumbnail created"
    log_info "${THUMBNAIL} finished" >> "${LOG}"
}

fix() {
    local input="$1"
    local basename="${input%.*}"
    local INPUT="${basename}.m3u8"
    local M4S="${basename}.m4s"
    local OUTPUT="${basename}.mp4"
    local TEMP="${basename}.processing"
    local RAW="${basename}.raw"
    local CHAT="${basename}.log"
    local THUMBNAIL="${basename}.png"
    local LOG="/tmp/${CHANNEL_NAME}-fix.log"
    local LOCK="/tmp/fix.lock"

    {
        if ! flock -n 36; then
            info "${INPUT} waiting for other fixing task to finish.."
            log_info "${INPUT} waiting for other fixing task to finish.." >> "${LOG}"
        fi

        flock -x 36

        info "${INPUT} fixing started"
        log_info "${INPUT} fixing started" > "${LOG}"

        if [ -f "${INPUT}" ] && [ -s "${INPUT}" ]; then
            if [[ $(tail -n 1 "${INPUT}") != "#EXT-X-ENDLIST" ]]; then echo "#EXT-X-ENDLIST" >> "${INPUT}"; fi
            rm -rf "${TEMP}"
        else
            error "${INPUT} fixing failed: m3u8 not found or empty (related files removed)"
            log_error "${INPUT} fixing failed" >> "${LOG}"
            rm -rf "${INPUT}" "${M4S}" "${RAW}" "${OUTPUT}" "${CHAT}" "${THUMBNAIL}"
            return
        fi

        ffmpeg -y -i "${INPUT}" -c copy -movflags +faststart -f mp4 "${TEMP}" 2>&1 | awk 'NF {print strftime("%Y-%m-%d %H:%M:%S"), $0; fflush()}' >> "${LOG}"
        local ffmpeg_exit=$?

        if [ -f "${TEMP}" ] && [ $ffmpeg_exit -eq 0 ]; then
            mv "${TEMP}" "${OUTPUT}"
            rm -rf "${INPUT}" "${M4S}" "${RAW}"
            info "${INPUT} fixing finished"
            log_info "${INPUT} fixing finished" >> "${LOG}"
            thumbnail "${OUTPUT}"
        else
            error "${INPUT} fixing failed"
            log_error "${INPUT} fixing failed" >> "${LOG}"
        fi

    } 36>"${LOCK}"
}

is_twitch_live() {
    local channel="$1"
    local resp

    resp=$(curl -s -X POST https://gql.twitch.tv/gql \
    -H 'Client-ID: kimne78kx3ncx6brgo4mv6wki5h1ko' \
    -H 'Content-Type: application/json' \
    -d '{
        "operationName": "ChannelShell",
        "variables": {
        "login": "'"$channel"'"
        },
        "query": "query ChannelShell($login: String!) { user(login: $login) { stream { type } } }"
    }')

    echo "$resp" | grep -q '"type":"live"' && echo "1" || echo "0"
}

#######################################################################################

for file in "${basedir}"/archive/"${CHANNEL_NAME}"/*.raw; do
    if [ -f "$file" ]; then
        (sleep 1 && fix "$file") &
    fi
done

#######################################################################################

if [ -z "${2}" ]; then

    while true; do

        islive=$(is_twitch_live "${CHANNEL_NAME}")

        if [ "${islive}" == "1" ]; then

            mkdir -p "${OUTPUT_DIR}"

            TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
            OUTPUT="${OUTPUT_DIR}/${CHANNEL_NAME}_${TIMESTAMP}.m3u8"
            CHAT="${OUTPUT_DIR}/${CHANNEL_NAME}_${TIMESTAMP}.log"
            RAW="${OUTPUT_DIR}/${CHANNEL_NAME}_${TIMESTAMP}.raw"
            LOG="/tmp/${CHANNEL_NAME}-ffmpeg.log"
            STREAMS="/tmp/${CHANNEL_NAME}-streams.log"

            info "${OUTPUT} recording started"
            log_info "${OUTPUT} recording started" >> "${LOG}"
            log_info "${OUTPUT} recording started" >> "${STREAMS}"

            touch "${RAW}"

            STREAM_URL=$(node "${basedir}"/getStreamURL.js "${CHANNEL_NAME}" "720,1080,best") # example "720,1080,best" or 0 (best), 1 (second best), 2 (third best) - also mixable like: "720,480,1080,5,4,3,2,1,0,best"

            if [ $? -ne 0 ]; then
                error $STREAM_URL
                continue
            fi

            node "${basedir}/chatlog.js" "${CHANNEL_NAME}" "${CHAT}" &
            NODE_PID=$!

            sleep 10

            timeout --foreground 1439m \
                ffmpeg -y \
                    -fflags +genpts \
                    -reconnect 1 -reconnect_at_eof 1 -reconnect_streamed 1 -reconnect_delay_max 5 \
                    -rw_timeout 30000000 -timeout 30000000 \
                    -i "${STREAM_URL}" \
                    -loglevel error \
                    -avoid_negative_ts make_zero \
                    -start_at_zero \
                    -c:v copy -c:a aac -b:a 160k \
                    -f hls \
                    -hls_time 5 \
                    -hls_list_size 0 \
                    -hls_flags single_file+append_list \
                    -hls_segment_type fmp4 \
                    "${OUTPUT}" 2>&1 | awk 'NF {print strftime("%Y-%m-%d %H:%M:%S"), $0; fflush()}' >> "${LOG}"

            kill $NODE_PID

            info "${OUTPUT} recording finished"
            log_info "${OUTPUT} recording finished" >> "${LOG}"
            log_info "${OUTPUT} recording finished" >> "${STREAMS}"

            fix "${RAW}" &

        fi

        sleep 60

    done

fi

#######################################################################################

info "twitchrecorder process finished for channelname: ${CHANNEL_NAME}"
