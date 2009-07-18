#!/usr/bin/php-cgi -q
<?php

/**
 * @file
 * Recursively bulk upload a given directory's entire file contents to a given
 * Rackspace Cloud Files container.
 *
 * @author Mike Smullin <mike@smullindesign.com>
 * @license MIT
 *
 * Usage:
 *   ./scp-cloudfiles.sh.php -u=<user> -k=<api_key> -c=<container> -p=<path>
 */

// initialize
ini_set('register_globals', 'on');
error_reporting(E_ALL);
require_once './php-cloudfiles-1.3.0/cloudfiles.php';

// validate arguments
$user = $_GET['-u'];
$api_key = $_GET['-k'];
$container_name = $_GET['-c'];
$path = $_GET['-p'];
if (empty($user) || empty($api_key) || empty($container_name) || empty($path)) {
  echo <<<TEXT
usage: scp-cloudfiles -u=<user> -k=<api_key> -c=<container> -p=<path>
scp-cloudfiles command-line client, version 1.0-alpha.


TEXT;
  exit;
}

/**
 * Flush given output to stdout.
 *
 * @param String $s
 *   Text to output stdout.
 * @param Boolean $lb
 *   Include CR line break.
 */
function out($s = '', $lb = TRUE) {
  echo $s . ($lb? "\n" : '');
  ob_flush();
}

/**
 * List all files in a directory tree.
 * @author <archipel.gb@online.fr>
 * @see http://us2.php.net/manual/en/function.opendir.php#83990
 *
 * @param String $from
 *   Filesystem path to start recursing from.
 * @return Array
 *   List of all files.
 */
function listFiles($from = '.') {
//   return `find "$from";`;
  if (!is_dir($from)) {
    return FALSE;
  }

  $files = array();
  $dirs = array($from);
  while (NULL !== ($dir = array_pop($dirs))) {
    if ($dh = opendir($dir)) {
      while (FALSE !== ($file = readdir($dh))) {
        if ($file == '.' || $file == '..') {
            continue;
        }
        $path = $dir . '/' . $file;
        if ( is_dir($path)) {
          $dirs[] = $path;
        }
        else {
          $files[] = $path;
        }
      }
      closedir($dh);
    }
  }

  return $files;
}

# Authenticate to Cloud Files.  The default is to automatically try
# to re-authenticate if an authentication token expires.
#
# NOTE: Some versions of cURL include an outdated certificate authority (CA)
#       file.  This API ships with a newer version obtained directly from
#       cURL's web site (http://curl.haxx.se).  To use the newer CA bundle,
#       call the CF_Authentication instance's 'ssl_use_cabundle()' method.
#
out('Initializing new CF_Authentication...', FALSE);
$auth = new CF_Authentication($user, $api_key);
out('Done.');

out('Authenticating with Rackspace Cloud Files...', FALSE);
$auth->authenticate();
out('Done.');

# Establish a connection to the storage system
#
# NOTE: Some versions of cURL include an outdated certificate authority (CA)
#       file.  This API ships with a newer version obtained directly from
#       cURL's web site (http://curl.haxx.se).  To use the newer CA bundle,
#       call the CF_Connection instance's 'ssl_use_cabundle()' method.
#
out('Establishing a new connection to storage system...', FALSE);
$conn = new CF_Connection($auth);
out('Done.');

out('Getting existing remote Container...', FALSE);
try {
  $container = $conn->get_container($container_name);
}
catch (Exception $e) {
  out('Fail! Container does not exist!');
  out('Attempting to automatically create new remote Container...', FALSE);
  $container = $conn->create_container($container_name);
}
out('Done.');

foreach (listFiles($path) as $file) {
  $filepath = dirname($file);
  $shortpath = basename(str_replace($path, '', $filepath));
  $filename = basename($file);
  $object_name = preg_replace('/[^\w\d\.]+/', '_', $shortpath .'_'. $filename);
  out("Found $filename in ./$shortpath...");

  // @TODO: Figure out how to use directory separators (/) in the object name. 
  //        It is possible.
  out("Creating a new remote storage Object $object_name...", FALSE);
  $object = $container->create_object($object_name);
  out('Done.');
  
  out('Uploading content from a local file by convenience function...', FALSE);
  $object->load_from_filename($file);
  out('Done.');
  
  out('Making uploaded file public...', FALSE);
  $uri = $container->make_public();
  out('Done.');
  
  out('Obtaining public URI for uploaded file...');
  echo "\t". $object->public_uri() ."\n";
  out('Done.'."\n");
  
  unset($object);
}

out('Success! Thank you for using this tool.');
out();
exit;
