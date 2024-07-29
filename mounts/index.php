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
        $items = new DirectoryIterator($dir);
        foreach ($items as $item) {
            if ($item->isDot()) continue;
            $path = $item->getPathname();
            if ($item->isFile() && $item->getExtension() === 'mp4') {
                $files[] = [
                    'path' => $path,
                    'date' => $item->getMTime(),
                    'name' => $item->getFilename(),
                    'size' => $item->getSize()
                ];
            } elseif ($item->isDir()) {
                $files = array_merge($files, getFiles($path));
            }
        }
        return $files;
    }

    $videoFiles = getFiles(__DIR__);
    usort($videoFiles, fn($a, $b) => strcmp($b['name'], $a['name']));
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
            background-color: #555;
            color: #999;
            border: 0;
            border-radius: 3px;
        }
        .navigation {
            position: fixed;
            right: 50px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .navigation button {
            padding: 10px 20px;
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
    </style>
    <script>
        let currentIndex = 0;
        let videoTiles = [];

        document.addEventListener("DOMContentLoaded", () => {
            videoTiles = document.querySelectorAll('.video-tile');
            updateCounter();

            document.querySelector('#btnUp').addEventListener('click', () => scrollToVideo(currentIndex - 1));
            document.querySelector('#btnDown').addEventListener('click', () => scrollToVideo(currentIndex + 1));

            window.addEventListener('scroll', updateCounter);

            document.querySelectorAll('video').forEach(video => {
                loadVideoTime(video);

                const saveTime = () => saveVideoTime(video);

                video.addEventListener('play', () => video.addEventListener('timeupdate', saveTime));
                video.addEventListener('pause', () => video.removeEventListener('timeupdate', saveTime));
                video.addEventListener('ended', () => video.removeEventListener('timeupdate', saveTime));
            });
        });

        function scrollToVideo(index) {
            currentIndex = Math.max(0, Math.min(index, videoTiles.length - 1));
            videoTiles[currentIndex].scrollIntoView({ behavior: 'smooth', block: 'center' });
            updateCounter();
        }

        function updateCounter() {
            videoTiles.forEach((tile, index) => {
                const rect = tile.getBoundingClientRect();
                if (rect.top >= 0 && rect.bottom <= window.innerHeight) {
                    currentIndex = index;
                }
            });
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

        function deleteFile(filePath, videourl) {
            if (confirm('Are you sure you want to delete this file?')) {
                const video = document.querySelector(`video[src="${videourl}"]`);
                localStorage.removeItem(video.src);
                fetch(`?delete=${encodeURIComponent(filePath)}`)
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
    <div id="header" style="padding: 25px; position: fixed; top: 0; left:0; right: 0; text-align: center; background-color: #444">
        <a href="/archive" style="text-decoration: underline; color: #aaa;">archive</a>
    </div>

    <div class="navigation">
        <button id="btnUp">▲</button>
        <div id="counter" class="counter">1 / <?php echo count($videoFiles); ?></div>
        <button id="btnDown">▼</button>
    </div>

    <?php foreach ($videoFiles as $video): ?>
        <div class="video-tile">
            <div class="video-name"><?= htmlspecialchars($video['name']) ?> <br> (<?= number_format($video['size'] / (1024 * 1024 * 1024), 2) ?> GB)</div>
            <video class="video-thumb" src="<?= str_replace(__DIR__, '', $video['path']) ?>" controls></video>
            <div class="options">
                <button onclick="deleteFile('<?= $video['path'] ?>', '<?= str_replace(__DIR__, '', $video['path']) ?>')">Delete</button>
            </div>
        </div>
    <?php endforeach; ?>

    <div id="footer" style="padding: 25px; position: fixed; bottom: 0; left:0; right: 0; text-align: center; background-color: #444">
        by <a href="https://github.com/ThirtySix361/twitchrecorder" style="text-decoration: underline; color: #aaa;">thirtysix</a>
    </div>
</body>
</html>
