const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

const channelName = process.argv[2];
const logFilePath = process.argv[3];

if (!channelName || !logFilePath) {
    console.error('channel or logfilepath not set');
    process.exit(1);
}

const url = `https://www.twitch.tv/popout/${channelName}/chat`;
const resolvedPath = path.resolve(logFilePath);
const logStream = fs.createWriteStream(resolvedPath, { flags: 'a' });

(async () => {
    const browser = await puppeteer.launch({ headless: true, args: ['--no-sandbox', '--disable-setuid-sandbox'] });
    const page = await browser.newPage();

    await page.exposeFunction('logChatMessage', (message) => {
        logStream.write(`${message}\n`);
        //console.log(`${message}\n`);
    });

    await page.goto(url);
    await page.waitForSelector('.chat-scrollable-area__message-container');

    await page.evaluate(() => {
        const startTime = new Date();
        const targetNode = document.querySelector('.chat-scrollable-area__message-container');
        const config = { childList: true };

        const callback = (mutationsList) => {
            for (let mutation of mutationsList) {
                if (mutation.addedNodes.length) {
                    mutation.addedNodes.forEach(node => {
                        if (node.classList && node.classList.contains('chat-line__message')) {

                            const messageText = node.innerText.trim();
                            const currentTime = new Date();
                            const elapsedMilliseconds = currentTime - startTime;
                            const elapsedSeconds = Math.floor(elapsedMilliseconds / 1000);
                            const elapsedMinutes = Math.floor(elapsedSeconds / 60);
                            const elapsedHours = Math.floor(elapsedMinutes / 60);

                            const formattedTime = [
                                String(elapsedHours).padStart(2, '0'),
                                String(elapsedMinutes % 60).padStart(2, '0'),
                                String(elapsedSeconds % 60).padStart(2, '0')
                            ].join(':');

                            const message = `${formattedTime} ${messageText}`;
                            window.logChatMessage(message);

                        }
                    });
                }
            }
        };

        const observer = new MutationObserver(callback);
        observer.observe(targetNode, config);
    });

    await new Promise(() => {});

})();
