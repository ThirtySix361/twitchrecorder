
// ------------------------------------------------------------------------------- //

const path = require('path');

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

// ------------------------------------------------------------------------------- //

async function killThisTask(msg="", delay=0) {
    if (timer) { clearTimeout(timer); }
    if ( !timer || !taskHasAlreadyBeenKilled ) {
        timer = setTimeout(async function() {
            taskHasAlreadyBeenKilled = true;
            if (msg) { console.log(msg) }
            if (browser) { try { await browser.close() } catch (e) {} }
        }, delay);
    }
}

(async () => {

    browser = await puppeteer.launch({
        headless: "new",
        args: [...(debug ? ["--remote-debugging-port=9224"] : []), '--start-maximized', '--ignore-certificate-errors', '--no-sandbox', '--disable-web-security', '--disable-setuid-sandbox', '--user-agent=' + userAgentString],
        ignoreDefaultArgs: ['--disable-extensions'],
        userDataDir: undefined
    });

    [page] = await browser.pages();

    try {

        var responsePromise = page.waitForResponse(function(res) { return res.url().includes(".m3u8?acmb="); });

        if (!debug) { killThisTask("error, timeout while waiting for m3u8 url.", 10000) }

        await page.goto("https://twitch.tv/" + channel);

        var response = await responsePromise;

        var body = await response.text();
        var lines = body.split('\n');
        var targetLines = lines.filter(function(line) {
            return line.includes('VIDEO="');
        });
        var urls = [];

        targetLines.forEach(function(targetLine) {
            var targetLineIndex = lines.indexOf(targetLine);
            if (targetLineIndex !== -1 && targetLineIndex + 1 < lines.length) {
                urls.push(lines[targetLineIndex + 1]);
            }
        });

        if (debug) {
            console.log(lines);
        } else {
            killThisTask(urls[1]);
        }

    } catch (e) {
        //console.error("error:", e);
    }

    await browser.close();

})();