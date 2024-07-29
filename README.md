# 🎥 twitchrecorder

[![version](https://img.shields.io/badge/version-1.0.2-deepgreen)](https://github.com/ThirtySix361/twitchrecorder)
[![commit](https://img.shields.io/github/last-commit/ThirtySix361/twitchrecorder?logo=github&label=github+last+commit)](https://github.com/ThirtySix361/twitchrecorder)
[![stars](https://img.shields.io/github/stars/thirtysix361/twitchrecorder.svg?logo=github&style=flat&label=github+stars)](https://github.com/ThirtySix361/twitchrecorder)
[![pulls](https://img.shields.io/docker/pulls/thirtysix361/twitchrecorder.svg?logo=docker)](https://hub.docker.com/r/thirtysix361/twitchrecorder)
[![stars](https://img.shields.io/docker/stars/thirtysix361/twitchrecorder.svg?logo=docker)](https://hub.docker.com/r/thirtysix361/twitchrecorder)

[![mail](https://img.shields.io/badge/contact-dev%4036ip.de-blue?logo=maildotru)](mailto:dev@36ip.de)
![discord](https://img.shields.io/badge/discord-.thirtysix-5865F2?style=flat&logo=discord)

---

this container lets you download any twitch stream by giving a simple twitch channel name as parameter.

[![preview](https://raw.githubusercontent.com/ThirtySix361/twitchrecorder/master/preview.png)](https://raw.githubusercontent.com/ThirtySix361/twitchrecorder/master/preview.png)

---

## 🔗 dependencies

+ docker

---

## 🚀 quick start

step 1.

```bash
git clone https://github.com/ThirtySix361/twitchrecorder.git
cd twitchrecorder
```

step 2.

```bash
bash build.sh
```

step 3.

```bash
bash run.sh <twitchchannelname>
```

perform `step 3` for each streamer you want to record.

for example:

```bash
bash run.sh shroud
bash run.sh ninja
bash run.sh pewdiepie
bash run.sh montanablack88
```

>//<br>//<samp> 💡 optionally point your webserver with php interpreter to the `mounts/` directory </samp><br>//

---

## 🧠 general informations

this container will scan every 5 minutes if the given streamer has started streaming. \
if so, it will start recording the stream.

if the streamer stopped streaming, the container will perform another check after 60 seconds, in case the streamer just had a disconnect and restarts the stream, you will not miss full 5 minutes of the stream.

if container breaks, shutdown, is killed or has no more internet connection, the captured file will not be corrupted. you will still be able to play it!

on common stream ending, the container will perform a fix on the final `.mp4` file, which will move the header information (which includes also the final length of file) to the begin. this has to do with the way the `.mp4` is written while the stream is running.

all header information are stored at the begin of the file which is uncommon. but only by this way it is possible to have a working file after crash or forceful shutdown of the container and its inner capturing software.

>//<br>//<samp> 💡 the `.mp4` files are stored in the `mounts/archive/<channelname>/` directory </samp><br>//

---

## 🧐 troubleshooting

in case a container was forcefully killed and you want to fix the files, use `fix` as second parameter:

for example:

```bash
bash run.sh shroud fix
bash run.sh ninja fix
bash run.sh pewdiepie fix
bash run.sh montanablack88 fix
```

or set this environment variable in the container:

```bash
startupfix="fix"
```

this will fix all files for a streamer on startup, which can take a while.

---

## 📝 todo list

- [x] container
    - [x] runs as user instead of root
    - [x] allowing multiple instances at same time for unlimited parallel recordings
    - [x] proper logging
- [ ] recordings
    - [x] autodetect if streamer started streaming and start recording
    - [x] make file playable even if the streamer is still streaming and file is still being written
    - [x] prevent file from beeing corrupted after container shutdown while file is still being written
    - [x] fix header information of final .mp4 file on stream end
    - [x] optional re-fix files on container startup
        - [ ] fix only files which are not already fine
    - [ ] capture chat into textfile
    - [ ] improve live playback
- [ ] webpage
    - [x] list every video file from archive (order by filename)
    - [x] display filename and filesize
    - [x] delete video button
    - [x] video navigation buttons
    - [x] save last time position to localstorage
    - [x] load last time position on open
    - [x] remove last time position on delete
    - [ ] display chat next to the video
        - [ ] sync chat with video
