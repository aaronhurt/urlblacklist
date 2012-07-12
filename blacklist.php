<?php

/* a little php code to parse all the files
  in the large download from urlblacklist.com
  into a manageable sqlite database.  processing of
  the entire list takes less than 5 minutes on my
  home linux machine.
  
  this is the sqlite schema used for the below functions:

drop table if exists `categories`;
create table `categories` (
	`id` integer primary key autoincrement,
	`name` varchar(128) default null,
	`desc` varchar(128) default null,
	unique (`name`) on conflict replace
);

drop table if exists `blacklist`;
create table `blacklist` (
	`id` integer primary key autoincrement,
	`type` varchar(5) default null,
	`category` integer default null,
	`value` varchar(128) default null,
	unique (`type`, `value`) on conflict replace
);

*/

set_time_limit(0);
ini_set("memory_limit", "2048M");

// where your sqlite database is located
$my_db = "/home/ahurt/blacklist.db";

// full path to your extracted urlblacklist.com files
$my_blacklist = "/home/ahurt/Desktop/blacklists";

// populate categories
function read_categories($blpath, $db) {
    // open database
    $dh = new SQLite3($db);
    // tell them what we are doing
    echo "building category list and descriptions ...\n";
    // begin transaction
    if ($dh->query('BEGIN TRANSACTION') === false) die("ERROR ".$dh->lastErrorMsg()."\n");
    // loop through file
    foreach (file($blpath.'/CATEGORIES', FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^[a-z]+\s-\s[A-Z]+/', $line)) {
            // explode line into parts
            $temps = explode(' - ', $line, 2);
            // build query
            $sql = sprintf("insert or ignore into `categories` (`name`, `desc`) values ('%s', '%s')",
                $dh->escapeString(trim($temps[0])), $dh->escapeString(trim($temps[1])));
            // execute in transaction
            if ($dh->query($sql) === false) die("ERROR ".$dh->lastErrorMsg()."\n");
        }
    }
    // end transaction
    if ($dh->query('END TRANSACTION') === false) die("ERROR ".$dh->lastErrorMsg()."\n");
    // close handle
    $dh->close();
}
read_categories($my_blacklist, $my_db);

// read blacklist files
function read_blacklist($blpath, $db) {
    // open database
    $dh = new SQLite3($db);
    foreach (array('urls', 'domains') as $type) {
        // get files
        $files = glob($blpath.'/*/'.$type);
        // loop through url files
        foreach ($files as $file) {
            // get category from file path
            $cat = basename(dirname($file));
            // tell what we are about to do
            echo "parsing $cat $type ...\n";
            // begin transaction
            if ($dh->query('BEGIN TRANSACTION') === false) die("ERROR ".$dh->lastErrorMsg()."\n");
            // loop through contents and insert into database
            foreach(file($file, FILE_SKIP_EMPTY_LINES) as $line) {
                // build query
                $sql = sprintf("insert or ignore into `blacklist` (`type`, `category`, `value`) values ('%s', '%s', '%s')",
                    $dh->escapeString($type), $dh->escapeString($cat), $dh->escapeString(trim($line)));
                // execute in transaction
                if ($dh->query($sql) === false) die("ERROR ".$dh->lastErrorMsg()."\n");
            }
            // end transaction
            if ($dh->query('END TRANSACTION') === false) die("ERROR ".$dh->lastErrorMsg()."\n");
        }
    }
    // close handle
    $dh->close();
}
read_blacklist($my_blacklist, $my_db);
