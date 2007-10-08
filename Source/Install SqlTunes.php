#!/usr/bin/php
<?
// Copyright 2007, Robert Pufky
//
// SqlTunes Installation Scripts
//
// This installs SqlTunes onto a system
//
// Does not do 10.4 / iTunes 7.0.2 system checks
//
//-------------------------------------------------
// You shouldn't need to change anything below here
//-------------------------------------------------

echo "\nCreating target: ";
// Make the target applications directory
`mkdir -p /Applications/.SqlTunes/Interfaces`;
echo "done.";

echo "\nInstalling SqlTunes Interfaces: ";
// Copy interfaces & SqlTunes.app to Applications
`cp -R /Volumes/SqlTunes\ 2.0/.SqlTunes/Interfaces/* /Applications/.SqlTunes/Interfaces/`;
echo "done.";

echo "\nInstalling SqlTunes: ";
`cp -R /Volumes/SqlTunes\ 2.0/.SqlTunes/SqlTunes.app /Applications/`;
echo "done.";

echo "\nBacking up /etc/php.ini.default to /etc/php.ini.default.backup: ";
`cp -f /etc/php.ini.default /etc/php.ini.backup`;
echo "done.";

echo "\nUpdating /etc/php.ini.default: ";
// we could use sed/awk here, but this is quick and dirty
$source = file("/etc/php.ini.default");
$fpipe = fopen("/etc/php.ini.default","w");

foreach( $source as $line ) {
  // check for max_execution_time
  if( strpos($line,"max_execution_time = 30") !== false ) { $line = "max_execution_time = 0 ; Maximum execution time of each script, in seconds\n"; }
  // check for memory_limit
  if( strpos($line,"memory_limit = 8M") !== false ) { $line = "memory_limit = 100M    ; Maximum amount of memory a script may consume (100MB)\n"; }
  // write line back to php.ini.default
  fwrite($fpipe,$line);
}

fclose($fpipe);
echo "done.";

// verify MySql socket workaround (default php is /var/mysql/mysql.sock, default OSX is /tmp/mysql.sock
echo "\nVerify local MySql Socket: ";
`mkdir -p /var/mysql`;
`ln -sf /tmp/mysql.sock /var/mysql/mysql.sock`;
echo "done.";

echo "\n\nInstallation complete!";
?>