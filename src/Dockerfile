FROM debian:latest

RUN apt update
RUN apt upgrade -y
RUN apt install -y nano curl apache2 libapache2-mod-php nodejs npm ffmpeg libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libxcomposite1 libxdamage1 libxkbcommon0 libxfixes3 libxrandr2 libgbm1 libasound2

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf && \
    echo "User twitchrecorder" >> /etc/apache2/apache2.conf && \
    echo "Group twitchrecorder" >> /etc/apache2/apache2.conf
RUN rm -rf /var/www/html && ln -s /home/twitchrecorder /var/www/html

RUN useradd -m -s /bin/bash twitchrecorder
WORKDIR /home/twitchrecorder/
USER twitchrecorder

RUN mkdir archive
RUN npm install puppeteer puppeteer-extra puppeteer-extra-plugin-stealth user-agents

COPY --chown=twitchrecorder:twitchrecorder container/ /home/twitchrecorder/
COPY --chown=twitchrecorder:twitchrecorder webpage/ /home/twitchrecorder/

RUN echo "window.build = '$(date +%y%m%d)';" | cat - js/index.js > temp && mv temp js/index.js

USER 0

# HEALTHCHECK CMD curl -f http://localhost || exit 1

CMD ["bash", "/home/twitchrecorder/entrypoint.sh"]
