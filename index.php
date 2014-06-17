<?php
/**
 * (almost) drop-in cahing engine for CakePHP
 * Got the idea by a fantastic plugin by Matt Curry https://github.com/mcurry/html_cache
 * but I wanted it to be even simpler to implement.
 *
 * Copyright (c) 2014, Emanuele "ToX" Toscano
 * Available via the MIT license.
 * see: https://github.com/ToX82/cakephp-supercache for details
 * index.php - version. 0.2
 *
 *
 * INSTALLATION STEPS:
 * 1) create a new folder called "cache" in your app/webroot directory, with write permissions
 * 2) rename app/webroot/index.php to app/webroot/index_cake.php
 * 3) save this file to app/webroot. Yes, you're replacing the original file with this one
 * 4) configure as you need
 * 5) that's all folks!
 */


/*
*
* CONFIGURATION
*
*/
$cache = 1;
$debug = 0;
$filterType = "blacklist";  // "whitelist" caches specified pages - caches nothing by default
                            // "blacklist" don't cache specified pages - caches everything by default

$cacheHomePage = true;      // whether or not we want to cache the home page (you may need it if filterType is whitelist and you want the homepage to be cached)

$whitelist = array(
    "/pages"
);
$blacklist = array(
    "/login",
    "/users",
    "/contacts"
);


/*
* CAKEPHP RELATED CONFIGURATION
*/
session_name('CAKEPHP');
session_start();
$timerStart = microtime(true);
$cachePath = getcwd().'/cache'; // the path of cache folder, starting from app/webroot

/*
*
* CHECK IF WE NEED A CACHE FILE OR NOT
*
*/

// PATH BASED CHECKS
// based on whitelist, blacklist and cacheHomePage configurations

if ($cache == 0) {
    $debugInfo = "Caching disabled...";
} else {
    switch ($filterType) {
        case "whitelist":
            $cache = 0;
            foreach ($whitelist as $word) {
                if (strpos($_SERVER["REQUEST_URI"], $word) > -1) {
                    $debugInfo = "Whitelisted page, cache it!";
                    $cache = 1;
                }
            }
            break;
        case "blacklist":
            $cache = 1;
            foreach ($blacklist as $word) {
                if (strpos($_SERVER["REQUEST_URI"], $word) > -1) {
                    $debugInfo = "Blacklisted page! I'm not caching it";
                    $cache = 0;
                }
            }
            break;
    }
    if ($cacheHomePage == true) {
        $homepath = str_replace($_SERVER['DOCUMENT_ROOT'], "", $_SERVER['SCRIPT_FILENAME']);
        $homepath = str_replace("app/webroot/index.php", "", $homepath); // root path. This is to make it caching work even if cakephp is installed in a sub-folder

        if ($_SERVER["REQUEST_URI"] == $homepath) {
            $debugInfo = "Home page, cache it!";
            $cache = 1;
        }
    }
}



// SESSION BASED CHECK 
// When a user is logged, we delete every cache file, this is to ensure that all cache files are up-to-date )
// Pages with a session message are not cached too, to avoid caching the message itself

if (!empty($_SESSION['Auth']['User'])) {
    $debugInfo = "Authenticated, emptying cache files";
    $cache = 0;

    // Remove all cache files and directories
    $contents = new RecursiveDirectoryIterator( $cachePath, FilesystemIterator::SKIP_DOTS );
    $contents = new RecursiveIteratorIterator( $contents, RecursiveIteratorIterator::CHILD_FIRST );
    foreach ( $contents as $content ) {
        $content->isDir() ?  rmdir($content) : unlink($content);
    }
}
if (!empty($_SESSION['Message'])) {
    $debugInfo = "There's a session message to show, no caching :)";
    $cache = 0;
}


/*
*
* ACTION!
*
*/

if ($cache == 0) { // No caching, because either configuration or one of the checks we have done didn't want it
    include('index_cake.php');

} else { // Go ahead and use caching
    $cachePath = $cachePath . $_SERVER["REQUEST_URI"];
    $cacheFile = $cachePath . '/index.html';
    
    // If a cache file is already there, let's use it. CakePHP can rest for a while, this time :)
    if (is_readable($cacheFile)) { 
        $debugInfo = "Showing cached file: $cacheFile";

        ob_start();
            include($cacheFile);
        $page = ob_get_clean();
        echo $page;

    // This page is not cached. Let's create a new one. Sorry Cake :)
    } else { 
        $debugInfo = "Creating a new cache file...";

        ob_start();
        include('index_cake.php');
        $page = ob_get_contents();

        if (!is_dir($cachePath)) {
            mkdir($cachePath, $mode = 0777, $recursive = true);
        }
        file_put_contents($cacheFile, $page);
    }
}



/*
*
* DEBUG INFO
*
*/
if ($debug == 1 and isset($debugInfo)) {
    $timerEnd = microtime(true);
    print($debugInfo);
    print("<br />Used memory: " . memory_usage());
    printf("<br />Page was generated in %f seconds", $timerEnd - $timerStart);
}

function memory_usage() {
    $size = memory_get_peak_usage(true);
    $unit = array('b','kb','mb','gb','tb','pb');
    return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
 }
