
baseurl = location.protocol + "//" + location.host;
var chatMessages = "";
var lastVideoTimeUpdate = 0;

if (localStorage.getItem("settings_toggleMultiSelect") === null) { localStorage.setItem("settings_toggleMultiSelect", "true"); }

// ------------------------------------------------------------------------------------------------------------------- //

function copySizeAuto(fromEl, toEl, options = {}) {
    var opts = {
        width: { copy: true, condition: null, fallback: null },
        height: { copy: true, condition: null, fallback: null },
        ...options
    };
    function update() {
        var rect = fromEl.getBoundingClientRect();

        if (opts.width.copy) {
            if (!opts.width.condition || opts.width.condition()) {
                toEl.style.width = rect.width + "px";
            } else if (opts.width.fallback) {
                toEl.style.width = opts.width.fallback();
            }
        }

        if (opts.height.copy) {
            if (!opts.height.condition || opts.height.condition()) {
                toEl.style.height = rect.height + "px";
            } else if (opts.height.fallback) {
                toEl.style.height = opts.height.fallback();
            }
        }
    }
    update();
    var observer = new ResizeObserver(update);
    observer.observe(fromEl);
    window.addEventListener("resize", update);
}

// ------------------------------------------------------------------------------------------------------------------- //

window.addEventListener('load', function () { setTimeout(function () { document.body.classList.remove('init'); }, 50); });
setPrimary(localStorage.getItem("settings_primaryColor") || undefined);
function setPrimary(color = "#2c73d2") {
    document.documentElement.style.setProperty('--primary', color);
    localStorage.setItem("settings_primaryColor", color);
    try { document.querySelector(".modaloverlay").click(); } catch (e) { }
}

function toggleVideolistening(init = false) {
    var grouped = localStorage.getItem("settings_toggleVideolistening") === "true";
    var icon = document.querySelector("#toggleVideolistening i");
    var text = document.querySelector("#toggleVideolistening span");
    if (init) { grouped = !grouped; } else { localStorage.setItem("settings_toggleVideolistening", !grouped); history.pushState(null, '', '/'); getChannels(); getVideos(); }
    if (grouped) {
        icon.className = "fa-solid fa-bars-staggered";
        text.innerHTML = "videolist grouped"
    } else {
        icon.className = "fa-solid fa-bars";
        text.innerHTML = "videolist ungrouped"
    }
}

function toggleMultiSelect(init = false) {
    var multiselect = localStorage.getItem("settings_toggleMultiSelect") === "true";
    var icon = document.querySelector("#toggleMultiSelect i");
    var text = document.querySelector("#toggleMultiSelect span");
    if (init) { multiselect = !multiselect; } else { localStorage.setItem("settings_toggleMultiSelect", !multiselect); }
    if (multiselect) {
        icon.className = "fa-solid fa-check";
        text.innerHTML = "single channel select"
    } else {
        icon.className = "fa-solid fa-check-double";
        text.innerHTML = "multi channel select"
    }
}

function toggleHideSeen(init = false) {
    var hideseen = localStorage.getItem("settings_toggleHideSeen") === "true";
    var icon = document.querySelector("#toggleHideSeen i");
    var text = document.querySelector("#toggleHideSeen span");
    if (init) { hideseen = !hideseen; } else { localStorage.setItem("settings_toggleHideSeen", !hideseen); }
    if (hideseen) {
        icon.className = "fa-solid fa-eye";
        text.innerHTML = "show seen videos";
    } else {
        icon.className = "fa-solid fa-eye-slash";
        text.innerHTML = "hide seen videos";
    }
    renderHideSeen();
}

function toggleDescription(init) {
    var description = localStorage.getItem("settings_toggleDescription") === "true";
    var icon = document.querySelector("#toggleDescription i");
    var text = document.querySelector("#toggleDescription span");
    if (init) { description = !description; } else { localStorage.setItem("settings_toggleDescription", !description); }
    if (description) {
        document.documentElement.style.setProperty('--hint-display', 'inline');
        icon.className = "fa-solid fa-lightbulb";
        text.innerHTML = "show description hints";
    } else {
        document.documentElement.style.setProperty('--hint-display', 'none');
        icon.className = "fa-regular fa-lightbulb";
        text.innerHTML = "hide description hints";
    }
}

function renderHideSeen() {
    if (localStorage.getItem("settings_toggleHideSeen") === "true") {
        document.querySelectorAll('.videoimage.seen').forEach(function (e) {
            e.parentElement.classList.add('hide');
            var wrapper = e.parentElement.parentElement;
            var channeloptions = wrapper && wrapper.previousElementSibling;
            if (channeloptions && channeloptions.classList.contains('channeloptions')) {
                if (!wrapper.querySelector('.videoimage:not(.seen)')) {
                    wrapper.classList.add('hide');
                    wrapper.previousElementSibling.classList.add('hide');
                }
            }
        });
        document.querySelectorAll('.videowrapper').forEach(function (e) {
            var wrapper = e;
            var channeloptions = wrapper && wrapper.previousElementSibling
            if (channeloptions && channeloptions.classList.contains('channeloptions')) {
                if (!wrapper.querySelector('.video')) {
                    wrapper.classList.add('hide');
                    wrapper.previousElementSibling.classList.add('hide');
                }
            }
        });
    } else {
        document.querySelectorAll('.videoimage.seen').forEach(function (e) {
            e.parentElement.classList.remove('hide');
            var wrapper = e.parentElement.parentElement;
            var channeloptions = wrapper && wrapper.previousElementSibling;
            if (channeloptions && channeloptions.classList.contains('channeloptions')) {
                if (wrapper.querySelector('.videoimage.seen')) {
                    wrapper.classList.remove('hide');
                    wrapper.previousElementSibling.classList.remove('hide');
                }
            }
        });
        document.querySelectorAll('.videowrapper').forEach(function (e) {
            var wrapper = e;
            var channeloptions = wrapper && wrapper.previousElementSibling
            if (channeloptions && channeloptions.classList.contains('channeloptions')) {
                wrapper.classList.remove('hide');
                wrapper.previousElementSibling.classList.remove('hide');
            }
        });
    }
}

function fetchChatData() {
    var url = document.querySelector("#chat").getAttribute("chaturl");
    fetch(url)
        .then(response => response.text())
        .then(data => {
            chatMessages = data;
        })
        .catch(e => {
            console.error('Fetch error:', e);
        });
}

function updateChatWindow(video) {
    var chat = document.querySelector("#chat");
    var currentTime = video.currentTime;
    var lines = chatMessages.split('\n');
    var filteredLines = lines.filter(line => {
        var timeMatch = line.match(/^(\d{2}):(\d{2}):(\d{2})/);
        if (timeMatch) {
            var [, hours, minutes, seconds] = timeMatch;
            var messageTime = parseInt(hours, 10) * 3600 + parseInt(minutes, 10) * 60 + parseInt(seconds, 10);
            return messageTime < currentTime;
        }
        return false;
    });
    var recentLines = filteredLines.slice(-100);
    var formattedMessages = recentLines.map(line => formatChatMessage(line));
    chat.innerHTML = formattedMessages.join('');
    chat.scroll({
        top: chat.scrollHeight,
        behavior: 'smooth'
    });
}

function formatChatMessage(chatMessage) {
    const normalMessageRegex = /^(\d{2}:\d{2}:\d{2})\s*(.*)$/;
    let match = chatMessage.match(normalMessageRegex);
    if (match) {
        const [, time, msg] = match;
        if (msg) {
            return `<div class="chatmessage"><span class="time">${time}</span><span class="msg">${msg}</span></div>`;
        }
        return "";
    }
    return `<div class="chatmessage"><span class="msg">${chatMessage}</span></div>`;
}

function handleVideoTimeUpdate() {
    var now = Math.floor(Date.now() / 1000);
    if (now === lastVideoTimeUpdate) return;
    lastVideoTimeUpdate = now;

    try {
        var video = document.querySelector('#video');
        saveVideoTime(video);
        saveVideoLength(video);
        let chat = document.querySelector('#chat');
        if (chat) {
            if (chat.scrollTop + chat.clientHeight >= chat.scrollHeight - 100) {
                updateChatWindow(video);
            }
        }
    } catch (e) { }
}

function saveVideoTime(video) {
    localStorage.setItem(video.getAttribute("filename") + ".time", Math.floor(video.currentTime));
}

function loadVideoTime(video) {
    var time = localStorage.getItem(video.getAttribute("filename") + ".time");
    if (time !== null && time != "0") { video.currentTime = time; } else { video.currentTime = 0.1; }
}

function saveVideoLength(video) {
    localStorage.setItem(video.getAttribute("filename") + ".length", Math.floor(video.duration));
}

function loadVideoLength(video) {
    return localStorage.getItem(video.getAttribute("filename") + ".length");
}

function toggleSeen(filename, persist = true, force = false) {
    icon = document.getElementById(filename).querySelector(".toggleSeen i");
    var makeSeen = false;
    if (force == "seen") { makeSeen = true; }
    else if (force == "unseen") { makeSeen = false; }
    else { makeSeen = icon.className == "fa-solid fa-eye"; }
    if (makeSeen) {
        icon.className = "fa-solid fa-eye-slash"
        document.getElementById(filename).querySelector(".videoimage").classList.add("seen");
        document.getElementById(filename).querySelector(".videooverlay").classList.add("seen");
        if (persist) { localStorage.setItem(filename + ".seen", true); }
    } else {
        icon.className = "fa-solid fa-eye"
        document.getElementById(filename).querySelector(".videoimage").classList.remove("seen");
        document.getElementById(filename).querySelector(".videooverlay").classList.remove("seen");
        if (persist) { localStorage.setItem(filename + ".seen", false); }
    }
    renderHideSeen();
    document.body.setAttribute('tabindex', '-1');
    document.body.focus();
}

async function deleteSeen(filename, skipconfirm = false) {
    if (!skipconfirm) { if (!(await renderModal(getModalOptionsString("Do you really want to reset this recording?", { "yes": true, "no": false })))) { return; } }

    localStorage.removeItem(filename + ".time");
    localStorage.removeItem(filename + ".length");
    localStorage.removeItem(filename + ".seen");

    var video = document.getElementById(filename);
    video.querySelector(".progresswrapper").style.opacity = 0;
    video.querySelector("img").classList.remove("seen");

    toggleSeen(filename, false, "unseen");

    document.body.setAttribute('tabindex', '-1');
    document.body.focus();
}

function sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function getPidString(channel, running) {
    return `<a onclick="event.preventDefault(); (async () => {
        var el = this;
        var oldHTML = el.outerHTML;
        try {
            var res = await callAction('${running ? 'stop' : 'start'}RecordingTask', '${channel}');
            if (res != '${running ? 'task stopped' : 'task started'}') {
                notifyModal(res); // ← geändert: prüfe auf exakten Rückgabewert
            } else {
                el.outerHTML = getPidString('${channel}', ${!running});
            }
        } catch (e) {
            el.outerHTML = oldHTML;
        }
    })()">
        <i class="fa-solid ${running ? 'fa-ban' : 'fa-cloud-arrow-down'}" ${running ? 'style="color: var(--red);"' : ''}></i>
        ${running ? '<span class="hint">stop task</span>' : '<span class="hint">start task</span>'}
    </a>`;
}


function getLoadingPlaceholder() {
    return '<div class="loadingplaceholder"><div class="spinner"></div></div>';
}

function getVideoOptionsString(channel, pids) {
    var pidString = getPidString(channel, pids.includes(channel));
    return `
        <div class="channeloptions">
            <div class="channelname">${channel}</div>
            <a href="https://twitch.tv/${channel}" class=""><i class="fa-brands fa-twitch"></i><span class="hint underlined">view on twitch</span></a>
            <a href="${baseurl}/archive/${channel}" class=""><i class="fa-solid fa-folder-open"></i><span class="hint underlined">open archive</span></a>
            <a class="" onclick="event.preventDefault(); logModal('${channel}')"><i class="fa-solid fa-magnifying-glass"></i><span class="hint">view logs</span></a>
            ${pidString}
            <hr class="channelhr"></hr>
        </div>
    `;
}

function getVideoString(video) {
    return `
        <div class="video" tabindex="0" id="${video.filename}" channel="${video.name}">
            <div class="videoimage"><img src="${video.url_png}" onerror="this.style.display='none';"></img><i class="fa-solid fa-video"></i>unfinshed</div>
            <div class="videooverlay"><div class="progresswrapper"><div class="progressbar"></div></div></div>
            <div class="videoinfo"><div>${video.name}</div><div>${video.date}</div><div>${video.time}</div><div>${video.size} GB</div></div>
            <div class="videosettings">
                <a href="?getVideo=${video.path}" onclick="event.preventDefault(); videoModal(${video})"><i class="fa-solid fa-play"></i></a>
                <div class="toggleSeen" onclick="toggleSeen('${video.filename}')"><i class="fa-solid fa-eye"></i></div>
                <div onclick="deleteSeen('${video.filename}')"><i class="fa-solid fa-rotate-left"></i></div>
                <div onclick="(async () => { await renderModal(getModalOptionsString('Do you really want to delete this recording permanently?', {'yes':true, 'no':false})) && deleteVideo('#${video.filename}', '${video.path}') })()"><i class="fa-solid fa-trash"></i></div>
            </div>
        </div>
    `;
}

function notifyModal(msg, icon = false) {
    icon = icon || `<i class="fa-solid fa-circle-info"></i>`;
    var string = `<div id="notifyModal"><div>${icon}</div><div>${msg}</div></div>`;
    renderModal(string);
}

function getModalOptionsString(msg, options, icon = false) {
    icon = icon || `<i class="fa-solid fa-question"></i>`;

    var btns = "";
    Object.keys(options).forEach(function (key) {
        btns += `<div class="notifyOptionsButton" modalreturn="${options[key]}">` + key + `</div>`;
    });

    var html = `
        <div id="notifyOptionsModal">
            <div class="notifyOptionsIcon">${icon}</div>
            <div class="notifyOptionsTitle">${msg}</div>
            <div class="notifyOptionsButtons">${btns}</div>
        </div>
    `;

    return html;
}

function renderModal(content, evalafter = false) {
    var overlay = document.createElement("div");
    overlay.className = "modaloverlay";

    var modal = document.createElement("div");
    modal.className = "modalcontent";
    modal.innerHTML = content;

    function closeModal(result = null) {
        modal.classList.remove("open");
        document.body.style.overflow = '';
        modal.addEventListener("transitionend", function () {
            overlay.remove();
            if (evalafter) { eval(evalafter) };
            if (typeof overlay.resolve === 'function') { overlay.resolve(result) };
        }, { once: true });
    }

    setTimeout(function () {
        modal.classList.add("open");
        document.body.style.overflow = 'hidden';
    }, 10);

    overlay.addEventListener("click", function (e) {
        if (e.target === overlay) closeModal(null);
    });

    var buttons = modal.querySelectorAll("[modalreturn]");
    buttons.forEach(function (el) {
        var val = el.getAttribute("modalreturn");
        try { val = JSON.parse(val); } catch (e) { };
        el.addEventListener("click", function () {
            closeModal(val);
        });
    });

    overlay.appendChild(modal);
    document.body.appendChild(overlay);

    return new Promise(function (resolve) {
        overlay.resolve = resolve;
    });
}

async function updateLog(target, channel) {
    var element = document.querySelector(`#logview`);
    element.innerHTML = getLoadingPlaceholder();
    var result = await getLogs(channel);
    if (!result) { element.innerHTML = "api error (view in console)"; return; }
    element.innerHTML = result[target];
    element.scrollTop = element.scrollHeight;
}

async function apiFetch(url) {
    url = encodeURI(url);
    var response = await fetch(url).then(function (res) {
        if (!res.ok) throw new Error('HTTP Error ' + res.status);
        return res.json();
    }).then(function (data) {
        if (data.status === true) {
            return data.data;
        } else {
            throw new Error('bad response');
        }
    }).catch(function (error) {
        console.error('Fetch', error);
        return null;
    });
    console.debug(url);
    console.debug(response);
    // await sleep(1000);
    // await sleep(Math.floor(Math.random() * 1501))
    return response;
}

function makeRandomString(length) {
    if (length > 100) length = 100;
    var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    var result = '';
    for (var i = 0; i < length; i++) {
        result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return result;
}

function userPing() {
    var pingIntervalSeconds = 300;
    var build = window.build ? window.build : "dev";
    var now = () => Math.floor(Date.now() / 1000);
    function sendPing() {
        var lastPing = parseInt(localStorage.getItem('settings_lastping') || '0');
        if (now() - lastPing < pingIntervalSeconds - 2) { return; }
        var uuid = localStorage.getItem('settings_uuid');
        if (!uuid) { uuid = makeRandomString(32); localStorage.setItem('settings_uuid', uuid); }
        var random = makeRandomString(5);
        new Image().src = `https://36ip.de/favicon.ico?twitchrecorder_uuid=${uuid}-${build}&nocache=${random}`;
        localStorage.setItem('settings_lastping', now().toString());
    }
    var lastPing = parseInt(localStorage.getItem('settings_lastping') || '0');
    var delay = Math.max(0, pingIntervalSeconds - (now() - lastPing)) * 1000;
    setTimeout(function () {
        sendPing();
        setInterval(sendPing, pingIntervalSeconds * 1000);
    }, delay);
}
userPing();

// ------------------------------------------------------------------------------------------------------------------- //

async function callAction(action, channel) {
    var url = baseurl + `/api/?action=${action}&channel=${channel}`;
    var result = await apiFetch(url);
    if (!result) { return "network error"; }
    return result;
}

async function deleteVideo(video, videopath) {
    var video = document.querySelector(video);
    var channel = video.getAttribute('channel');
    var filename = video.id;
    var pids = await getPids();
    if (videopath.includes(".m3u8") && pids.includes(channel)) { notifyModal("please stop the running task first, before attempt to delete a livestream file."); return; }
    var url = baseurl + `/api/?deleteVideo=${videopath}`;
    var result = await apiFetch(url);
    if (!result) { video.innerHTML = "api error (view in console)"; return; }
    await deleteSeen(filename, true);
    video.remove();
    document.querySelector(".modaloverlay")?.click();
}

async function getPids() {
    var url = baseurl + "/api/?getPids";
    var result = await apiFetch(url);
    if (!result) { return false; }
    return result;
}

async function getLogs(channel) {
    var url = baseurl + `/api/?getLogs&channel=${channel}`;
    var result = await apiFetch(url);
    if (!result) { return false; }
    return result;
}

async function getDiskUsage() {
    var element = document.querySelector("#diskusage");
    element.innerHTML = getLoadingPlaceholder();
    var url = baseurl + "/api/?getDiskusage";
    var result = await apiFetch(url);
    if (!result) { element.innerHTML = "network error"; }
    element.innerHTML = result;
}

async function getChannels() {
    var element = document.querySelector("#header .mid");
    element.innerHTML = getLoadingPlaceholder();
    var url = baseurl + "/api/?getChannels";
    var result = await apiFetch(url);
    if (!result) { element.innerHTML = "api error (view in console)"; return; }

    element.innerHTML = "";
    var current = (new URLSearchParams(location.search).get('getVideos') || "").split(',').filter(Boolean);

    result.forEach(function (channel) {
        var active = current.includes(channel);
        element.innerHTML += `<a href="#" class="${active ? 'active' : ''}" onclick="event.preventDefault(); toggleChannel(this, '${channel}')">${channel}</a>`;
    });

    window.toggleChannel = function (el, channel) {
        var multiselect = localStorage.getItem("settings_toggleMultiSelect") === "true";
        var params = new URLSearchParams(location.search);
        var channels = (params.get('getVideos') || "").split(',').filter(Boolean);
        if (multiselect) {
            var i = channels.indexOf(channel);
            i === -1 ? channels.push(channel) : channels.splice(i, 1);
        } else {
            channels = el.classList.contains('active') ? [] : [channel];
        }
        if (channels.length) {
            params.set('getVideos', channels.join(','));
        } else {
            params.delete('getVideos');
            history.replaceState(null, '', '/');
        }
        getVideos(params.toString() ? '?' + params.toString() : '?getVideos');
        element.querySelectorAll('a').forEach(function (a) {
            a.classList.toggle('active', channels.includes(a.textContent));
        });
    };
}

async function getVideos(filter = "?getVideos") {
    if (filter != "?getVideos") { history.pushState(null, '', filter) }

    var element = document.querySelector("#content");
    element.innerHTML = getLoadingPlaceholder();

    var url = baseurl + "/api/" + filter;
    var result = await apiFetch(url);
    if (!result) { element.innerHTML = "api error (view in console)"; return }

    var grouped = localStorage.getItem("settings_toggleVideolistening") === "true";

    var pids = await getPids();
    if (filter == "?getVideos") {
        if (grouped) {
            pids.forEach(function (pid) {
                if (!result[pid]) { result[pid] = [] }
            });
        }
    } else {
        var params = new URL(url).searchParams;
        var channels = params.get("getVideos").split(",");
        var mode = params.get("mode") == "exclude" ? false : true;
        if (mode) {
            channels.forEach(function (channel) {
                if (!result[channel]) { result[channel] = []; }
            })
        }
    }

    var content = "";

    if (filter == "?getVideos" && grouped) {
        var videos = [];
        Object.keys(result).forEach(function (channel) {
            result[channel].forEach(function (video) {
                videos.push(video);
            });
        });
        videos.sort((a, b) => b.timestamp - a.timestamp);
        if (videos.length) {
            content += `<div class="videowrapper">`;
            videos.forEach(function (video) {
                content += getVideoString(video);
            });
            content += `</div>`;
        }
    } else {
        Object.keys(result).sort().forEach(function (channel) {
            content += getVideoOptionsString(channel, pids);
            content += `<div class="videowrapper">`;
            result[channel].forEach(function (video) {
                content += getVideoString(video);
            });
            content += `</div>`;
        });
    }

    element.innerHTML = content || `<div style="display: flex; justify-content: center; align-items: center;">no files</div>`;
    updateProgress();
}

async function getVideo(filepath) {
    var url = baseurl + `/api/?getVideo=${filepath}`;
    var result = await apiFetch(url);
    if (!result) { return; }
    videoModal(result)
}

function updateProgress() {
    var videos = document.querySelectorAll(".video");
    if (videos) {
        videos.forEach(function (video) {
            let filename = video.id;
            let implicit = localStorage.getItem(filename + ".time");
            let explicit = localStorage.getItem(filename + ".seen");
            let length = localStorage.getItem(filename + ".length");

            let setSeen = false;
            let showProgress = false;
            let progress = 0;

            if (explicit == "true") { setSeen = true }

            if (implicit && length) {
                progress = ((implicit / length) * 100).toFixed(2);
                showProgress = true
                if (progress > 90 && explicit != "false") { setSeen = true }
            }

            if (setSeen) {
                toggleSeen(filename, false, "seen");
            } else {
                toggleSeen(filename, false, "unseen");
            }

            if (showProgress) {
                progresswrapper = video.querySelector(".progresswrapper");
                progressbar = video.querySelector(".progressbar");
                progress = ((implicit / length) * 100).toFixed(2);
                progresswrapper.style.opacity = 1;
                progressbar.style.width = progress + "%";
            }

        })
    }
    renderHideSeen();
}


// ------------------------------------------------------------------------------------------------------------------- //

function themeModal() {
    try { document.querySelector(".modaloverlay").click(); } catch (e) { }
    var string = `
        <div id="themeModal">
            <div><i class="fa-solid fa-palette"></i></div>
            <div>select a theme color</div>
            <div id="colorpicker">
                <div onclick="setPrimary('#2c73d2')" style="background-color: #2c73d2"></div>
                <div onclick="setPrimary('#d51930')" style="background-color: #d51930"></div>
                <div onclick="setPrimary('#2ecc71')" style="background-color: #2ecc71"></div>
                <div onclick="setPrimary('#7c5eda')" style="background-color: #7c5eda"></div>
                <div onclick="setPrimary('#ffe081')" style="background-color: #ffe081"></div>
                <div onclick="setPrimary('#EB6700')" style="background-color: #EB6700"></div>
                <div onclick="setPrimary('#bb00b3')" style="background-color: #bb00b3"></div>
                <div onclick="setPrimary('#ffffff')" style="background-color: #ffffff"></div>
            </div>
        </div>
    `;
    renderModal(string);
}

async function addModal() {
    var string = `
        <div id="addModal">
            <div><i class="fa-solid fa-cloud-arrow-down"></i></div>
            <div>add new twitch user to be recorded</div>
            <form onsubmit="event.preventDefault(); (async () => { if(this.addNew.value.trim() !== '') { window.added = true; response = await callAction('startRecordingTask', this.addNew.value); this.addNew.value=''; document.querySelector('#modalresponse').innerHTML = response; } })()">
                <input name="addNew" type="text" placeholder="enter name here"> </input>
            </form>
            <div id="modalresponse" style="color: var(--primary); text-align: center;">&nbsp;</div>
        </div>
    `;
    var evalafter = `setTimeout(function(){if (typeof window.added != "undefined") { delete window.added; reload(); }}, 500);`;
    renderModal(string, evalafter);
    document.querySelector("input").focus();

}

async function logModal(channel) {
    var string = `<div id="logModal">
        <div id="logsettings">
            <a class="" onclick="event.preventDefault(); updateLog('logtask', '${channel}')"><i class="fa-solid fa-list-check"></i><span class="hint">task</span></a>
            <a class="" onclick="event.preventDefault(); updateLog('logstreams', '${channel}')"><i class="fa-solid fa-film"></i><span class="hint">streams</span></a>
            <a class="" onclick="event.preventDefault(); updateLog('logffmpeg', '${channel}')"><i class="fa-solid fa-video"></i></i><span class="hint">recording</span></a>
            <a class="" onclick="event.preventDefault(); updateLog('logfix', '${channel}')"><i class="fa-solid fa-hammer"></i><span class="hint">fix</span></a>
            <a class="" onclick="event.preventDefault(); updateLog('logthumbnail', '${channel}')"><i class="fa-solid fa-image"></i></i><span class="hint">thumbnail</span></a>
            <hr class="channelhr"></hr>
        </div>
        <div id="logview"></div>
    </div>`;
    renderModal(string);
    updateLog('logtask', channel)
}

async function videoModal(target) {

    var targetPath = `?getVideo=${target.path}`;

    if (window.location.search === targetPath) {
        previousPath = "/";
    } else {
        previousPath = window.location.pathname + window.location.search;
        history.pushState(null, '', targetPath);
    }

    var string = `<div id="videoModal">
        <video id="video" src="${target.url_video}" filename="${target.filename}" controls></video>
        <div id="videoOptions">
            <div class="hideonmobile">${target.name}</div><div class="hideonmobile">${target.date}</div><div class="hideonmobile">${target.time}</div><div class="hideonmobile">${target.size} GB</div>
            <input style="accent-color:var(--primary);" type="range" min="0.0" max="4.0" step="0.1" value="1" oninput="document.querySelector('video').playbackRate=this.value; this.nextElementSibling.textContent=parseFloat(this.value).toFixed(1)"><span style="width: 25px">1.0</span>
            <div onclick="(async () => { await renderModal(getModalOptionsString('Do you really want to delete this recording permanently?', {'yes':true, 'no':false})) && deleteVideo('#${target.filename}', '${target.path}') })()"><i class="fa-solid fa-trash"></i></div>
        </div>
        <div id="chat" chaturl="${target.url_log}"></div>
    </div>`;

    var evalafter = `window.hls?.destroy(); history.pushState(null, '', '${previousPath}'); updateProgress();`;

    renderModal(string, evalafter);

    var video = document.querySelector('#video');
    var options = document.querySelector("#videoOptions");
    var chat = document.querySelector('#chat');
    var timer;

    function showOptions() {
        options.classList.add("show");
        clearTimeout(timer);
        timer = setTimeout(function () {
            options.classList.remove("show");
        }, 2000);
    }

    function hideOptions() {
        clearTimeout(timer);
        options.classList.remove("show");
    }

    setTimeout(function () {
        video.addEventListener("mouseover", showOptions);
        video.addEventListener("mousemove", showOptions);
        video.addEventListener("mouseout", hideOptions);

        options.addEventListener("mouseover", showOptions);
        options.addEventListener("mousemove", showOptions);
        options.addEventListener("mouseout", hideOptions);

        copySizeAuto(video, options, { height: { copy: false } });
        copySizeAuto(video, chat, {
            width: { copy: false },
            height: {
                copy: true,
                condition: () => window.innerWidth >= 800,
                fallback: () => (window.innerHeight - video.clientHeight - 50 - 40) + 'px'
            }
        });
    }, 300);

    if (video.src.includes(".m3u8")) {
        hls = new Hls({
            startLevel: -1,
            liveSyncDurationCount: 1,
            maxBufferLength: 30,
            maxBufferSize: 60 * 1000 * 1000,
            startFragPrefetch: true,
            maxBufferHole: 0.5
        });

        hls.loadSource(target.url_video);
        hls.attachMedia(video);
    }

    loadVideoTime(video);

    video.addEventListener('timeupdate', handleVideoTimeUpdate);

    var lastDuration = 0;
    video.addEventListener('durationchange', function () {
        if (video.duration !== lastDuration) {
            lastDuration = video.duration;
            fetchChatData(video);
        }
    });

    video.play();

}

// ------------------------------------------------------------------------------------------------------------------- //

function createToggleMenu() {
    var style = document.createElement('style');
    document.head.appendChild(style);

    var menu = document.createElement('div');
    menu.id = "sideMenu";
    menu.innerHTML = `
        <a onclick="event.preventDefault();" class="settingsBtn"><i class="fa-solid fa-gear"></i><span class="hint">close</span></a>
        <a onclick="event.preventDefault(); themeModal();" id="themeSelect"><i class="fa-solid fa-palette"></i><span class="hint">change theme color</span></a>
        <a onclick="event.preventDefault(); toggleVideolistening();" id="toggleVideolistening"><i class="fa-solid fa-bars-staggered"></i><span class="hint">videolist grouping</span></a>
        <a onclick="event.preventDefault(); toggleMultiSelect();" id="toggleMultiSelect"><i class="fa-solid fa-check"></i><span class="hint">multi channel select</span></a>
        <a onclick="event.preventDefault(); toggleHideSeen();" id="toggleHideSeen"><i class="fa-solid fa-eye"></i><span class="hint">hide seen videos</span></a>
        <a onclick="event.preventDefault(); toggleDescription();" id="toggleDescription"><i class="fa-solid fa-lightbulb"></i><span class="hint">show description hints</span></a>
    `;
    document.body.appendChild(menu);

    var buttons = document.querySelectorAll(".settingsBtn");
    buttons.forEach(function (btn) {
        btn.addEventListener("click", function () {
            var isOpen = menu.classList.contains("open");
            if (isOpen) {
                menu.classList.remove("open");
                document.body.classList.remove("sideMenu-open");
                document.body.style.marginRight = "0px";
            } else {
                menu.classList.add("open");
                document.body.classList.add("sideMenu-open");
                document.body.style.marginRight = menu.offsetWidth + "px";
            }
        });
    });
    var observer = new ResizeObserver(function () {
        if (menu.classList.contains("open")) {
            document.body.style.marginRight = menu.offsetWidth + "px";
        }
    });
    observer.observe(menu);
}


function initialize() {
    createToggleMenu();
    toggleVideolistening(true)
    toggleMultiSelect(true)
    toggleHideSeen(true)
    toggleDescription(true)
}

async function reload(reset = false) {

    var filter;
    var video;

    if (reset) { history.pushState(null, '', '/'); }

    var params = new URLSearchParams(window.location.search);

    if (params.get('getVideos') !== null) {
        let mode = params.get('mode') !== null ? `&mode=${params.get('mode')}` : "";
        filter = `?getVideos=${params.get('getVideos')}${mode}`;
    }

    if (params.get('getVideo') !== null) {
        video = `${params.get('getVideo')}`;
    }

    getDiskUsage();
    getChannels();
    getVideos(filter);
    if (video) { getVideo(video); }

};

initialize();
reload();
