#!/bin/bash

info() { command echo $(date +"%Y-%m-%d %H:%M:%S") [INFO] "$0": "$@" >&2 ; }
error() { command echo $(date +"%Y-%m-%d %H:%M:%S") [ERROR] "$0": "$@" >&2; }

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

    echo "started $(date)" > "${LOG}"

    duration=$(ffmpeg -i "${INPUT}" 2>&1 | grep "Duration" | cut -d ' ' -f 4 | sed s/,//)
    halfduration=$(halving "${duration}")

    ffmpeg -y -ss $halfduration -i "${INPUT}" -vframes 1 -q:v 2 -vf "scale=iw*0.5:ih*0.5" "${THUMBNAIL}" 2>&1 | awk 'NF {print strftime("%Y-%m-%d %H:%M:%S"), $0; fflush()}' >> "${LOG}"

    info "${THUMBNAIL} thumbnail created"
}

fix() {
    local input="$1"
    local basename="${input%.*}"
    local INPUT="${basename}.m3u8"
    local OUTPUT="${basename}.mp4"
    local INIT="${basename}.init"
    local TEMP="${basename}.processing"
    local RAW="${basename}.raw"
    local CHAT="${basename}.log"
    local THUMBNAIL="${basename}.png"
    local LOG="/tmp/${CHANNEL_NAME}-fix.log"

    echo "started $(date)" > "${LOG}"

    info "${INPUT} fixing started"

    if [ -f "${INPUT}" ] && [ -s "${INPUT}" ]; then
        if [[ $(tail -n 1 "${INPUT}") != "#EXT-X-ENDLIST" ]]; then echo "#EXT-X-ENDLIST" >> "${INPUT}"; fi
        rm -rf "${TEMP}"
    else
        error "${INPUT} not found or empty"
        rm -rf "${INPUT}" "${INIT}" "${basename}"*.m4s "${RAW}" "${OUTPUT}" "${CHAT}" "${THUMBNAIL}"
        error "${INPUT} and related messed up files removed"
        error "${INPUT} fixing failed"
        echo "${INPUT} fixing failed" >> "${LOG}"
        return
    fi

    ffmpeg -y -allowed_extensions ALL -fflags +genpts -copyts -start_at_zero -i "${INPUT}" -c copy -movflags +faststart -f mp4 "${TEMP}" 2>&1 | awk 'NF {print strftime("%Y-%m-%d %H:%M:%S"), $0; fflush()}' >> "${LOG}"
    local ffmpeg_exit=$?

    if [ -f "${TEMP}" ] && [ $ffmpeg_exit -eq 0 ]; then
        mv "${TEMP}" "${OUTPUT}"
        rm -rf "${INPUT}" "${INIT}" "${basename}"*.m4s "${RAW}"
        info "${INPUT} fixing finished"
        thumbnail "${OUTPUT}"
    else
        error "${INPUT} fixing failed"
        echo "${INPUT} fixing failed" >> "${LOG}"
    fi
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
        (sleep 5 && fix "$file") &
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
            PREVIEW="${OUTPUT_DIR}/${CHANNEL_NAME}_${TIMESTAMP}.png"
            INIT="${CHANNEL_NAME}_${TIMESTAMP}.init"
            RAW="${OUTPUT_DIR}/${CHANNEL_NAME}_${TIMESTAMP}.raw"
            LOG="/tmp/${CHANNEL_NAME}-ffmpeg.log"
            STREAMS="/tmp/${CHANNEL_NAME}-streams.log"

            echo "started $(date)" >> "${LOG}" 2>&1
            echo "started $(date)" >> "${STREAMS}" 2>&1

            info "${OUTPUT} recording started"

            touch "${RAW}"

            cp "${basedir}"/raw36.png "${PREVIEW}"

            STREAM_URL=$(node "${basedir}"/getStreamURL.js "${CHANNEL_NAME}" "720,1080,best")

            if [ $? -ne 0 ]; then
                error $STREAM_URL
                continue
            fi

            node "${basedir}/chatlog.js" "${CHANNEL_NAME}" "${CHAT}" &
            NODE_PID=$!

            sleep 10

            timeout 1439m \
                ffmpeg -fflags +genpts \
                    -i "${STREAM_URL}" \
                    -protocol_opts http_persistent=1,reconnect=3 \
                    -loglevel warning -xerror \
                    -avoid_negative_ts make_zero \
                    -start_at_zero \
                    -c:v copy -c:a aac -b:a 160k \
                    -f hls \
                    -hls_time 60 \
                    -hls_list_size 0 \
                    -hls_flags append_list+delete_segments \
                    -hls_fmp4_init_filename "${INIT}" \
                    -hls_segment_type fmp4 \
                    "${OUTPUT}" 2>&1 | awk 'NF {print strftime("%Y-%m-%d %H:%M:%S"), $0; fflush()}' >> "${LOG}"

            kill $NODE_PID

            info "${OUTPUT} recording finished"

            fix "${RAW}" &

        fi

        sleep 60

    done

fi

#######################################################################################

info "twitchrecorder process finished for channelname: ${CHANNEL_NAME}"
