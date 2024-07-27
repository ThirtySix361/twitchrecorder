FROM debian:latest
RUN apt update
RUN apt upgrade -y
RUN apt install -y nano streamlink ffmpeg
RUN mkdir -p /home/twitchrecorder/archive/
WORKDIR /home/twitchrecorder/
COPY entrypoint.sh /home/twitchrecorder/
ENTRYPOINT ["bash", "/home/twitchrecorder/entrypoint.sh"]
