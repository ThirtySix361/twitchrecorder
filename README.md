<div align="center">

[![version](https://img.shields.io/endpoint?url=https://raw.githubusercontent.com/ThirtySix361/twitchrecorder/master/src/version.json?&style=for-the-badge&logo=wikidata)](https://github.com/ThirtySix361/twitchrecorder)
[![commit](https://img.shields.io/github/last-commit/ThirtySix361/twitchrecorder?&style=for-the-badge&logo=github&label=github+last+commit)](https://github.com/ThirtySix361/twitchrecorder)
[![Docker Image CI/CD](https://img.shields.io/github/actions/workflow/status/ThirtySix361/twitchrecorder/docker.yml?style=for-the-badge&logo=github&label=Docker%20Pipeline)](https://github.com/ThirtySix361/twitchrecorder/actions/workflows/docker.yml) <br>
[![stars](https://img.shields.io/github/stars/thirtysix361/twitchrecorder.svg?style=for-the-badge&logo=github&label=github+stars)](https://github.com/ThirtySix361/twitchrecorder/stargazers)
[![pulls](https://img.shields.io/docker/pulls/thirtysix361/twitchrecorder.svg?style=for-the-badge&logo=docker)](https://hub.docker.com/r/thirtysix361/twitchrecorder)
[![stars](https://img.shields.io/docker/stars/thirtysix361/twitchrecorder.svg?style=for-the-badge&logo=docker)](https://hub.docker.com/r/thirtysix361/twitchrecorder) <br>
[![mail](https://img.shields.io/badge/contact-dev%4036ip.de-blue?style=for-the-badge&&logo=maildotru)](mailto:dev@36ip.de)
[![discord](https://img.shields.io/badge/discord-.thirtysix-5865F2?style=for-the-badge&logo=discord)](https://discord.com/users/323043165021929482)

</div>

# 🎥 twitchrecorder

<div align="center">

this container lets you download any twitch stream by giving a simple twitch channel name as parameter.

[![features](https://raw.githubusercontent.com/ThirtySix361/twitchrecorder/master/doc/features.png)](https://github.com/ThirtySix361/twitchrecorder/)

</div>

## 🌐 links

[source code](https://github.com/ThirtySix361/twitchrecorder)

## 🔗 dependencies

docker

## 🚀 quick start

step 1.

```bash
git clone https://github.com/ThirtySix361/twitchrecorder.git
cd twitchrecorder
```

step 2.

```bash
docker pull thirtysix361/twitchrecorder
```

step 3.

```bash
bash run.sh
# default port is: 8081
# or for custom port use:
bash run.sh <port>
```

then acceess the webpage through your browser on `http://localhost:<port>`

## 🧠 general informations

<div align="center">

[![flow](https://raw.githubusercontent.com/ThirtySix361/twitchrecorder/master/doc/flow.png)](https://github.com/ThirtySix361/twitchrecorder/)

</div>

>//<br>//<samp> 💡 the `.mp4` files are stored in the `mounts/archive/<channelname>/` directory </samp><br>//

## 🧐 troubleshooting

in case a container was forcefully killed, just re-deploy the container.
it will autofix the files which did not graceful finished.

this can take a while on huge files, depending on your used hardware.

## 🌊 flowchart

```mermaid
graph TD

    start[start]
    checkraw[check for .raw files]
    islive{is streamer live?}
    finalthumb[create final thumbnail]
    deleteraw[delete .raw indicaton file]
    sleep[sleep for 60 seconds]
    defaultthumbnail[create default .raw thumbnail]
    createraw[create .raw indication file]
    startrecording[start recording task]
    chattask[start chat scraping task]
    streamend{stream end?}
    fixrecording[fix recording]

    start --> islive
    start --> checkraw

    islive -->|no| sleep
    islive -->|yes| createraw

    createraw --> defaultthumbnail
    defaultthumbnail --> chattask
    chattask --> startrecording
    startrecording --> streamend

    streamend -->|yes| fixrecording
    streamend -->|no| streamend

    checkraw --> fixrecording

    fixrecording --> finalthumb
    finalthumb --> deleteraw
    deleteraw --> sleep

    sleep --> islive

```

## 📝 todo list
- [x] container
    - [x] runs as user instead of root
    - [x] allowing multiple instances at same time for unlimited parallel recordings
    - [x] proper logging
- [x] recordings
    - [x] autodetect if streamer started streaming and start recording
    - [x] make file playable even if the streamer is still streaming and file is still being written
    - [x] prevent file from beeing corrupted after container shutdown while file is still being written
    - [x] autofix final .mp4 file on stream end
        - [x] autofix unfinished files on container startup
    - [x] take thumbnail from final .mp4 on stream end
    - [x] capture chat into textfile
        - [x] capture twitch emotes
    - [x] improve live playback
        - [x] capture stream with hls and .m3u8
    - [x] remove all advertisements by twitch from the stream
- [x] webserver
    - [x] add an optional webserver-container for the webpage
    - [x] remove the separate webserver-container and implement it directly into the recorder-container
- [x] webpage
    - [x] list every video file from archive (order by filename)
        - [x] filter videos by streamer
    - [x] display filename and filesize
    - [x] delete video button
        - [x] remove empty directorys
        - [x] remove last time position
    - [x] video navigation buttons
    - [x] save last time position to localstorage
    - [x] load last time position on open
    - [x] display chat next to the video
        - [x] sync chat with video
    - [x] release a demo version
    - [x] improve responsive design especially for mobile
    - [x] redesign webpage
    - [x] implement hls for live-playback
    - [x] add recording-task-management page to webpage
