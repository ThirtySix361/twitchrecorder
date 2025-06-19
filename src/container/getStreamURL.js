
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
var priority = process.argv[3]; // example "720,1080,best" or 0 (best), 1 (second best), 2 (third best) - also mixable like: "720,480,1080,5,4,3,2,1,0,best"
var debug = process.argv[4];

if (!priority) { priority = "best" }

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

function pickUrlsByPriority(qualityEntries, priorityList) {
    for (const q of priorityList) {
        if (q === 'best') return qualityEntries[0];
        if (q === 'worst') return qualityEntries[qualityEntries.length - 1];

        const idx = parseInt(q);
        if (!isNaN(idx) && idx < qualityEntries.length && qualityEntries[idx]) { return qualityEntries[idx]; }

        const match = qualityEntries.find(entry => {
            const resMatch = entry.info.match(/RESOLUTION=([^,]+)/);
            const resParts = resMatch[1].split('x');
            return resParts.some(dim => dim === q);
        });
        if (match) return match;
    }
    return qualityEntries[0];
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
                    const videoMatch = info.match(/BANDWIDTH=([^,]+)/);
                    const quality = videoMatch ? videoMatch[1] : "";
                    qualityEntries.push({ quality, info, url });
                }
            }
        }

        qualityEntries.sort((a, b) => parseInt(b.quality) - parseInt(a.quality));

        const priorities = priority.split(',');
        const selected = pickUrlsByPriority(qualityEntries, priorities);

        if (debug) {
            console.log(qualityEntries);
            console.log(priorities)
            console.log(selected);
        } else {
            fs.appendFileSync(logFile, JSON.stringify(qualityEntries, null, 2) + '\n' + JSON.stringify(priorities, null, 0) + '\n' + JSON.stringify(selected.info, null, 0) + '\n');
            fs.appendFileSync(logFile, '-------------------------------------------------');
            killThisTask(selected.url);
        }

    } catch (e) {
        //console.error("error:", e);
    }

    await browser.close();

})();