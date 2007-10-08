#!/usr/bin/php
<?
// Copyright 2007, Robert Pufky
//
// SqlTunes Engine
//
// This engine will export your iTunes playlist to a MySql database
// It requires that a file with sql credentials be created (from the main interface)
//
// This then can be imported to any mysql db that supports UTF8!
//
// Please note: iTunes 7.1 and OS X 10.4 required!
//
//-------------------------------------------------
// You shouldn't need to change anything below here
//-------------------------------------------------
// create file pipe and itunes track
$iTunesTrack = new track();

// open log file (if we can't, automatically fail)
if( !$log = fopen("/tmp/SqlTunes.log","w") ) { die("Could not open SqlTunes log file."); }

// init credentials array and connect to MySql DB
$startTime = init($credentials = array(), $link);

echo "Opening iTunes... (You can minimize iTunes when it opens) ";
// grab the total track count from iTunes
$tracks = str_replace("\n","",@`/usr/bin/osascript /Applications/.SqlTunes/Interfaces/iTunes.Interface.app count 2> /dev/null`);
if( !is_numeric($tracks) ) { wlog("Invalid number of tracks returned.",true,true); }
echo "done.\n";

// go through all the tracks in the iTunes library
for( $i = 1; $i <= $tracks; $i++ ) {
  // dump data for that track
  @`/usr/bin/osascript /Applications/.SqlTunes/Interfaces/iTunes.Interface.app $i 2>&1 /dev/null`;
  
  // Load the iTunes dump, dump the load read if we are logging it
  $iTunesTrack->setData(getDump($credentials["Log"]));
  
  switch( $credentials["Log"] ) {
  	case "Debug": { wlog($iTunesTrack->debug(),false,false); }
  	case "Verbose": {
		printf("\n%06.2f%% %6d %6d | %s - %s",($i/$tracks)*100,$i,$iTunesTrack->getDatabaseID(),$iTunesTrack->getArtist(),$iTunesTrack->getTrackName());  	
		wlog($i . " " . $iTunesTrack->getDatabaseID() . " " . $iTunesTrack->getArtist() . " - " . $iTunesTrack->getTrackName(),false,false);  	
  	} break;
  	case "Normal": { printf("\n%06.2f%% %s - %s",($i/$tracks)*100,$iTunesTrack->getArtist(),$iTunesTrack->getTrackName()); } break;
  }
 
  // perform non-fatal query on MySql DB
  query($iTunesTrack->getQuery(),false);
}

// log the summary stats
$processTime = time()-$startTime;
wlog($tracks . " processed, total processing time: " . $processTime . " seconds.  Processing rate: " . round($tracks/$processTime, 2) . " songs/sec.",false,false);

// close dump file
fclose($log);

// cleanup
clean();



// Function: getDump
// Requires: string - log level; only "Debug" level makes it log any output (sliently)
// Returns: array of key => value pairs
// Purpose: Parses loaded file into an array of key => value pairs containing data
Function getDump($log) {
	$file = loadtrack();
	$data = array();
	
	// for each line in file
	foreach( $file as $line ) {
		// if we are not defining a variable (no |]=> in line)
		// NOTE: if a |]=> appears anywhere in the iTunes information, this will fail
		// known bug, but should not happen unless someone is doing something whacky with their lyrics
		if( strpos($line,"|]=>") === false ) {
			// then we are adding to the last variable
			$data[$lastVar] = $data[$lastVar] . $line;
		} else {
			// grab the variable and data
			$newVar = explode("|]=>",$line);
			$lastVar = $newVar[0];
			
			switch($lastVar) {
				case "tlongdescription" : {
						// long description, remove newline if no data
						if( rtrim($newVar[1]) == "" ) {
							$newVar[1] = "";
						}	
					} break;
				case "tlyrics" : {
						// lyrics, always keep newlines (in other words, do nothing)
					} break;
				default : {
						// else, remove newline
						$newVar[1] = rtrim($newVar[1]);
						} break;
			}
			
			// push data onto array
			$data[$lastVar] = $newVar[1];
		}
	}
	
	// only print array data if in debug mode
	if( $log == "Debug" ) {
		$output = "\n----getDump----";
		foreach( $data as $key => $value ) {
			$output .= "\n" . $key . "=>'" . $value . "'";
		}
		wlog($output . "\n---------------",false,false);
	}

	return $data;
}

// Function: loadtrack()
// Requires: nothing
// Returns: array containing lines of file
// Purpose: converts MAC file format to UNIX file format, and loads into array
//          Created for ease of optimization later
Function loadtrack() {
	// We actually get better performance out of this, opposed to reading, replacing, and 
	// using explode to get a file array (explode is probably the hold up here)
	// test: TR: 100-45sec,200-91sec,500-216sec; pipe: 100-47sec,200-90sec,500-221sec
	
	// convert file to UNIX and load it
	`tr '\r' '\n' < /tmp/SqlTunes.track > /tmp/SqlTunes.unix`;
	// load into array and return
	return file("/tmp/SqlTunes.unix");
}

// Function: clean()
// Requires: nothing
// Returns: nothing
// Purpose: Cleans temporary files used, and launches finder opened to the dump file
Function clean() {
	// remove iTunes track & unix conversions
	`rm -f /tmp/SqlTunes.track;rm -f /tmp/SqlTunes.unix`;
	// Create & move dumpfile to subdirectory in finder (for people who can't find it)
	`mkdir -p /tmp/SqlTunes;mv /tmp/SqlTunes.log /tmp/SqlTunes/SqlTunes.log`;
	// launch finder in the background
	//`/usr/bin/osascript /Applications/.SqlTunes/Interfaces/iTunes.Interface.app clean 2>&1 /dev/null &`;
	`open /tmp/SqlTunes/SqlTunes.log &`;
}

// Function: init()
// Purpose: Initalizes credentials, and opens log file
// Requires: array - holds user credentials for MySql
//           link - holds established MySql link
// Returns: date - the time the program was started
function init(&$credentials,&$link) {
	$time = time();
	
	// open credential file, convert to UNIX, read variables (1 per line), destroy file
	$data = explode("\r",`cat /tmp/.connect.sqltunes`);
	`rm -f /tmp/.connect.sqltunes`;
	
	// for each line in file
	foreach( $data as $line ) {
		// break up tokens and store
		$newVar = explode("=",$line);
		// set the appropriate parsed credential
		$credentials[$newVar[0]] = rtrim($newVar[1]);
	}

	// attempt to connect to the database, if failed, die
	if( !$link = mysql_connect($credentials["Server"],$credentials["Username"],$credentials["Password"]) ) {
		wlog("Database connection error: Unable to connect to Mysql server at '" . $credentials["Server"] . "' with user '" . $credentials["Username"] . "'\n" . mysql_error(),true,true);
	}
	// attempt to select the database, if failed, die
	if( !mysql_select_db($credentials["Database"],$link) ) { 
		wlog("Could not select database '" . $credentials["Database"] . "' for use.\n" . mysql_error(),true,true);
	}

	// Drop the existing table if it exists, if failed, die
	query("drop table if exists tracks",true);

	// create the new table query
	$query = "create table tracks (id int(11) not null auto_increment," . 
		"talbum varchar(255) default ''," .
		"talbumartist varchar(255) default ''," .
		"tartist varchar(255) default ''," .
		"tbitrate smallint unsigned default 0," .
		"tbookmark float default 0.0," .
		"tbookmarkable tinyint(1) default 0," .
		"tbpm smallint unsigned default 0," .
		"tcategory varchar(255) default ''," .
		"tcomment varchar(255) default ''," .
		"tcompilation tinyint(1) default 0," .
		"tcomposer varchar(255) default ''," .
		"tdatabaseid int(11) default 0," .
		"tdateadded datetime default '0000-00-00 00:00:00'," .
		"tdescription varchar(255) default ''," .
		"tdisccount tinyint unsigned default 0," .
		"tdiscnumber tinyint unsigned default 0," .
		"tduration float unsigned default 0," .
		"tenabled tinyint(1) default 0," .
		"tepisodeid varchar(255) default ''," .
		"tepisodenumber tinyint unsigned default 0," .
		"teq varchar(255) default ''," .
		"tfinish float default 0.0," .
		"tgapless tinyint(1) default 0," .
		"tgenre varchar(255) default ''," .
		"tgrouping varchar(255) default ''," .
		"tkind varchar(255) default ''," .
		"tlongdescription varchar(255) default ''," .
		"tlyrics text default ''," .
		"tmodificationdate datetime default '0000-00-00 00:00:00'," .
		"tplayedcount int(11) default 0," .
		"tplayeddate date default '0000-00-00'," .
		"tpodcast tinyint(1) default 0," .
		"trating tinyint unsigned default 0," .
		"tsamplerate mediumint unsigned default 0," .
		"tseasonnumber tinyint unsigned default 0," .
		"tshufflable tinyint(1) default 0," .
		"tskippedcount int(11) default 0," .
		"tskippeddate datetime default '0000-00-00 00:00:00'," .
		"tshow varchar(255) default ''," .
		"tsortalbum varchar(255) default ''," .
		"tsortartist varchar(255) default ''," .
		"tsortalbumartist varchar(255) default ''," . 
		"tsortname varchar(255) default ''," .
		"tsortcomposer varchar(255) default ''," .
		"tsortshow varchar(255) default ''," .
		"tsize int(11) unsigned default 0," .
		"tstart float default 0.0," .
		"ttime time default '00:00:00'," .
		"ttrackcount tinyint unsigned default 0," .
		"ttracknumber tinyint unsigned default 0," .
		"tunplayed  tinyint(1) default 0," .
		"tvideokind varchar(11) default 'none'," .
		"tvolumeadjustment smallint default 0," .
		"tyear smallint unsigned default 0," .
		"tlocation varchar(255) default ''," .
		"tname varchar(255) default ''," .
		"tpersistentid varchar(255) default ''," .
		"PRIMARY KEY (id) ) ENGINE=MyISAM DEFAULT CHARSET=UTF8";
		
	// attempt to create the new table, if failed, die
	query($query,true);
	
	return $time;
}

// Function: query()
// Purpose: performs a MySql Query, with non-fatal logging
// Requires: string - MySql Query to perform
//           boolean - true if failed query is fatal
// Returns: Pass-through of mysql_query()
function query($query,$fatal) {
	global $link;
	global $credentials;

 	// attempt to select the database on the resource, otherwise error
    if( !mysql_select_db($credentials["Database"],$link) ) { wlog("Could not select database '" . $credentials["Database"] . "' for use.\n" . mysql_error(),true,true); }

	// preform the query and return the results
	$res = mysql_query($query,$link);
	
	// if we encountered a MySql error
	if( mysql_error() ) {
		// log it, and make it fatal if it is a "fatal" query
		if( $fatal ) {
			wlog("Query: " . $query . "\n\nError: " . mysql_error(),true,true);
		} else {
			// if it is not a fatal query, back-off DB for 1 second.
			wlog("Query: " . $query . "\n\nError: " . mysql_error(),false,true);
			sleep(1);
		}
	}
	
	// return results of query
	return $res;
}

// Function: wlog()
// Purpose: Writes a specified message to the logfile
// Requires: string - message to log
//           boolean - true if fatal log message
//           boolean - true if the log message should be printed to screen
// Returns: none
function wlog($message,$fatal,$visable) {
	global $log;

	// echo the message to the screen
	if( $visable ) {
		echo $message;
	}

	// If we are a fatal error, always log
	if( $fatal ) {
		// write the log, close it, open finder, stop execution.
		fwrite($log,"\n-------------FATAL-ERROR------------\n" . $message . "\n------------------------------------");
		fclose($log);
		clean();
		die();
	} else {
		// write the log message
		fwrite($log,"\n" . $message);
	}
}


//-------------------------------------------------
// iTunes 7.1 track class
//-------------------------------------------------
// This is basically an array wrapper with super-awesome text processing capabilities and functions
class track {
	// holds all track information
	var $trackInfo = array();

	// Function: track
	// Purpose: constructor - Initalizes the data array
	Function track() {
		//unset($this->$trackInfo);
		// because arrays are being fucktarded.
		foreach( $this->trackInfo as $key => $value ) {
			$this->trackInfo[$key] = "";
		}
	}
	
	// Function: resetTrack()
	// Requires: nothing
	// Purpose: alias of constructor, for use during execution
	Function resetTrack() {
		$this->track();
	}
	
	// Function: debug()
	// Requires: nothing
	// Returns: a string containing current array information formatted for output.
	// Purpose: prints the data array for debugging purposes
	Function debug() {
		$output = "\n-----debug-----";
		foreach( $this->trackInfo as $key => $value ) {
			$output .= "\n" . $key . "=>'" . $value . "'";
		}
		return $output . "\n---------------";
	}
	
	// Function: getArtist()
	// Requires: nothing
	// Returns: the current artist name, if it exists
	// Purpose: For formatting log data
	Function getArtist() {
		return $this->trackInfo["tartist"];
	}

	// Function: getTrackName()
	// Requires: nothing
	// Returns: the current track name, if it exists
	// Purpose: For formatting log data
	Function getTrackName() {
		return $this->trackInfo["tname"];
	}	
	
	// Function: getDatabaseID()
	// Requires: nothing
	// Returns: the current databaseID, if it exists
	// Purpose: For formatting log data
	Function getDatabaseID() {
		return $this->trackInfo["tdatabaseid"];
	}
	
	// Function: setData(array)
	// Requires: An array with or without data
	// Purpose: Takes raw array data and converts to mysql safe data.
	//          This pertains to matching mysql datatypes, not mysql-safe escaping
	//          If array keys do not match, it will not be imported.
	Function setData($newData) {
		// reset track data
		$this->resetTrack();
		// go through all the new data
		foreach( $newData as $key => $value ) {
			// if the value is blank, don't add to trackInfo 
			// Defaults are inserted via mysql (plus this is faster :) )
			if( $value != NULL && $value != "" ) {
				$this->trackInfo[$key] = $value;
			}
		}
	}
	
	// Function: getQuery()
	// Requires: Nothing
	// Returns: UTF8 mysql-safe insert query for dump / execution
	Function getQuery() {
		$query = "insert into tracks set ";
		
		// go through our data and write query
		foreach( $this->trackInfo as $key => $value ) {
			// If the value is set
			if( $value != "" ) {
				// check to see if we are a date (special conversion needed)
				switch($key) {
					// convert to full dates for db import
					case "tdateadded":
					case "tmodificationdate":
					case "tskippeddate": {
							$value = mysql_escape_string(date("YmdHis",strtotime($value)));
						} break;
					// convert to partial dates for db import
					case "tplayeddate" : {
							$value = mysql_escape_string(date("Ymd",strtotime($value)));
						} break;
					// videokind cannot be exported correctly yet (constant -> exporting from shell = bad)
					case "tvideokind" : {
							$value = "none";
						} break;
					default: $value = mysql_escape_string($value); break;
				}
				
				// create query
				$query .= $key . "='" . $value . "',";
			}
		}
		
		// replace last , with ; and return
		return substr($query,0,strrpos($query,",")) . ";";
	}
}
?>
