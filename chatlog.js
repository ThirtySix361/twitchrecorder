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
    });

    await page.goto(url);
    await page.waitForSelector('.chat-scrollable-area__message-container');

    await page.evaluate(() => {
        var startTime = new Date();
        var targetNode = document.querySelector('.chat-scrollable-area__message-container');
        var config = { childList: true };

        function replaceMultipleSpaces(str) {
            return str.replace(/\s+/g, ' ');
        }

        function extractText(node) {
            let stack = [node];
            let messageText = '';

            while (stack.length) {
                let currentNode = stack.pop();

                if (currentNode.nodeType === Node.ELEMENT_NODE) {
                    if (currentNode.tagName === 'IMG') {
                        messageText += `<img src="${currentNode.src}" alt="${currentNode.alt || ''}">`;
                    } else if (currentNode.tagName === 'SPAN' && currentNode.classList.contains('chat-author__display-name')) {
                        messageText += `<span class="chat-author__display-name" style="${currentNode.getAttribute('style')}">${currentNode.innerHTML}</span>`;
                    } else {
                        for (let i = currentNode.childNodes.length - 1; i >= 0; i--) {
                            stack.push(currentNode.childNodes[i]);
                        }
                    }
                } else if (currentNode.nodeType === Node.TEXT_NODE) {
                    messageText += currentNode.nodeValue + " ";
                }
            }

            return replaceMultipleSpaces(messageText);
        }

        var callback = (mutationsList) => {
            for (let mutation of mutationsList) {
                if (mutation.addedNodes.length) {
                    mutation.addedNodes.forEach(node => {
                        messageText = extractText(node).trim();
                        var elapsedMilliseconds = Date.now() - startTime;
                        var elapsedDate = new Date(elapsedMilliseconds);
                        var formattedTime = elapsedDate.toISOString().substr(11, 8);
                        var message = `${formattedTime} ${messageText}`;
                        window.logChatMessage(message);
                    });
                }
            }
        };

        var observer = new MutationObserver(callback);
        observer.observe(targetNode, config);
    });

    await new Promise(() => {});

})();
