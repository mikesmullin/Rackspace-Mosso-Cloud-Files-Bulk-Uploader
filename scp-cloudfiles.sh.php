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
 *   ./scp-cloudfiles.sh.php <user> <api_key> <container> <path>
 */

// initialize
error_reporting(E_ALL);
require_once './php-cloudfiles-1.3.0/cloudfiles.php';

// validate arguments
$args = array_keys(array_slice($_GET, 1));
$user = array_shift($args);
$api_key = array_shift($args);
$container_name = array_shift($args);
$path = array_shift($args);
if (empty($user) || empty($api_key) || empty($container_name) || empty($path)) {
  echo <<<TEXT
usage: scp-cloudfiles <user> <api_key> <container> <path>
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

// @TODO: For ... loop recursively through filesystem $path 
$path = '/home/mikesmullin/Pictures/';
$file = '[wallcoo.com]_2880x900_DualScreen_Nature_Wallpaper_263531.jpg';

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
  $images = $conn->get_container($container_name);
} 
catch (Exception $e) {
  out('Fail! Container does not exist!');
  out('Attempting to automatically create new remote Container...', FALSE);
  $images = $conn->create_container($container_name);
}
out('Done.');

out('Creating a new remote storage Object...', FALSE);
$bday = $images->create_object($file);
out('Done.');

out('Uploading content from a local file by convenience function...', FALSE);
$bday->load_from_filename($path . $file);
out('Done.');

out('Making uploaded file public...', FALSE);
$uri = $images->make_public();
out('Done.');

out('Obtaining public URI for uploaded file...');
echo "\t". $bday->public_uri() ."\n\n";
out('Done.');

out('Success! Thank you for using this tool.');
out();
exit;