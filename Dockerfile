FROM debian:latest

RUN apt update
RUN apt upgrade -y
RUN apt install -y nano streamlink ffmpeg nodejs npm libnss3

RUN useradd -m -d /home/twitchrecorder twitchrecorder
WORKDIR /home/twitchrecorder/
USER twitchrecorder

RUN mkdir archive
RUN npm install puppeteer
RUN npx puppeteer browsers install chrome

COPY entrypoint.sh /home/twitchrecorder/
COPY chatlog.js /home/twitchrecorder/

ENTRYPOINT ["bash", "/home/twitchrecorder/entrypoint.sh"]
