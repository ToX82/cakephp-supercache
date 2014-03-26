<?php
/**
 * (almost) drop-in cahing engine for CakePHP
 * Got the idea by a fantastic plugin by Matt Curry https://github.com/mcurry/html_cache
 * but I wanted it to be even simpler to implement.
 *
 * Copyright (c) 2014, Emanuele "ToX" Toscano
 * Available via the MIT license.
 * see: https://github.com/ToX82/cakephp-supercache for details
 * index.php - version. 0.1
 *
 *
 * INSTALLATION STEPS:
 * 1) create a new folder called "cache" in your app/webroot directory, with write permissions
 * 2) rename app/webroot/index.php to app/webroot/index_cake.php
 * 3) save this file to app/webroot. Yes, you're replacing the original file with this one
 * 4) that's all folks!
 */

session_name('CAKEPHP');
session_start();



/*
*
* CONFIGURATION
*
*/
$cache = 1;
$debug = 0;

$cachePath = getcwd().'/cache'; // the path of cache folder, starting from app/webroot



/*
*
* LET'S DO SOME CHECKS TO SEE IF WE NEED A CACHE FILE OR NOT
* p.s. you have to put your cacheable uris here :)
*
*/

// PATH BASED CHECK
$homepath = str_replace($_SERVER['DOCUMENT_ROOT'], "", $_SERVER['SCRIPT_FILENAME']);
$homepath = str_replace("app/webroot/index.php", "", $homepath); // root path. This is to make it caching work even if cakephp is installed in a sub-folder

if ($cache == 1 and (
        $_SERVER["REQUEST_URI"] == $homepath // cache the home page...
         or 
        strpos($_SERVER["REQUEST_URI"], "pages/") > 0 // ... everything from pages controller...
         or 
        strpos($_SERVER["REQUEST_URI"], "news/") > 0 // ... and from news controller
    )) {
} else {
    $debugInfo = "Not a cacheable page";
    $cache = 0;
}

// AUTHENTICATION BASED CHECK 
// ( We delete every cache file while we're logged in and working on our website, this is to ensure that all cache files are up-to-date )

if (!empty($_SESSION['Auth']['User'])) {
    $debugInfo = "Authenticated, emptying cache files";
    $cache = 0;

    // Removing all cache files and directories
    $contents = new RecursiveDirectoryIterator( $cachePath, FilesystemIterator::SKIP_DOTS );
    $contents = new RecursiveIteratorIterator( $content, RecursiveIteratorIterator::CHILD_FIRST );
    foreach ( $contents as $content ) {
        $content->isDir() ?  rmdir($content) : unlink($content);
    }
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
    
    if (is_readable($cacheFile)) { // If a cache file is already there, let's use it. CakePHP can rest for a while, this time :)
        $debugInfo = "Showing cached file: $cacheFile";

        ob_start();
            include($cacheFile);
        $page = ob_get_clean();
        echo $page;

    } else { // This page is not cached. Let's create a new one. Sorry Cake :)
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
    print_r($debugInfo . "<br />Used memory: " . memory_usage());
}

function memory_usage() {
    $size = memory_get_peak_usage(true);
    $unit = array('b','kb','mb','gb','tb','pb');
    return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
 }
