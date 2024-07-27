<?php
    if (isset($_GET['delete'])) {
        $file = urldecode($_GET['delete']);
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: plain/text');
        if (file_exists($file)) {
            unlink($file);
            echo 'File has been deleted: ' . basename($file);
        } else {
            echo 'File not found.';
        }
        exit();
    }

    function getFiles($dir) {
        $files = [];
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item != '.' && $item != '..') {
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                if (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'mp4') {
                    $files[] = [
                        'path' => $path,
                        'date' => filemtime($path),
                        'name' => $item,
                        'size' => filesize($path)
                    ];
                } elseif (is_dir($path)) {
                    $files = array_merge($files, getFiles($path));
                }
            }
        }
        return $files;
    }

    $videoFiles = getFiles(__DIR__);
    usort($videoFiles, function($a, $b) {
        return strcmp($b['name'], $a['name']);
    });
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <link rel="shortcut icon" type="image/x-icon" href="./favicon.ico">
    <title>Archive</title>
    <style>
        body {
            margin: 0 auto;
            font-family: Arial, sans-serif;
            color: #999;
            background-color: #333;
        }
        .video-tile {
            margin: 10px;
            padding: 10px;
            margin-top: 135px;
            margin-bottom: 135px;
            border: 0px solid #666;
            border-radius: 10px;
            text-align: center;
        }
        .video-name {
            font-size: 14px;
            word-break: break-word;
        }
        .video-thumb {
            width: 66%;
            max-height: auto;
            object-fit: cover;
            margin-top: 10px;
            margin-bottom: 10px;
        }
        .options {
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        .options button {
            padding: 5px 10px;
            cursor: pointer;
            background-color: #999;
            border: 0;
            border-radius: 3px;
        }
    </style>
    <script>
        function openInNewTab(url) {
            window.open(url, '_blank');
        }

        function saveVideoTime(video) {
            localStorage.setItem(video.src, video.currentTime);
        }

        function removeVideoTime(video) {
            localStorage.removeItem(video.src);
        }

        function loadVideoTime(video) {
            const time = localStorage.getItem(video.src);
            if (time !== null) {
                video.currentTime = time;
            }
        }

        document.addEventListener("DOMContentLoaded", () => {
            document.querySelectorAll('video').forEach(video => {
                loadVideoTime(video);

                const saveTime = () => saveVideoTime(video);

                video.addEventListener('play', function() {
                    video.addEventListener('timeupdate', saveTime);
                });

                video.addEventListener('pause', function() {
                    video.removeEventListener('timeupdate', saveTime);
                });

                video.addEventListener('ended', function() {
                    video.removeEventListener('timeupdate', saveTime);
                });

            });
        });

        function deleteFile(filePath, videourl) {
            if (confirm('Are you sure, you want to delete this file?')) {
                video = document.querySelector(`video[src="${videourl}"]`)
                removeVideoTime(video)
                fetch(window.location.href + '?delete=' + encodeURIComponent(filePath))
                    .then(response => response.text())
                    .then(data => {
                        console.log(data);
                        location.reload();
                    });
            }
        }
    </script>
</head>
<body>

    <div id="header" style="padding: 25px; position: fixed; top: 0; left:0; right: 0; text-align: center; background-color: #444"><a href="/archive" style="text-decoration: underline; color: #aaa;">archive</a></div>

    <?php
        foreach ($videoFiles as $video) {
            $videoUrl = str_replace(__DIR__, '', $video['path']);
            $fileName = basename($video['path']);
            $fileSizeGB = number_format($video['size'] / (1024 * 1024 * 1024), 2);
            echo '<div class="video-tile">';
            echo '<div class="video-name">' . htmlspecialchars($fileName) . ' <br> ' . ' (' . $fileSizeGB . ' GB)</div>';
            echo '<video class="video-thumb" src="' . $videoUrl . '" controls></video>';
            echo '<div class="options">';
            echo '<button onclick="deleteFile(\'' . $video['path'] . '\', \'' . $videoUrl . '\')">Delete</button>';
            echo '</div>';
            echo '</div>';
        }
    ?>

    <div id="footer" style="padding: 25px; position: fixed; bottom: 0; left:0; right: 0; text-align: center; background-color: #444">by 36</div>

</body>
</html>
