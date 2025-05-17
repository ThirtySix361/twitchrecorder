FROM node:latest

RUN apt update
RUN apt upgrade -y
RUN apt install -y nano ffmpeg libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libxcomposite1 libxdamage1 libxkbcommon0 libxfixes3 libxrandr2 libgbm1 libasound2

RUN useradd -m twitchrecorder
WORKDIR /home/twitchrecorder/
USER twitchrecorder

RUN mkdir archive
RUN npm install puppeteer puppeteer-extra puppeteer-extra-plugin-stealth user-agents

COPY --chown=twitchrecorder:twitchrecorder container/ /home/twitchrecorder/

ENTRYPOINT ["bash", "/home/twitchrecorder/entrypoint.sh"]
