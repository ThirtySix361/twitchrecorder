
// ------------------------------------------------------------------------------- //

const path = require('path');
const fs = require('fs');

const puppeteer = require('puppeteer-extra');
const StealthPlugin = require('puppeteer-extra-plugin-stealth');
puppeteer.use(StealthPlugin());

const userAgent = require('user-agents');
const userAgentString = userAgent.random().toString();

// ------------------------------------------------------------------------------- //

var browser;
var timer;
var taskHasAlreadyBeenKilled = false;

var channel = process.argv[2];
var debug = process.argv[3];

const logFile = '/tmp/' + channel + '-streams.log';

// ------------------------------------------------------------------------------- //

async function killThisTask(msg = "", delay = 0) {
    if (timer) { clearTimeout(timer); }
    if (!timer || !taskHasAlreadyBeenKilled) {
        timer = setTimeout(async function () {
            taskHasAlreadyBeenKilled = true;
            if (msg) { console.log(msg) }
            if (browser) { try { await browser.close() } catch (e) { } }
        }, delay);
    }
}

(async () => {

    browser = await puppeteer.launch({
        headless: "new",
        args: [...(debug ? ["--remote-debugging-port=9224"] : []), '--start-maximized', '--ignore-certificate-errors', '--no-sandbox', '--disable-web-security', '--disable-setuid-sandbox', '--user-agent=' + userAgentString],
        userDataDir: undefined
    });

    [page] = await browser.pages();

    try {

        var responsePromise = page.waitForResponse(function (res) { return res.url().includes(".m3u8?acmb="); });

        if (!debug) { killThisTask("error, timeout while waiting for m3u8 url.", 10000) }

        await page.goto("https://twitch.tv/" + channel);

        var response = await responsePromise;
        var body = await response.text();
        var lines = body.split('\n');

        const qualityEntries = [];

        for (let i = 0; i < lines.length - 1; i++) {
            const line = lines[i];
            if (line.startsWith('#EXT-X-STREAM-INF:')) {
                const info = line;
                const url = lines[i + 1];
                if (url && !url.startsWith('#')) {
                    const videoMatch = info.match(/VIDEO="([^"]+)"/);
                    const video = videoMatch ? videoMatch[1] : "";
                    qualityEntries.push({ video, info, url });
                }
            }
        }

        qualityEntries.sort((a, b) => b.video.localeCompare(a.video));

        const sortedUrls = qualityEntries.map(entry => entry.url);

        if (debug) {
            console.log(qualityEntries);
        } else {
            fs.appendFileSync(logFile, JSON.stringify(qualityEntries, null, 2) + '\n');
            killThisTask(sortedUrls[1]);
        }

    } catch (e) {
        //console.error("error:", e);
    }

    await browser.close();

})();