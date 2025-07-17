<?php

    # --------------------------------------------------------------------------------- #

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 || $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ? "https://" : "http://";
    $baseurl = rtrim(strtok($protocol . $_SERVER['HTTP_HOST'], '?'), '/');

    # --------------------------------------------------------------------------------- #

?>

<!DOCTYPE html>
<html>

    <head>
        <title>Twitchrecorder</title>
        <meta charset="utf-8" />
        <meta id="meta" name="viewport" content="width=device-width, initial-scale=1.0" />
        <link rel="icon" type="image/x-icon" href="favicon.ico" />
        <link rel="stylesheet" type="text/css" href="css/style.css" />
        <link rel="stylesheet" href="lib/fontawesome/css/all.min.css" />
    </head>

    <body class="init">
        <div id="headerwrapper">
            <div id="header">
                <div class="left"><a href="<?=$baseurl?>"><i class="fa-solid fa-video"></i><span>twitchrecorder</span></a><div id="diskusage">&nbsp;</div></div>
                <div class="toggle" onclick="console.log(this.nextElementSibling.classList.toggle('show'))">
                    <a><i class="fa-solid fa-users"></i><span class="hint">users</span></a>
                </div>
                <div class="mid"></div>
                <div class="right">
                    <a onclick="event.preventDefault(); addModal();" id="newTask"><i class="fa-solid fa-plus"></i><span class="hint">new task</span></a>
                    <a onclick="event.preventDefault(); themeModal();" id="themeSelect"><i class="fa-solid fa-palette"></i><span class="hint">theme</span></a>
                    <a onclick="event.preventDefault(); toggleVideolistening();" id="groupToggle"><i class="fa-solid fa-bars-staggered"></i><span class="hint">grouping</span></a>
                    <a onclick="event.preventDefault(); toggleMultiSelect();" id="toggleMultiSelect"><i class="fa-solid fa-check"></i><span class="hint">multi select</span></a>
                    <a onclick="event.preventDefault(); toggleDescription();" id="descriptionToggle"><i class="fa-solid fa-lightbulb"></i><span class="hint">hints</span></a>
                </div>
            </div>
        </div>
        <div id="contentwrapper">
            <div id="content"></div>
        </div>
        <div id="footerwrapper">
            <div id="footer">
                <a href="https://github.com/ThirtySix361/twitchrecorder"><i class="fa-brands fa-github"></i><span class="underlined">twitchrecorder</span></a>
                <a href="<?=$baseurl?>/archive"><i class="fa-solid fa-folder"></i><span class="underlined">archive</span></a>
                <a href="mailto:dev@36ip.de?subject=twitchrecorder"><i class="fa-solid fa-envelope"></i><span class="underlined">by ThirtySix</span></a>
            </div>
        </div>
    </body>

    <script src="lib/hls.js"></script>
    <script src="js/index.js"></script>

</html>
