<?php
// If you want SOME form of authentication, insert a SHA256 hash of your password, that has "ipbanner" added to it.
// e.g. If I wanted my password to be 'password', I'd type 'passwordipbanner' into the website.
// NOTE: Please use a randomly generated password
$password_hash = "";
$country_ban_path = "";
$manual_ban_path = "";
$auto_ban_path = "";

function lower_trimmed($str) {
    return strtolower(trim(rtrim($str)));
}

function cache_ip_countries() {
    if(is_dir("ips")) {
        return false;
    }

    echo "
    <h1>Caching IPs to separate files...</h1>
    <p>This will only be done once. Caching might take a while.</p>
    ";

    mkdir("ips");

    $csv_file = fopen("IP2LOCATION-LITE-DB1.CIDR.CSV", "r");

    $country_handles = array();
    
    while(($row = fgetcsv($csv_file, null, "," , '"', "\\")) !== false) {
        $range = lower_trimmed($row[0]);
        $country = lower_trimmed($row[1]);

        if(!array_key_exists($country, $country_handles)) {
            $country_handles[$country] = fopen("ips/".$country.".txt", "w");
        }

        fwrite($country_handles[$country], $range.PHP_EOL);
    }

    fclose($csv_file);

    foreach(array_keys($country_handles) as $country) {
        fclose($country_handles[$country]);
    }

    return true;
}

function get_basic_blocked($path) {
    $file = new SplFileObject($path);
    $len = 0;

    while(!$file -> eof()) {
        $line = rtrim(rtrim(rtrim($file->fgets()), " 1;"));
        if(strlen($line) === 0) {
            continue;
        }

        if($len > (isset($_GET["results"]) ? intval($_GET["results"]) : 250)) {
            break;
        }

        yield $line;
        $len++;
    }
}

function make_blocked_ip_html($label, $range, $type, $varname) {
    return "<li>"
            .$label
            ."<form method=\"POST\" action=\"/index.php?unblock=".$type."\">
                <input type=\"hidden\" name=\"".$varname."\" value=\"".$range."\">
                <button type=\"submit\">Unblock</button>
            </form>
            </li>";
} 

function block_country($target_country) {
    global $country_ban_path;
    
    $ip_file = lower_trimmed(file_get_contents("ips/".lower_trimmed($target_country).".txt"));
    $ips = explode(PHP_EOL, $ip_file);
    $ips = array_map(function($item) { return "$item 1;"; }, $ips);

    $added_lines = PHP_EOL."#c=".$target_country.";start";
    $added_lines = $added_lines.PHP_EOL.implode(PHP_EOL, $ips).PHP_EOL;
    $added_lines = $added_lines."#c=".$target_country.";end".PHP_EOL;

    file_put_contents($country_ban_path, $added_lines, FILE_APPEND | LOCK_EX);
}

function remove_range_from_file($path, $range) {
    $file = new SplFileObject($path);
    $new_file = "";
    
    while(!$file -> eof()) {
        $line = $file->fgets();
        if(str_starts_with($line, $range)) {
            continue;
        }

        $new_file = $new_file.$line;
    }

    return $new_file;
}

if(strlen($password_hash) !== 0) {
    if(!isset($_COOKIE["Authorization"])) {
        header("Location: /login.php");
        die();
    }

    if($_COOKIE["Authorization"] !== $password_hash) {
        header("Location: /login.php?e=p");
        die();
    }
} 

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if(isset($_GET["block"])) {
        if($_GET["block"] === "country") {
            block_country($_POST["country"]);
        } else if($_GET["block"] === "range") {
            file_put_contents($manual_ban_path, "".PHP_EOL.$_POST["range"]." 1;", FILE_APPEND | LOCK_EX);
        } else if($_GET["block"] === "auto") {
            file_put_contents($auto_ban_path, "".PHP_EOL.$_POST["range"]." 1;", FILE_APPEND | LOCK_EX);
        }
    }

    if(isset($_GET["unblock"])) {
        if($_GET["unblock"] === "country") {
            $file_lines = explode(PHP_EOL, file_get_contents($country_ban_path));

            $start_index = array_search("#c=".lower_trimmed($_POST["country"]).";start", $file_lines);
            $end_index = array_search("#c=".lower_trimmed($_POST["country"]).";end", $file_lines);

            array_splice($file_lines, $start_index, ($end_index-$start_index+1));

            file_put_contents($country_ban_path, trim(join(PHP_EOL, $file_lines)), LOCK_EX);
        } else if($_GET["unblock"] === "range") {
            $new_file = remove_range_from_file($manual_ban_path, $_POST["range"]);
            file_put_contents($manual_ban_path, trim($new_file), LOCK_EX);
        } else if($_GET["unblock"] === "auto") {
            $new_file = remove_range_from_file($auto_ban_path, $_POST["auto"]);
            file_put_contents($auto_ban_path, trim($new_file), LOCK_EX);
        }
    }


    header("Location: /index.php");
}

if(cache_ip_countries()) {
    echo "<script>window.location.reload();</script>";
}

if(isset($_GET["countries"])) {
    $files = scandir("ips");

    echo "
    <h1>Available country codes</h1>
    <p>NOTE: \"-\" is for IPs that havent been assigned to a country
    <ul>
    ";
    
    foreach(array_diff($files, array(".", "..")) as $file) {
        echo "<li>".(substr($file, 0, strlen($file)-4))."</li>";
    }

    echo "</ul>";
    die();
}

?>


<html>
    <head>
        <title>IP block dashboard</title>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>

    <body>
        <h1>IP management tools</h1>

        <h2 class="mb-0">Countries blocked</h2>
        <ul style="margin-top: 2px">
            <?php
                $file = new SplFileObject($country_ban_path);
                $current_country = null;
                $country_ips = array();
                $errors = array();

                $line_number = -1;

                while(!$file -> eof()) {
                    $line = $file->fgets();
                    $line_number++;

                    if(strlen(lower_trimmed($line)) === 0) {
                        continue;
                    }

                    if(str_starts_with($line, "#c=")) {
                        $data = explode(";", str_replace("#c=", "", $line));
                        $country = $data[0];
                        $mode = rtrim($data[1]); // "start" or "stop"

                        if($mode === "start") {
                            if(!is_null($current_country)) {
                                array_push($errors, "line ".$line_number.": another country block is open, starting as ".$country);
                            }

                            $current_country = $country;
                            $country_ips[$country] = array();
                        }

                        if($mode === "end") {
                            if(is_null($current_country) || $current_country !== $country) {
                                array_push($errors, "line ".$line_number.": country block not open, ignoring");
                            } else {
                                $current_country = null;
                            }
                        }

                        continue;
                    }

                    if(is_null($current_country)) {
                        array_push($errors, "line ".$line_number.": Country tag not open, exiting");
                        break;
                    }

                    array_push($country_ips[$current_country], $line);
                }

                foreach(array_keys($country_ips) as $country) {
                    echo make_blocked_ip_html(
                        (strtoupper($country))." (".count($country_ips[$country])." IP ranges blocked)",
                        $country,
                        "country",
                        "country"
                    );
                }

                foreach($errors as $error) {
                    echo "<li style=\"color: red\">Error: ".$error."</li>";
                }

                $file = null;
            ?>
        </ul>

        <form style="margin-bottom: 0px" method="POST" action="/index.php?block=country">
            <label for="country">Country:</label>
            <input name="country" placeholder="e.g. FI" id="country">
            <button class="destructive" type="submit">Block</button>
        </form>

        <a href="/index.php?countries">View available countries</a>

        <h2 class="mb-0">Manually blocked IP ranges:</h2>
        <ul style="margin-top: 2px">
            <?php
                foreach(get_basic_blocked($manual_ban_path) as $line) {
                    echo make_blocked_ip_html($line, $line, "range", "range");
                }
            ?>
        </ul>

        <form method="POST" action="/index.php?block=range">
            <label for="range">IP range:</label>
            <input name="range" placeholder="e.g. 37.136.0.0/16" id="range">
            <button class="destructive" type="submit">Block</button>
        </form>

        <h2 class="mb-0">Automatically blocked IP ranges:</h2>
        <form method="POST" action="/index.php?unblock=auto">
            <label for="auto">IP range:</label>
            <input name="auto" placeholder="e.g. 37.136.0.0/16" id="auto">
            <button type="submit">Unblock</button>
        </form>
        <ul style="margin-top: 2px">
            <?php
                foreach(get_basic_blocked($auto_ban_path) as $line) {
                    echo make_blocked_ip_html($line, $line, "auto", "auto");
                }
            ?>
        </ul>
    </body>
</html>


<style>
    * {
        font-family: "Inter", sans-serif
    }

    body {
        margin-left: auto;
        margin-right: auto;
        max-width: 800px;
        width: 95%;
    }

    ul li {
        margin-bottom: 8px;
    }

    button {
        border-radius: 8px;
        outline: none;
        border: none;
        padding: 4px;
    }

    .destructive {
        background-color: red;
        color: white;
    }

    .mb-0 {
        margin-bottom: 0px;
    }
</style>
