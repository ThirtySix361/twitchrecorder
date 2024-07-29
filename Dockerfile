FROM debian:latest

RUN apt update
RUN apt upgrade -y
RUN apt install -y nano streamlink ffmpeg

RUN useradd -m -d /home/twitchrecorder twitchrecorder

WORKDIR /home/twitchrecorder/

COPY entrypoint.sh /home/twitchrecorder/

RUN mkdir archive

USER twitchrecorder

ENTRYPOINT ["bash", "/home/twitchrecorder/entrypoint.sh"]
