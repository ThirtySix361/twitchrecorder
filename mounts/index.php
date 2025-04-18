<?php

    function removeExtensions($string) {
        $parts = explode('.', $string);
        array_pop($parts);
        return implode('.', $parts);
    }

    function removeFile($file) {
        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $return = [];
        if (file_exists($file)) {
            unlink($file);
            $dir = dirname($file);
            if (is_dir($dir)) {
                if (count(scandir($dir)) === 2) {
                    rmdir($dir);
                }
            }
            $return[$ext]["attempt"] = "remove";
            $return[$ext]["file"] = basename($file);
            $return[$ext]["status"] = "successful";
        } else {
            $return[$ext]["attempt"] = "remove";
            $return[$ext]["file"] =  basename($file);
            $return[$ext]["status"] = "failed";
        }
        return $return;
    }

    if (isset($_GET['delete'])) {
        $requested_file = urldecode($_GET['delete']);
        $file = removeExtensions($requested_file);
        $return = [];

        if (strpos(__DIR__.$file, __DIR__.'/archive') !== false) {
            $response1 = removeFile(__DIR__.$file.'.mp4');
            $response2 = removeFile(__DIR__.$file.'.log');
            $response3 = removeFile(__DIR__.$file.'.png');
            $response4 = removeFile(__DIR__.$file.'.raw');
            $response5 = removeFile(__DIR__.$file.'.init');
            $response6 = removeFile(__DIR__.$file.'.m3u8');

            $m4sFiles = glob(__DIR__.$file.'*.m4s');
            $response7 = [];
            foreach ($m4sFiles as $m4sFile) {
                $response7[] = removeFile($m4sFile);
            }

            $return["response"] = array_merge($response1, $response2, $response3, $response4, $response5, $response6, $response7);
        } else {
            $return["response"]["attempt"] = "remove";
            $return["response"]["file"] = "$requested_file";
            $return["response"]["status"] = "failed";
        }

        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json');
        echo json_encode($return);
        exit();
    }

    function prettyName($name) {
        $name = pathinfo($name, PATHINFO_FILENAME);
        $lastUnderscorePos = strrpos($name, '_');
        $beforeLastUnderscore = substr($name, 0, $lastUnderscorePos);
        $timePart = substr($name, $lastUnderscorePos + 1);
        $secondLastUnderscorePos = strrpos($beforeLastUnderscore, '_');
        $datePart = substr($beforeLastUnderscore, $secondLastUnderscorePos + 1);
        $streamerName = substr($beforeLastUnderscore, 0, $secondLastUnderscorePos);
        list($year, $month, $day) = explode('-', $datePart);
        list($hour, $minute) = explode('-', $timePart);
        $formattedDate = sprintf('%02d.%02d.%04d', $day, $month, $year);
        $formattedTime = sprintf('%02d:%02d', $hour, $minute);
        return "$streamerName $formattedDate $formattedTime";
    }

    function prettyDate($name) {
        $name = pathinfo($name, PATHINFO_FILENAME);
        $lastUnderscorePos = strrpos($name, '_');
        $beforeLastUnderscore = substr($name, 0, $lastUnderscorePos);
        $timePart = substr($name, $lastUnderscorePos + 1);
        $secondLastUnderscorePos = strrpos($beforeLastUnderscore, '_');
        $datePart = substr($beforeLastUnderscore, $secondLastUnderscorePos + 1);
        list($year, $month, $day) = explode('-', $datePart);
        list($hour, $minute) = explode('-', $timePart);
        $formattedDate = sprintf('%04d.%02d.%02d', $year, $month, $day);
        $formattedTime = sprintf('%02d:%02d', $hour, $minute);
        return "$formattedDate $formattedTime";
    }

    function getSubDirs($dir) {
        $subDirs = [];
        $items = new DirectoryIterator($dir);
        foreach ($items as $item) {
            if ($item->isDir() && !$item->isDot()) {
                $subDirs[] = $item->getFilename();
            }
        }
        return $subDirs;
    }

    function getSize($item) {
        if ( $item->getExtension() == "m3u8" ) {
            $totalSize = 0;
            $m3u8Path = $item->getPathname();
            $lines = file($m3u8Path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $baseDir = dirname($m3u8Path);
            foreach ($lines as $line) {
                if (strpos($line, '#') === 0) continue;
                $tsPath = realpath($baseDir . DIRECTORY_SEPARATOR . $line);
                if ($tsPath && file_exists($tsPath)) {
                    $totalSize += filesize($tsPath);
                }
            }
            return number_format($totalSize / (1024 * 1024 * 1024), 2);
        } else {
            return number_format($item->getSize() / (1024 * 1024 * 1024), 2);
        }
    }

    function getFilteredFiles($baseurl, $dir, $selectedDirs) {
        $files = [];
        $items = new DirectoryIterator($dir);
        foreach ($items as $item) {
            if ($item->isDot()) continue;

            if ($item->isDir() && in_array($item->getFilename(), $selectedDirs)) {
                $files = array_merge($files, getFilteredFiles($baseurl, $item->getPathname(), $selectedDirs));
            }

            if (!$item->isFile()) continue;

            $ext = $item->getExtension();
            if (!in_array($ext, ['mp4', 'm3u8'])) continue;

            $basePath = str_replace(__DIR__, '', $item->getPathname());
            $baseUrlPath = $baseurl . $basePath;
            $baseName = pathinfo($basePath, PATHINFO_DIRNAME) . '/' . pathinfo($basePath, PATHINFO_FILENAME);
            $isLive = ($ext === 'm3u8');

            $entry = [
                'path' => $basePath,
                'date' => $item->getMTime(),
                'prettyDate' => prettyDate($item->getFilename()),
                'name' => pathinfo($item->getFilename(), PATHINFO_FILENAME),
                'prettyName' => prettyName($item->getFilename()),
                'size' => getSize($item, $ext),
                'log' => $baseurl . $baseName . '.log',
                'png' => $baseurl . $baseName . '.png',
                'noext' => $baseurl . $baseName
            ];

            if ($isLive) $entry['m3u8'] = $baseUrlPath;
            else         $entry['mp4']  = $baseUrlPath;

            $files[] = $entry;
        }
        return $files;
    }

    function printDiskusage($path_to_archive) {
        if (is_dir($path_to_archive)) {
            $totalSpace = disk_total_space($path_to_archive);
            $freeSpace = disk_free_space($path_to_archive);
            $usedSpace = $totalSpace - $freeSpace;
            echo "<div style='text-align: center; margin: 10px;'> " . round($usedSpace / 1073741824, 0) . " of " . round($totalSpace / 1073741824, 0) . " GB used</div>";
        } else {
            echo "<div style='text-align: center; margin: 10px;'> path of $path_to_archive not found </div>";
        }
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 || $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ? "https://" : "http://";
    $baseurl = rtrim(strtok($protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], '?'), '/');

    $subDirs = getSubDirs(__DIR__ . '/archive');
    $selectedDirs = isset($_GET['dirs']) ? explode(',', $_GET['dirs']) : $subDirs;
    $selectedDirs = array_intersect($selectedDirs, $subDirs);

    $videoFiles = getFilteredFiles($baseurl, __DIR__ . '/archive', $selectedDirs);

    usort($videoFiles, function($a, $b) {
        return strcmp($b['prettyDate'], $a['prettyDate']);
    });

    ########## debug ##########
    // echo '<pre>';
    // print_r($videoFiles);
    // echo '</pre>';

?>
<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="shortcut icon" type="image/x-icon" href="./favicon.ico">
        <title>Twitchrecorder</title>
        <style>
            * {
                box-sizing: border-box;
                font-size: 16px;
            }
            svg {
                filter: invert(70%) sepia(0%) saturate(11%) hue-rotate(82deg) brightness(89%) contrast(85%);
                display: inline-block;
                text-align: center;
                height: 16px;
            }
            body {
                display: flex;
                flex-direction: column;
                margin: 0 auto;
                font-family: Verdana, sans-serif;
                color: #999;
                background-color: #333;
                min-height: 100vh;
            }
            .navigationwrapper {
                z-index: 9999;
                position: sticky;
                top: 0;
                left:0;
                right: 0;
                text-align: center;
                background-color: #444;
                text-align: center;
            }
            .navigation {
                display: flex;
                justify-content: center;
                align-items: center;
                flex-wrap: wrap;
            }
            .navigation button {
                display: flex;
                justify-content: center;
                align-items: center;
                margin: 5px 10px;
                min-width: 75px;
                padding: 5px 10px;
                cursor: pointer;
                background-color: #555;
                color: #999;
                border: 0;
                border-radius: 3px;
            }
            .navigation button.active {
                background-color: #777;
            }
            .navigation button:hover {
                background-color: #666;
            }
            .contentwrapper {
                display: flex;
                justify-content: center;
                align-items: center;
                flex: 1;
            }
            .video-name {
                word-break: break-word;
            }
            .video-chat-wrapper {
                display: flex;
                justify-content: center;
            }
            .videowrapper {
                display: flex;
                justify-content: center;
                align-items: center;
                width: 67vw;
                height: 75vh;
                padding: 15px;
                background-color: #555;
            }
            .video {
                width: 100%;
                height: 100%;
            }
            .video-options {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 5px;
            }
            .video-options button {
                display: flex;
                justify-content: center;
                min-width: 50px;
                padding: 5px 10px;
                background-color: #555;
                color: #999;
                border: 0;
                border-radius: 3px;
            }
            .video-options button:hover {
                cursor: pointer;
                background-color: #666;
            }
            .chatwrapper {
                width: 25vw;
                height: 75vh;
                padding: 15px;
                background-color: #555;
            }
            .chat {
                width: 100%;
                height: 100%;
                text-align: left;
                overflow-y: auto;
                overflow-x: hidden;
                line-height: 1.5em;
                word-wrap: break-word;
            }
            .chat .chatmessage .time {
                font-size: 10px;
                margin-right: 5px;
            }
            .chat .chatmessage .msg {
                font-size: 16px;
                margin-right: 5px;
            }
            .chat .chatmessage:nth-child(odd) {
                //background-color: #444;
            }
            .chat .chatmessage .chat-author__display-name {
                font-weight: bold;
                margin-left: 5px;
            }
            .chat .chatmessage img {
                margin-left: 3px;
            }
            .footer {
                z-index: 9999;
                padding: 10px;
                position: sticky;
                bottom: 0;
                left:0;
                right: 0;
                text-align: center;
                background-color: #444
            }

            @media only screen and (max-width: 1200px) {

                .navigationwrapper {
                    position: inherit;
                }
                .navigation div {
                    order: 0;
                    width: 100%;
                }
                .navigation button {
                    order: 1;
                }
                .video-chat-wrapper {
                    flex-direction: column;
                }
                .videowrapper {
                    width: 90vw;
                    height: 100%;
                }
                .chatwrapper {
                    width: 90vw;
                    height: 37.5vh;
                }
                .chat {
                    line-height: 1.0em;
                }
                .chat .chatmessage .time {
                    margin-right: 5px;
                }
                .chat .chatmessage .name {
                    margin-right: 5px;
                    color: #333;
                }
                .chat .chatmessage .msg {
                    margin-right: 5px;
                }

            }
        </style>
        <script>
            document.addEventListener("DOMContentLoaded", () => {

                video = document.querySelector("video");
                if (video) {
                    loadVideoTime(video);

                    const saveTime = () => saveVideoTime(video);
                    const saveLength = () => saveVideoLength(video);
                    const updateChat = () => updateChatWindow(video);

                    video.addEventListener('play', () => {
                        fetchChatData(video)
                        video.addEventListener('timeupdate', saveTime)
                        video.addEventListener('timeupdate', saveLength)
                        video.addEventListener('timeupdate', updateChat)

                    });
                    video.addEventListener('pause', () => video.removeEventListener('timeupdate', saveTime));
                    video.addEventListener('ended', () => video.removeEventListener('timeupdate', saveTime));
                    video.addEventListener('pause', () => video.removeEventListener('timeupdate', saveLength));
                    video.addEventListener('ended', () => video.removeEventListener('timeupdate', saveLength));
                    video.addEventListener('pause', () => video.removeEventListener('timeupdate', updateChat));
                    video.addEventListener('ended', () => video.removeEventListener('timeupdate', updateChat));
                }

            });

            function fetchChatData(video) {
                var logFilePath = video.getAttribute("videourl_noext") + ".log";
                let url = logFilePath
                fetch(url)
                    .then(response => response.text())
                    .then(data => {
                        chatMessages = data;
                    })
                    .catch(e => {
                        console.error('Fetch error:', e);
                    });
            }

            function formatChatMessage(chatMessage) {
                const normalMessageRegex = /^(\d{2}:\d{2}:\d{2})\s*(.*)$/;
                let match = chatMessage.match(normalMessageRegex);
                if (match) {
                    const [, time, msg] = match;
                    return `<div class="chatmessage"><span class="time">${time}</span><span class="msg">${msg}</span></div>`;
                }
                return `<div class="chatmessage"><span class="msg">${chatMessage}</span></div>`;
            }

            function updateChatWindow(video) {
                const chatElement = video.parentNode.parentNode.querySelector(".chat");
                const currentTime = video.currentTime;

                const lines = chatMessages.split('\n');

                const filteredLines = lines.filter(line => {
                    const timeMatch = line.match(/^(\d{2}):(\d{2}):(\d{2})/);
                    if (timeMatch) {
                        const [, hours, minutes, seconds] = timeMatch;
                        const messageTime = parseInt(hours, 10) * 3600 + parseInt(minutes, 10) * 60 + parseInt(seconds, 10);
                        return messageTime < currentTime;
                    }
                    return false;
                });

                const recentLines = filteredLines.slice(-100);
                const formattedMessages = recentLines.map(line => formatChatMessage(line));

                chatElement.innerHTML = formattedMessages.join('');

                chatElement.scroll({
                    top: chatElement.scrollHeight,
                    behavior: 'smooth'
                });
            }

            function saveVideoTime(video) {
                localStorage.setItem(video.getAttribute("videourl_noext"), video.currentTime);
            }

            function loadVideoTime(video) {
                const time = localStorage.getItem(video.getAttribute("videourl_noext"));
                if (time !== null) {
                    video.currentTime = time;
                } else {
                    video.currentTime = 0.1;
                }
            }

            function saveVideoLength(video) {
                localStorage.setItem(video.getAttribute("videourl_noext")+".length", video.duration);
            }

            function loadVideoLength(video) {
                return localStorage.getItem(video.getAttribute("videourl_noext")+".length");
            }

            function switchSeen(thiselement, filePath, persist=true) {
                var target = thiselement.parentNode.parentNode;
                if (target.classList.contains("seen")) {
                    target.classList.remove("seen");
                    thiselement.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path fill="#000000" d="M288 32c-80.8 0-145.5 36.8-192.6 80.6C48.6 156 17.3 208 2.5 243.7c-3.3 7.9-3.3 16.7 0 24.6C17.3 304 48.6 356 95.4 399.4C142.5 443.2 207.2 480 288 480s145.5-36.8 192.6-80.6c46.8-43.5 78.1-95.4 93-131.1c3.3-7.9 3.3-16.7 0-24.6c-14.9-35.7-46.2-87.7-93-131.1C433.5 68.8 368.8 32 288 32zM144 256a144 144 0 1 1 288 0 144 144 0 1 1 -288 0zm144-64c0 35.3-28.7 64-64 64c-7.1 0-13.9-1.2-20.3-3.3c-5.5-1.8-11.9 1.6-11.7 7.4c.3 6.9 1.3 13.8 3.2 20.7c13.7 51.2 66.4 81.6 117.6 67.9s81.6-66.4 67.9-117.6c-11.1-41.5-47.8-69.4-88.6-71.1c-5.8-.2-9.2 6.1-7.4 11.7c2.1 6.4 3.3 13.2 3.3 20.3z"/></svg>'
                    if (persist) { localStorage.setItem(filePath+".seen", false); }
                } else {
                    target.classList.add("seen");
                    thiselement.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><path fill="#000000" d="M38.8 5.1C28.4-3.1 13.3-1.2 5.1 9.2S-1.2 34.7 9.2 42.9l592 464c10.4 8.2 25.5 6.3 33.7-4.1s6.3-25.5-4.1-33.7L525.6 386.7c39.6-40.6 66.4-86.1 79.9-118.4c3.3-7.9 3.3-16.7 0-24.6c-14.9-35.7-46.2-87.7-93-131.1C465.5 68.8 400.8 32 320 32c-68.2 0-125 26.3-169.3 60.8L38.8 5.1zM223.1 149.5C248.6 126.2 282.7 112 320 112c79.5 0 144 64.5 144 144c0 24.9-6.3 48.3-17.4 68.7L408 294.5c8.4-19.3 10.6-41.4 4.8-63.3c-11.1-41.5-47.8-69.4-88.6-71.1c-5.8-.2-9.2 6.1-7.4 11.7c2.1 6.4 3.3 13.2 3.3 20.3c0 10.2-2.4 19.8-6.6 28.3l-90.3-70.8zM373 389.9c-16.4 6.5-34.3 10.1-53 10.1c-79.5 0-144-64.5-144-144c0-6.9 .5-13.6 1.4-20.2L83.1 161.5C60.3 191.2 44 220.8 34.5 243.7c-3.3 7.9-3.3 16.7 0 24.6c14.9 35.7 46.2 87.7 93 131.1C174.5 443.2 239.2 480 320 480c47.8 0 89.9-12.9 126.2-32.5L373 389.9z"/></svg>'
                    if (persist) { localStorage.setItem(filePath+".seen", true); }
                }
            }

            function deleteSeen(filePath) {
                if (confirm('Are you sure, you want to reset this recording?')) {
                    localStorage.removeItem(filePath);
                    localStorage.removeItem(filePath+".seen");
                    localStorage.removeItem(filePath+".length");
                    setTimeout(() => { location.reload(); }, 500);
                }
            }

            function deleteFile(filePath) {
                if (confirm('Are you sure, you want to delete this file?')) {
                    baseurl = (location.origin + location.pathname).replace(/\/$/, '');
                    storage = baseurl+filePath.replace(/\.[^/.]+$/, "");
                    localStorage.removeItem(storage);
                    localStorage.removeItem(storage+".seen");
                    localStorage.removeItem(storage+".length");
                    url = baseurl+"?delete="+filePath;
                    fetch(url)
                        .then(response => response.json())
                        .then(data => { console.log(JSON.stringify(data)) });
                    setTimeout(() => { location.reload(); }, 500);
                }
            }
        </script>

    </head>
    <body>

        <?php if (isset($_GET['video']) && is_file(__DIR__.$_GET['video'])) { ?>

            <?php
                $videopath = $_GET['video'];
                $videourl = $baseurl.$videopath;
                $videourl_noext = substr($videourl, 0, strrpos($videourl, '.'));
                $videosize = number_format(filesize(__DIR__.$videopath) / (1024 * 1024 * 1024), 2);
                $videoname = basename($videopath);
                $videoprettyname = prettyName($videoname);
            ?>

            <div class="navigationwrapper">
                <div class="navigation">
                    <button onclick="location.href='<?=$baseurl?>'"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="#000000" d="M177.5 414c-8.8 3.8-19 2-26-4.6l-144-136C2.7 268.9 0 262.6 0 256s2.7-12.9 7.5-17.4l144-136c7-6.6 17.2-8.4 26-4.6s14.5 12.5 14.5 22l0 72 288 0c17.7 0 32 14.3 32 32l0 64c0 17.7-14.3 32-32 32l-288 0 0 72c0 9.6-5.7 18.2-14.5 22z"/></svg></button>
                    <div><?=$videoprettyname?> (<?= $videosize ?> GB)</div>
                    <button onclick="deleteFile('<?=$videopath?>')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M135.2 17.7L128 32 32 32C14.3 32 0 46.3 0 64S14.3 96 32 96l384 0c17.7 0 32-14.3 32-32s-14.3-32-32-32l-96 0-7.2-14.3C307.4 6.8 296.3 0 284.2 0L163.8 0c-12.1 0-23.2 6.8-28.6 17.7zM416 128L32 128 53.2 467c1.6 25.3 22.6 45 47.9 45l245.8 0c25.3 0 46.3-19.7 47.9-45L416 128z"/></svg></button>
                </div>
            </div>

            <div class="contentwrapper">
                <div class="content">
                    <div class="video-chat-wrapper">
                        <div class="videowrapper">
                            <video class="video" src="<?=$videourl?>" videourl_noext="<?=$videourl_noext?>" controls></video>
                        </div>
                        <div class="chatwrapper">
                            <div class="chat"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!--<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>-->
            <script src="hls.js"></script>
            <script>
                videoelement = document.querySelector("video");
                if ( videoelement.src.includes(".m3u8") ) {
                    hls = new Hls({
                        startLevel: -1,
                        liveSyncDurationCount: 1,
                        maxBufferLength: 30,
                        maxBufferSize: 60 * 1000 * 1000,
                        startFragPrefetch: true,
                        maxBufferHole: 0.5
                    });

                    hls.loadSource("<?=$videourl?>");
                    hls.attachMedia(videoelement);
                }
            </script>

        <?php } else { ?>

            <script>
                document.addEventListener("DOMContentLoaded", () => {

                    navigationButtons = document.querySelectorAll('.navigation button');
                    navigationButtons.forEach(button => {
                        button.addEventListener('click', () => {
                            const dir = button.getAttribute('data-dir');
                            const urlParams = new URLSearchParams(window.location.search);
                            let dirs = urlParams.get('dirs') ? urlParams.get('dirs').split(',') : [];

                            if (dirs.includes(dir)) {
                                dirs = dirs.filter(d => d !== dir);
                            } else {
                                dirs.push(dir);
                            }

                            if (dirs.length > 0) {
                                urlParams.set('dirs', dirs.join(','));
                            } else {
                                urlParams.delete('dirs');
                            }

                            window.location.search = urlParams.toString();
                        });
                    });

                    const urlParams = new URLSearchParams(window.location.search);
                    const selectedDirs = urlParams.get('dirs') ? urlParams.get('dirs').split(',') : [];
                    navigationButtons.forEach(button => {
                        const dir = button.getAttribute('data-dir');
                        if (selectedDirs.includes(dir)) {
                            button.classList.add('active');
                        }
                    });

                    images = document.querySelectorAll("img");
                    if (images) {
                        images.forEach(function(image) {
                            let videosrc = image.src.replace(".png", "")
                            let implicit = localStorage.getItem(videosrc);
                            let explicit = localStorage.getItem(videosrc+".seen");
                            let length = localStorage.getItem(videosrc+".length");

                            let setSeen = false;
                            let showProgress = false;
                            let progress = 0;

                            if ( explicit == "true" ) {
                                setSeen = true
                            }

                            if ( implicit && length ) {
                                progress = ((implicit / length) * 100).toFixed(2);
                                showProgress = true

                                if ( progress > 90 && explicit != "false" ) {
                                    setSeen = true
                                }
                            }

                            if ( setSeen ) {
                                target = image.parentNode.parentNode.parentNode;
                                buttons = target.querySelectorAll('.video-options button');
                                for (let btn of buttons) {
                                    if (btn.getAttribute('onclick')?.startsWith("switchSeen")) {
                                        switchSeen(btn, videosrc, false);
                                        break;
                                    }
                                }
                            }

                            if ( showProgress ) {
                                target = image.parentNode.parentNode;
                                progress = ((implicit / length) * 100).toFixed(2);
                                target.querySelector(".progress-bar").style.display = "block";
                                target.querySelector(".progress-fill").style.width = progress+"%";
                            }

                        })
                    }

                });

            </script>

            <style>
                .contentwrapper {
                    display: block;
                }
                .content {
                    margin: 10px 0px;
                    display: flex;
                    justify-content: center;
                    flex-flow: row wrap;
                    gap: 10px;
                }
                .preview-wrapper {
                    overflow: hidden;
                    width: auto;
                    text-align: center;
                    background-color: #444;
                    border-radius: 5px;
                }
                .seen {
                    filter: brightness(0.5);
                    transform: scale(0.975);
                }
                .video-name {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    margin: 5px 5px 10px 5px;
                    word-break: break-word;
                }
                .video-preview {
                    overflow: hidden;
                    position: relative;
                    border-radius: 5px;
                }
                .video-preview a {
                    display: block;
                }
                .video-preview .progress-bar {
                    display: none;
                    position: absolute;
                    bottom: 0;
                    left: 0;
                    width: 100%;
                    height: 4%;
                    background-color: rgba(255, 255, 255, 0.50);
                }
                .video-preview .progress-fill {
                    height: 100%;
                    background-color: rgba(255, 0, 0, 0.75);
                }
                .video-options {
                    margin: 5px;
                }
                .video-options button {
                    width: 100%;
                }
                img {
                    display: block;
                    width: 192px;
                    height: 108px;
                    /* width: 256px;
                    height: 144px; */
                }
            </style>

            <div class="navigationwrapper">
                <div class="navigation">
                    <?php foreach ($subDirs as $dir): ?>
                        <button data-dir="<?= htmlspecialchars($dir) ?>"><?= htmlspecialchars($dir) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="contentwrapper">
                <div class="content">
                    <?php foreach ($videoFiles as $video): ?>
                    <div class="preview-wrapper">
                        <div class="video-preview">
                            <a href="?video=<?=$video['path']?>"><img class="preview-img" src="<?=$video['png']?>"/></a>
                            <div class="progress-bar"><div class="progress-fill"></div></div>
                        </div>
                        <div class="video-name"><?= preg_replace('/\s/', '<br>', $video['prettyName'], 1) ?><br>(<?= $video['size'] ?> GB)</div>
                        <div class="video-options">
                            <button onclick="switchSeen(this,'<?=$video['noext']?>')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path fill="#000000" d="M288 32c-80.8 0-145.5 36.8-192.6 80.6C48.6 156 17.3 208 2.5 243.7c-3.3 7.9-3.3 16.7 0 24.6C17.3 304 48.6 356 95.4 399.4C142.5 443.2 207.2 480 288 480s145.5-36.8 192.6-80.6c46.8-43.5 78.1-95.4 93-131.1c3.3-7.9 3.3-16.7 0-24.6c-14.9-35.7-46.2-87.7-93-131.1C433.5 68.8 368.8 32 288 32zM144 256a144 144 0 1 1 288 0 144 144 0 1 1 -288 0zm144-64c0 35.3-28.7 64-64 64c-7.1 0-13.9-1.2-20.3-3.3c-5.5-1.8-11.9 1.6-11.7 7.4c.3 6.9 1.3 13.8 3.2 20.7c13.7 51.2 66.4 81.6 117.6 67.9s81.6-66.4 67.9-117.6c-11.1-41.5-47.8-69.4-88.6-71.1c-5.8-.2-9.2 6.1-7.4 11.7c2.1 6.4 3.3 13.2 3.3 20.3z"/></svg></button>
                            <button onclick="deleteSeen('<?=$video['noext']?>')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path fill="#000000" d="M125.7 160l50.3 0c17.7 0 32 14.3 32 32s-14.3 32-32 32L48 224c-17.7 0-32-14.3-32-32L16 64c0-17.7 14.3-32 32-32s32 14.3 32 32l0 51.2L97.6 97.6c87.5-87.5 229.3-87.5 316.8 0s87.5 229.3 0 316.8s-229.3 87.5-316.8 0c-12.5-12.5-12.5-32.8 0-45.3s32.8-12.5 45.3 0c62.5 62.5 163.8 62.5 226.3 0s62.5-163.8 0-226.3s-163.8-62.5-226.3 0L125.7 160z"/></svg></button>
                            <button onclick="deleteFile('<?=$video['path']?>')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M135.2 17.7L128 32 32 32C14.3 32 0 46.3 0 64S14.3 96 32 96l384 0c17.7 0 32-14.3 32-32s-14.3-32-32-32l-96 0-7.2-14.3C307.4 6.8 296.3 0 284.2 0L163.8 0c-12.1 0-23.2 6.8-28.6 17.7zM416 128L32 128 53.2 467c1.6 25.3 22.6 45 47.9 45l245.8 0c25.3 0 46.3-19.7 47.9-45L416 128z"/></svg></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?= printDiskusage("archive") ?>

        <?php } ?>

        <div class="footer">
            <a href="<?=$baseurl?>" style="text-decoration: underline; color: #aaa;">twitchrecorder</a>
            -
            <a href="<?=$baseurl?>/archive" style="text-decoration: underline; color: #aaa;">archive</a>
            - by
            <a href="https://github.com/ThirtySix361/twitchrecorder" style="text-decoration: underline; color: #aaa;">thirtysix</a>
        </div>

    </body>
</html>