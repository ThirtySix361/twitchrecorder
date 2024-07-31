<?php

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $baseurl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $baseurl = strtok($baseurl, '?');
    $baseurl = rtrim($baseurl, '/');

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
            $return[$ext]["file"] = "file not found";
            $return[$ext]["status"] = "failed";
        }
        return $return;
    }

    if (isset($_GET['delete'])) {
        $file = urldecode($_GET['delete']);
        $file = removeExtensions($file);
        $return = [];
        if (strpos(__DIR__.$file, __DIR__.'/archive') !== false) {
            $response1 = removeFile(__DIR__.$file.'.mp4');
            $response2 = removeFile(__DIR__.$file.'.log');
            $return["response"] = array_merge($response1, $response2);
        } else {
            $return["response"]["attempt"] = "remove";
            $return["response"]["file"] = "file not found";
            $return["response"]["status"] = "failed";
        }
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json');
        echo json_encode($return);
        exit();
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

    $subDirs = getSubDirs(__DIR__ . '/archive');

    $selectedDirs = isset($_GET['dirs']) ? explode(',', $_GET['dirs']) : $subDirs;
    $selectedDirs = array_intersect($selectedDirs, $subDirs);

    function getFilteredFiles($dir, $selectedDirs) {
        $files = [];
        $items = new DirectoryIterator($dir);
        foreach ($items as $item) {
            if ($item->isFile() && $item->getExtension() === 'mp4') {
                $files[] = [
                    'path' => $item->getPathname(),
                    'date' => $item->getMTime(),
                    'name' => $item->getFilename(),
                    'size' => $item->getSize()
                ];
            } elseif ($item->isDir() && in_array($item->getFilename(), $selectedDirs)) {
                $files = array_merge($files, getFilteredFiles($item->getPathname(), $selectedDirs));
            }
        }
        return $files;
    }

    $videoFiles = getFilteredFiles(__DIR__ . '/archive', $selectedDirs);

    usort($videoFiles, function($a, $b) {
        return strcmp($b['name'], $a['name']);
    });
?>
<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="shortcut icon" type="image/x-icon" href="./favicon.ico">
        <title>Archive</title>
        <style>
            * {
                box-sizing: border-box;
            }
            body {
                margin: 0 auto;
                font-family: Arial, sans-serif;
                color: #999;
                background-color: #333;
            }
            .video-tile {
                display: flex;
                justify-content: center;
                align-items: center;
                flex-direction: column;
                height: 100vh;
                border: 0px solid #666;
                border-radius: 10px;
                text-align: center;
            }
            .video-name {
                font-size: 14px;
                word-break: break-word;
                margin-bottom: 10px;
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
            .chat {
                width: 25vw;
                height: 75vh;
                padding: 15px;
                text-align: left;
                background-color: #555;
                overflow-y: auto;
                line-height: 1.5em;
                word-wrap: break-word;
            }
            .chat .chatmessage .time {
                font-size: 0.6em;
                margin-right: 5px;
            }
            .chat .chatmessage .name {
                font-size: 1.0em;
                margin-right: 5px;
                color: #333;
                font-weight: bold;
            }
            .chat .chatmessage .msg {
                font-size: 1.0em;
                margin-right: 5px;
            }
            .options {
                display: flex;
                justify-content: center;
                margin-top: 10px;
                gap: 10px;
            }
            .options button {
                padding: 5px 10px;
                cursor: pointer;
                background-color: #555;
                color: #999;
                border: 0;
                border-radius: 3px;
            }
            .navigation {
                z-index: 9999;
                position: fixed;
                right: 25px;
                top: 50%;
                transform: translateY(-50%);
                display: flex;
                flex-direction: column;
                align-items: center;
            }
            .navigation button {
                padding: 5px 10px;
                margin: 5px 0;
                cursor: pointer;
                background-color: #555;
                color: #999;
                border: 0;
                border-radius: 3px;
            }
            .navigation button:hover, .options button:hover {
                background-color: #666;
            }
            .counter {
                margin: 10px 0;
            }
            .subnav button {
                margin: 6px;
                padding: 5px 10px;
                cursor: pointer;
                background-color: #555;
                color: #999;
                border: 0;
                border-radius: 3px;
            }
            .subnav button.active {
                background-color: #666;
            }

            @media only screen and (max-width: 1200px) {

                .navigation {
                    display: none;
                }
                .video-chat-wrapper {
                    flex-direction: column;
                }
                .videowrapper {
                    width: 90vw;
                    height: 30vh;
                }
                .chat {
                    width: 90vw;
                    height: 30vh;
                    line-height: 1.0em;
                }
                .chat .chatmessage .time {
                    font-size: 0.4em;
                    margin-right: 5px;
                }
                .chat .chatmessage .name {
                    font-size: 0.6em;
                    margin-right: 5px;
                    color: #333;
                }
                .chat .chatmessage .msg {
                    font-size: 0.6em;
                    margin-right: 5px;
                }

            }
        </style>
        <script>
            let currentIndex = 0;
            let videoTiles = [];

            document.addEventListener("DOMContentLoaded", () => {

                videoTiles = document.querySelectorAll('.video-tile');

                updateCounter();
                window.addEventListener('scroll', updateCounter);

                document.querySelector('#btnUp').addEventListener('click', () => scrollToVideo(currentIndex - 1));
                document.querySelector('#btnDown').addEventListener('click', () => scrollToVideo(currentIndex + 1));

                window.addEventListener('scroll', updateCounter);

                document.querySelectorAll('video').forEach(video => {
                    loadVideoTime(video);

                    const saveTime = () => saveVideoTime(video);
                    const updateChat = () => updateChatWindow(video);

                    video.addEventListener('play', () => {
                        fetchChatData(video.src)
                        video.addEventListener('timeupdate', saveTime)
                        video.addEventListener('timeupdate', updateChat)
                    });
                    video.addEventListener('pause', () => video.removeEventListener('timeupdate', saveTime));
                    video.addEventListener('ended', () => video.removeEventListener('timeupdate', saveTime));
                    video.addEventListener('pause', () => video.removeEventListener('timeupdate', updateChat));
                    video.addEventListener('ended', () => video.removeEventListener('timeupdate', updateChat));
                });

                subNavButtons = document.querySelectorAll('.subnav button');

                subNavButtons.forEach(button => {
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
                subNavButtons.forEach(button => {
                    const dir = button.getAttribute('data-dir');
                    if (selectedDirs.includes(dir)) {
                        button.classList.add('active');
                    }
                });
            });

            function fetchChatData(videoSrc) {
                var logFilePath = videoSrc.replace('.mp4', '.log')
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

            function formatReply(chatMessage) {
                const regex = /^([^:]+):\s*(.*)$/;
                const match = chatMessage.match(regex);

                if (match) {
                    return `<span class="name">${match[1]}</span>${match[2]}`;
                }
            }

            function formatChatMessage(chatMessage) {
                const normalMessageRegex = /^(\d{2}:\d{2}:\d{2})\s+(.*?):\s*(.*)$/;
                const replyingToMessageRegex = /^(\d{2}:\d{2}:\d{2})\s+Replying to\s+(@[\w]+):\s*(.*)/s;

                let match = chatMessage.match(normalMessageRegex);
                if (match) {
                    const [_, time, name, msg] = match;
                    return `<div class="chatmessage"><span class="time">${time}</span><span class="name">${name}</span><span class="msg">${msg}</span></div>`;
                }

                match = chatMessage.match(replyingToMessageRegex);
                if (match) {
                    const [_, time, repliedTo, msg] = match;
                    return `<div class="chatmessage"><span class="time">${time}</span><span class="name">Replying to ${repliedTo}</span><span class="msg">${msg}</span></div>`;
                }

                return `<div class="chatmessage"><span class="msg">${chatMessage}</span></div>`;
            }


            function processReplies(lines) {
                let processedLines = [];
                let tempLine = '';
                let isReplyingTo = false;

                for (const line of lines) {
                    if (line.includes('Replying to')) {
                        tempLine = line;
                        isReplyingTo = true;
                    } else if (isReplyingTo && line.trim() === '') {
                        continue;
                    } else if (isReplyingTo) {
                        formattedline = formatReply(line);
                        processedLines.push(`${tempLine}\n${formattedline}`);
                        tempLine = '';
                        isReplyingTo = false;
                    } else {
                        processedLines.push(line);
                    }
                }

                return processedLines;
            }

            function updateChatWindow(video) {
                const chatElement = video.parentNode.parentNode.querySelector(".chat");
                const currentTime = video.currentTime;

                const lines = chatMessages.split('\n');

                const processedLines = processReplies(lines);

                const filteredLines = processedLines.filter(line => {
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

            function scrollToVideo(index) {
                currentIndex = Math.max(0, Math.min(index, videoTiles.length - 1));
                videoTiles[currentIndex].scrollIntoView({ behavior: 'smooth', block: 'center' });
                updateCounter();
            }

            function updateCounter() {
                const viewportMiddle = window.innerHeight / 2;
                let closestIndex = 0;
                let minDistance = Infinity;

                videoTiles.forEach((tile, index) => {
                    const rect = tile.getBoundingClientRect();
                    const elementMiddle = rect.top + rect.height / 2;
                    const distance = Math.abs(viewportMiddle - elementMiddle);

                    if (distance < minDistance) {
                        minDistance = distance;
                        closestIndex = index;
                    }
                });

                currentIndex = closestIndex;
                document.querySelector('#counter').textContent = `${currentIndex + 1} / ${videoTiles.length}`;
            }

            function saveVideoTime(video) {
                localStorage.setItem(video.src, video.currentTime);
            }

            function loadVideoTime(video) {
                const time = localStorage.getItem(video.src);
                if (time !== null) {
                    video.currentTime = time;
                }
            }

            function deleteFile(filePath) {
                if (confirm('Are you sure you want to delete this file?')) {
                    baseurl = (location.origin + location.pathname).replace(/\/$/, '');
                    storage = baseurl+filePath;
                    url = baseurl+"?delete="+filePath;
                    localStorage.removeItem(storage);
                    fetch(url)
                        .then(response => response.json())
                        .then(data => { console.log(data) });
                    setTimeout(() => { location.reload(); }, 500);
                }
            }
        </script>
    </head>
    <body>
        <div id="header" style="z-index: 9999; position: fixed; top: 0; left:0; right: 0; text-align: center; background-color: #444; text-align: center;">
            <div class="subnav">
                <?php foreach ($subDirs as $dir): ?>
                    <button data-dir="<?= htmlspecialchars($dir) ?>"><?= htmlspecialchars($dir) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="navigation">
            <button id="btnUp">▲</button>
            <div id="counter" class="counter">1 / <?php echo count($videoFiles); ?></div>
            <button id="btnDown">▼</button>
        </div>

        <?php foreach ($videoFiles as $video): ?>
            <div class="video-tile">
                <div class="video-name"><?= htmlspecialchars($video['name']) ?> (<?= number_format($video['size'] / (1024 * 1024 * 1024), 2) ?> GB)</div>
                <div class="video-chat-wrapper">
                    <div class="videowrapper"><video class="video" src="<?=$baseurl?><?= str_replace(__DIR__, '', $video['path']) ?>" controls></video></div>
                    <div class="chat"></div>
                </div>
                <div class="options">
                    <button onclick="deleteFile('<?=str_replace(__DIR__, '', $video['path'])?>')">Delete</button>
                </div>
            </div>
        <?php endforeach; ?>

        <div id="footer" style="z-index: 9999; padding: 10px; position: fixed; bottom: 0; left:0; right: 0; text-align: center; background-color: #444">
            twitchrecorder
            <a href="<?=$baseurl?>/archive" style="text-decoration: underline; color: #aaa;">archive</a>
            by
            <a href="https://github.com/ThirtySix361/twitchrecorder" style="text-decoration: underline; color: #aaa;">thirtysix</a>
        </div>
    </body>
</html>