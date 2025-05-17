
// ------------------------------------------------------------------------------- //

const puppeteer = require('puppeteer-extra')
const StealthPlugin = require('puppeteer-extra-plugin-stealth')
puppeteer.use(StealthPlugin())

const userAgent = require('user-agents');
const userAgentString = userAgent.random().toString();

// ------------------------------------------------------------------------------- //

var channel = process.argv[2];
var debug = process.argv[3];

// ------------------------------------------------------------------------------- //

async function killThisTask(msg=false, code=0) {
    console.log(msg);
    process.exit(code);
}

(async () => {

    const browser = await puppeteer.launch({
        headless: true,
        args: [...(debug ? ["--remote-debugging-port=9224"] : []), '--start-maximized', '--ignore-certificate-errors', '--no-sandbox', '--disable-web-security', '--disable-setuid-sandbox', '--user-agent=' + userAgentString],
        ignoreDefaultArgs: ['--disable-extensions'],
        userDataDir: undefined
    });

    [page] = await browser.pages();

    page.on('response', async res => {
        if ( res.url().includes(".m3u8?acmb=") ) {
            body = await res.text();
            lines = body.split('\n');
            targetLines = lines.filter(line => line.includes('VIDEO="'));
            urls = [];

            targetLines.forEach(targetLine => {
                let targetLineIndex = lines.indexOf(targetLine);
                if (targetLineIndex !== -1 && targetLineIndex + 1 < lines.length) {
                    urls.push(lines[targetLineIndex + 1]);
                }
            });

            if (debug) {
                console.log(lines)
            } else {
                killThisTask(urls[1], 0);
            }

        }
    });

    if (!debug) { setTimeout(() => killThisTask("error, timeout while waiting for m3u8 url.", 1), 10000); }

    await page.goto("https://twitch.tv/"+channel);

})();
