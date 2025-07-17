<?php

    # --------------------------------------------------------------------------------- #

    function execute($cmd) {
        $response = shell_exec($cmd);
        return $response;
    }

    function killPidTree($pid) {
        execute("kill -KILL $(pstree -p $pid | grep -oP '\(\\K[0-9]+' )");
    }

    function isValidTwitchChannel($name) {
        if (strlen($name) < 3 || strlen($name) > 25) { return false; }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) { return false; }
        if (strpos($name, '_') === 0) { return false; }
        return true;
    }

    function getSanitized($key, $type = 'string', $default = null) {
        if (!isset($_GET[$key])) { return $default; }

        $value = urldecode($_GET[$key]);

        if ($type === 'channel') {
            if (isValidTwitchChannel($value)) { return strtolower($value); }
            return $default;
        }

        if ($type === 'mode') {
            if ($value === 'include' || $value === 'exclude') { return $value; }
            return $default;
        }

        if ($type === 'action') {
            if ($value === 'startRecordingTask' || $value === 'stopRecordingTask') { return $value; }
            return $default;
        }

        if ($type === 'array') {
            $list = explode(',', $value);
            return array_filter($list, 'isValidTwitchChannel');
        }

        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    function startRecordingTask($channel) {
        $basedir = dirname(__DIR__);
        $pidFilePath = $basedir."/archive/$channel.pid";
        if (!isValidTwitchChannel($channel)) { return "invalid twitch channelname"; }
        if (file_exists($pidFilePath)) { return "task is already running"; }
        $pid = execute("cd $basedir; bash record.sh $channel >> /tmp/$channel.log 2>&1 & echo $!");
        file_put_contents($pidFilePath, $pid);
        return "task started";
    }

    function stopRecordingTask($channel) {
        $basedir = dirname(__DIR__);
        $pidFilePath = $basedir."/archive/$channel.pid";
        $profilePath = "/tmp/$channel-profile";
        if (!isValidTwitchChannel($channel)) { return "invalid twitch channelname"; }
        if (!file_exists($pidFilePath)) { return "task not found"; }
        $pid = trim(file_get_contents($pidFilePath));
        unlink($pidFilePath);
        system("rm -rf $profilePath");
        killPidTree($pid);
        return "task stopped";
    }

    function escapeString($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    function getPids($channel) {
        if (!$channel) { $channel = '*'; }
        $dir = dirname(__DIR__).'/archive';
        $files = glob(rtrim($dir, '/\\') . '/'.$channel.'.pid');
        $names = array_map(function($file) {
            return pathinfo($file, PATHINFO_FILENAME);
        }, $files);
        sort($names);
        return $names;
    }

    function getDiskusage($path_to_archive) {
        if (is_dir($path_to_archive)) {
            $totalSpace = disk_total_space($path_to_archive);
            $freeSpace = disk_free_space($path_to_archive);
            $usedSpace = $totalSpace - $freeSpace;
            return round($usedSpace / 1073741824, 0) . " / " . round($totalSpace / 1073741824, 0) . " GB";
        } else {
            return "cant get diskUsage";
        }
    }

    # --------------------------------------------------------------------------------- #

    function delete($request) {
        $requested_file = urldecode($request);
        $file = removeExtensions($requested_file);
        $dir = dirname(__DIR__);
        $return = [];

        if (strpos($dir.$file, $dir.'/archive') !== false) {
            $response1 = removeFile($dir.$file.'.mp4');
            $response2 = removeFile($dir.$file.'.log');
            $response3 = removeFile($dir.$file.'.png');
            $response4 = removeFile($dir.$file.'.raw');
            $response5 = removeFile($dir.$file.'.m3u8');
            $response6 = removeFile($dir.$file.'.m4s');
            $return["response"] = array_merge($response1, $response2, $response3, $response4, $response5, $response6);
        } else {
            $return["response"]["attempt"] = "remove";
            $return["response"]["file"] = "$requested_file";
            $return["response"]["status"] = "failed";
        }

        return $return["response"];
    }

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

    function prettyName($name, $result) {
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
        if ($result == "name") {
            return "$streamerName";
        } else if ($result == "date") {
            return "$formattedDate";
        } else if ($result == "time") {
            return "$formattedTime";
        } else {
            return "$streamerName $formattedDate $formattedTime";
        }
    }

    function getSize($item) {
        if ($item->getExtension() == "m3u8") {
            $m4sPath = preg_replace('/\.m3u8$/', '.m4s', $item->getPathname());
            if (file_exists($m4sPath)) {
                return number_format(filesize($m4sPath) / (1024 * 1024 * 1024), 2);
            }
        } else {
            return number_format($item->getSize() / (1024 * 1024 * 1024), 2);
        }
    }

    function getVideo($request, $baseurl) {
        $requested_file = urldecode($request);
        $dir = dirname(__DIR__);
        $archiveDir = realpath($dir . '/archive');

        $ext = strtolower(pathinfo($requested_file, PATHINFO_EXTENSION));
        if (!in_array($ext, ['m3u8', 'mp4'])) { output("invalid extension", false); }

        $absolute_path = $dir . $requested_file;

        if (!file_exists($absolute_path)) {
            if ($ext === 'm3u8') {
                $info = pathinfo($requested_file);
                $fallback_path = $dir . dirname($requested_file) . '/' . $info['filename'] . '.mp4';
                if (file_exists($fallback_path)) {
                    $requested_file = dirname($requested_file) . '/' . $info['filename'] . '.mp4';
                    $absolute_path = $fallback_path;
                } else {
                    output("file not found", false);
                }
            } else {
                output("file not found", false);
            }
        }

        $fullpath = realpath($absolute_path);
        if (!$fullpath || strpos($fullpath, $archiveDir) !== 0 || !is_file($fullpath)) {
            output("invalid path", false);
        }

        $info = pathinfo($fullpath);
        $filename = $info['filename'];
        $base = rtrim(dirname($requested_file), '/') . '/' . $filename;

        $entry = [
            'path' => $requested_file,
            'filename' => $filename,
            'name' => prettyName($info['basename'], "name"),
            'date' => prettyName($info['basename'], "date"),
            'time' => prettyName($info['basename'], "time"),
            'timestamp' => DateTime::createFromFormat(
                'd.m.Y H:i',
                prettyName($info['basename'], "date") . ' ' . prettyName($info['basename'], "time")
            )?->getTimestamp(),
            'size' => getSize(new SplFileInfo($fullpath)),
            'url_noext' => $baseurl . $base,
            'url_video' => $baseurl . $requested_file,
            'url_log' => $baseurl . $base . '.log',
            'url_png' => $baseurl . $base . '.png'
        ];

        return $entry;
    }

    function getFilteredFiles($dir, $filter, $baseurl, $mode = 'exclude') {
        $baseurl = $baseurl . "/archive";
        $result = [];
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($items as $item) {
            if ($item->isFile() && in_array($item->getExtension(), ['mp4', 'm3u8'])) {
                $user = basename($item->getPath());
                if ($filter !== []) {
                    if ($mode === 'exclude' && in_array($user, $filter)) {
                        continue;
                    }
                    if ($mode === 'include' && !in_array($user, $filter)) {
                        continue;
                    }
                }

                $basePath = str_replace($dir, '', $item->getPathname());
                $basePath = str_replace('\\', '/', $basePath);
                $baseUrlPath = $baseurl . $basePath;

                $baseName = pathinfo($basePath, PATHINFO_DIRNAME) . '/' . pathinfo($basePath, PATHINFO_FILENAME);

                $entry = [
                    'path' => str_replace(dirname(__DIR__), '', $item->getPathname()),
                    'timestamp' => (DateTime::createFromFormat('d.m.Y H:i', prettyName($item->getFilename(), "date") . ' ' . prettyName($item->getFilename(), "time")))->getTimestamp(),
                    'size' => getSize($item),
                    'filename' => pathinfo($item->getFilename(), PATHINFO_FILENAME),
                    'name' => prettyName($item->getFilename(), "name"),
                    'date' => prettyName($item->getFilename(), "date"),
                    'time' => prettyName($item->getFilename(), "time"),
                    'url_noext' => $baseurl . $baseName,
                    'url_video' => $baseUrlPath,
                    'url_log' => $baseurl . $baseName . '.log',
                    'url_png' => $baseurl . $baseName . '.png'
                ];
                $result[] = $entry;
            }
        }
        $grouped = [];
        foreach ($result as $entry) {
            $grouped[$entry['name']][] = $entry;
        }
        foreach ($grouped as &$group) {
            usort($group, function($a, $b) {
                return $b['timestamp'] - $a['timestamp'];
            });
        }
        ksort($grouped);
        return $grouped;
    }

    # --------------------------------------------------------------------------------- #

    function isAssoc($arr) {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    function output($msg, $status = true) {
        header("Access-Control-Allow-Origin: *");
        header("Content-Type: application/json");
        http_response_code($status ? 200 : 400);

        if (is_array($msg)) {
            $datatype = (count($msg) === 0) ? 'array' : (isAssoc($msg) ? 'object' : 'array');
        } else {
            $datatype = gettype($msg);
        }

        echo json_encode([
            'status' => $status,
            'statuscode' => http_response_code(),
            'datatype' => $datatype,
            'data' => $msg
        ]);
        exit;
    }

    # --------------------------------------------------------------------------------- #

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 || $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ? "https://" : "http://";
    $baseurl = rtrim(strtok($protocol . $_SERVER['HTTP_HOST'], '?'), '/');
    $basedir = dirname(__DIR__) . '/archive';

    if (isset($_GET['getVideos'])) {
        $filter = getSanitized('getVideos', 'array', []);
        $mode = getSanitized('mode', 'mode', 'include');
        $videoFiles = getFilteredFiles($basedir, $filter, $baseurl, $mode);
        output($videoFiles);
    }

    if (isset($_GET['getVideo'])) {
        $response = getVideo($_GET['getVideo'], $baseurl);
        output($response);
    }

    if (isset($_GET['getChannels'])) {
        $dir = dirname(__DIR__) . '/archive';
        $result = [];
        $items = new DirectoryIterator($dir);
        foreach ($items as $item) {
            if ($item->isDir() && !$item->isDot()) {
                $result[] = $item->getFilename();
            }
        }
        $files = glob(rtrim($dir, '/\\') . '/*.pid');
        foreach ($files as $file) {
            $result[] = pathinfo($file, PATHINFO_FILENAME);
        }
        $unique = array_unique($result);
        sort($unique);
        output($unique);
    }

    if (isset($_GET['getLogs'])) {
        $channel = getSanitized('channel', 'channel', 'error');
        if ($channel == 'error') { output("invalid channel"); }
        $taskLogPath = "/tmp/$channel.log";
        $ffmpegLogPath = "/tmp/$channel-ffmpeg.log";
        $fixLogPath = "/tmp/$channel-fix.log";
        $streamsLogPath = "/tmp/$channel-streams.log";
        $thumbnailLogPath = "/tmp/$channel-thumbnail.log";
        $logs = [
            "logtask" => $taskLogPath,
            "logffmpeg" => $ffmpegLogPath,
            "logfix" => $fixLogPath,
            "logstreams" => $streamsLogPath,
            "logthumbnail" => $thumbnailLogPath,
        ];
        $response = [];
        foreach ($logs as $key => $path) {
            $content = @file_get_contents($path);
            $response[$key] = $content !== false && trim($content) !== "" ? escapeString($content) : "log empty";
        }
        if (!$response) { output("bad request", false); }
        output($response);
    }

    if (isset($_GET['action']) && isset($_GET['channel'])) {
        $action = getSanitized('action', 'action', 'error');
        $channel = getSanitized('channel', 'channel', 'error');
        if ($channel == 'error') { output("invalid twitch channelname"); }
        if ($action !== '' && $channel !== '') {
            if ($action === "startRecordingTask") { $response = startRecordingTask($channel); }
            if ($action === "stopRecordingTask") { $response = stopRecordingTask($channel); }
            if (!$response) { output("empty response..?!"); }
            output($response);
        }
    }

    if (isset($_GET['getPids'])) {
        $channel = getSanitized('channel', 'channel', false);
        $response = getPids($channel);
        output($response);
    }

    if (isset($_GET['getDiskusage'])) {
        $response = getDiskusage($basedir);
        output($response);
    }

    if (isset($_GET['deleteVideo'])) {
        $response = delete($_GET['deleteVideo']);
        output($response);
    }

    output("bad request", false);

?>
