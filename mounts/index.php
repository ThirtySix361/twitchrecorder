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
            $return["response"] = array_merge($response1, $response2, $response3, $response4);
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

    function getFilteredFiles($baseurl, $dir, $selectedDirs) {
        $files = [];
        $items = new DirectoryIterator($dir);
        foreach ($items as $item) {
            if ($item->isFile() && $item->getExtension() === 'mp4') {
                $files[] = [
                    'path' => str_replace(__DIR__, '', $item->getPathname()),
                    'date' => $item->getMTime(),
                    'name' => $item->getFilename(),
                    'prettyName' => prettyName($item->getFilename()),
                    'size' => number_format($item->getSize() / (1024 * 1024 * 1024), 2),
                    'log' => $baseurl.str_replace(__DIR__, '', str_replace('.mp4', '.log', $item->getPathname())),
                    'png' => $baseurl.str_replace(__DIR__, '', str_replace('.mp4', '.png', $item->getPathname())),
                    'mp4' => $baseurl.str_replace(__DIR__, '', $item->getPathname())
                ];
            } elseif ($item->isDir() && in_array($item->getFilename(), $selectedDirs)) {
                $files = array_merge($files, getFilteredFiles($baseurl, $item->getPathname(), $selectedDirs));
            }
        }
        return $files;
    }

    $videoFiles = getFilteredFiles($baseurl, __DIR__ . '/archive', $selectedDirs);

    usort($videoFiles, function($a, $b) {
        return strcmp(explode('_', $b['name'], 2)[1], explode('_', $a['name'], 2)[1]);
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
            svg {
                filter: invert(70%) sepia(0%) saturate(11%) hue-rotate(82deg) brightness(89%) contrast(85%);
                width: auto;
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
            .contentwrapper {
                display: flex;
                justify-content: center;
                align-items: center;
                flex: 1;
            }
            .video-name {
                font-size: 14px;
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
                font-size: 0.6em;
                margin-right: 5px;
            }
            .chat .chatmessage .msg {
                font-size: 1.0em;
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
            .video-options {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 10px;
            }
            .video-options button {
                display: flex;
                justify-content: center;
                min-width: 75px;
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
            .navigation button:hover, .video-options button:hover {
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
                background-color: #777;
            }
            .subnav button:hover {
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
            document.addEventListener("DOMContentLoaded", () => {

                video = document.querySelector("video");
                if (video) {
                    loadVideoTime(video);

                    const saveTime = () => saveVideoTime(video);
                    const updateChat = () => updateChatWindow(video);

                    video.addEventListener('play', () => {
                        fetchChatData(video)
                        video.addEventListener('timeupdate', saveTime)
                        video.addEventListener('timeupdate', updateChat)
                    });
                    video.addEventListener('pause', () => video.removeEventListener('timeupdate', saveTime));
                    video.addEventListener('ended', () => video.removeEventListener('timeupdate', saveTime));
                    video.addEventListener('pause', () => video.removeEventListener('timeupdate', updateChat));
                    video.addEventListener('ended', () => video.removeEventListener('timeupdate', updateChat));
                }

                images = document.querySelectorAll("img");

                if (images) {
                    images.forEach(function(image) {
                        let videosrc = image.src.replace(".png", ".mp4")
                        if (localStorage.getItem(videosrc)) {
                            target = image.parentNode.parentNode.parentNode;
                            target.style.filter = "brightness(0.5)";
                            target.style.transform = "scale(0.975)";
                        }
                    })
                }

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

            function fetchChatData(video) {
                var logFilePath = video.src.replace('.mp4', '.log')
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

        <?php if (isset($_GET['video']) && is_file(__DIR__.$_GET['video'])) { ?>

            <?php
                $videopath = $_GET['video'];
                $videourl = $baseurl.$_GET['video'];
                $videosize = number_format(filesize(__DIR__.$videopath) / (1024 * 1024 * 1024), 2);
                $videoname = basename($videopath);
                $videoprettyname = prettyName($videoname);
            ?>

            <style>
                .content {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                }
            </style>

            <div id="header" style="z-index: 9999; position: sticky; top: 0; left:0; right: 0; text-align: center; background-color: #444; text-align: center; padding: 10px;">
                <div class="video-options">
                    <button onclick="location.href='<?=$baseurl?>'"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path fill="#000000" d="M177.5 414c-8.8 3.8-19 2-26-4.6l-144-136C2.7 268.9 0 262.6 0 256s2.7-12.9 7.5-17.4l144-136c7-6.6 17.2-8.4 26-4.6s14.5 12.5 14.5 22l0 72 288 0c17.7 0 32 14.3 32 32l0 64c0 17.7-14.3 32-32 32l-288 0 0 72c0 9.6-5.7 18.2-14.5 22z"/></svg></button>
                    <?=$videoprettyname?> (<?= $videosize ?> GB)
                    <button onclick="deleteFile('<?=$videopath?>')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M135.2 17.7L128 32 32 32C14.3 32 0 46.3 0 64S14.3 96 32 96l384 0c17.7 0 32-14.3 32-32s-14.3-32-32-32l-96 0-7.2-14.3C307.4 6.8 296.3 0 284.2 0L163.8 0c-12.1 0-23.2 6.8-28.6 17.7zM416 128L32 128 53.2 467c1.6 25.3 22.6 45 47.9 45l245.8 0c25.3 0 46.3-19.7 47.9-45L416 128z"/></svg></button>
                </div>
            </div>

            <div class="contentwrapper">
                <div class="content">
                    <div class="video-chat-wrapper">
                        <div class="videowrapper">
                            <video class="video" src="<?=$videourl?>" controls></video></div>
                        <div class="chatwrapper">
                            <div class="chat"></div>
                        </div>
                    </div>
                </div>
            </div>

        <?php } else { ?>

            <style>
                .contentwrapper {
                    display: block;
                }
                .content {
                    margin: 15px 0px;
                    display: flex;
                    justify-content: center;
                    flex-flow: row wrap;
                    gap: 15px;
                }
                .preview-wrapper {
                    width: auto;
                    text-align: center;
                    background-color: #444;
                    border-radius: 5px;
                }
                .video-name {
                    margin: 10px;
                    word-break: break-word;
                }
                .video-preview {
                    margin: 10px;
                }
                .video-preview a {
                    display: block;
                }
                .video-options {
                    margin: 10px;
                }
                .video-options button {
                    width: 100%;
                }
                img {
                    display: block;
                    width: 192px;
                    height: 108px;
                    border-radius: 5px;
                }

            </style>

            <div id="header" style="z-index: 9999; position: sticky; top: 0; left:0; right: 0; text-align: center; background-color: #444; text-align: center;">
                <div class="subnav">
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
                            <a href="?video=<?=$video['path']?>"><img class="preview-img" src="<?=$video['png']?>"></img></a>
                        </div>
                        <div class="video-name"><?= str_replace(' ', '<br>', $video['prettyName']) ?><br>(<?= $video['size'] ?> GB)</div>
                        <div class="video-options">
                            <button onclick="deleteFile('<?=$video['path']?>')"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><!--!Font Awesome Free 6.7.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M135.2 17.7L128 32 32 32C14.3 32 0 46.3 0 64S14.3 96 32 96l384 0c17.7 0 32-14.3 32-32s-14.3-32-32-32l-96 0-7.2-14.3C307.4 6.8 296.3 0 284.2 0L163.8 0c-12.1 0-23.2 6.8-28.6 17.7zM416 128L32 128 53.2 467c1.6 25.3 22.6 45 47.9 45l245.8 0c25.3 0 46.3-19.7 47.9-45L416 128z"/></svg></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php
                $path_to_archive = "archive";
                if (is_dir($path_to_archive)) {
                    $totalSpace = disk_total_space($path_to_archive);
                    $freeSpace = disk_free_space($path_to_archive);
                    $usedSpace = $totalSpace - $freeSpace;
                    echo "<div style='text-align: center; padding: 10px;'> " . round($usedSpace / 1073741824, 0) . " of " . round($totalSpace / 1073741824, 0) . " GB used</div>";
                } else {
                    echo "<div style='text-align: center; padding: 10px;'> archive path not found </div>";
                }
            ?>

        <?php } ?>

        <div id="footer" style="z-index: 9999; padding: 10px; position: sticky; bottom: 0; left:0; right: 0; text-align: center; background-color: #444">
            <a href="<?=$baseurl?>" style="text-decoration: underline; color: #aaa;">twitchrecorder</a>
            -
            <a href="<?=$baseurl?>/archive" style="text-decoration: underline; color: #aaa;">archive</a>
            - by
            <a href="https://github.com/ThirtySix361/twitchrecorder" style="text-decoration: underline; color: #aaa;">thirtysix</a>
        </div>

    </body>
</html>