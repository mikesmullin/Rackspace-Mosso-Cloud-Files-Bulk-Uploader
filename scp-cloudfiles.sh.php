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

# Authenticate to Cloud Files.  The default is to automatically try
# to re-authenticate if an authentication token expires.
#
# NOTE: Some versions of cURL include an outdated certificate authority (CA)
#       file.  This API ships with a newer version obtained directly from
#       cURL's web site (http://curl.haxx.se).  To use the newer CA bundle,
#       call the CF_Authentication instance's 'ssl_use_cabundle()' method.
#
out(sprintf('Initializing new CF_Authentication as "%s" / "%s"...', $user, $api_key), FALSE);
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

out(sprintf('Getting existing remote Container "%s"...', $container_name), FALSE);
try {
  $container = $conn->get_container($container_name);
}
catch (Exception $e) {
  out('Fail! Container does not exist!');
//  out('Attempting to automatically create new remote Container...', FALSE);
//  $container = $conn->create_container($container_name);
}
out('Done.');

if (is_dir($path)) {
  $dirs = array($path);
  while (NULL !== ($dir = array_pop($dirs))) {
    if ($dh = opendir($dir)) {
      while (FALSE !== ($_file = readdir($dh))) {
        if ($_file == '.' || $_file == '..') {
            continue;
        }
        $_path = $dir . '/' . $_file;
        if (is_dir($_path)) {
          $dirs[] = $_path;
        }
        else {
          $file = $_path;
          $object_name = ltrim(str_replace($path, '', $file), '/');
          
          out(sprintf('Uploading file "%s"...', $object_name), FALSE);
          $object = $container->create_object($object_name);
//          out('Done.');
          
//          out('Uploading content from a local file by convenience function...', FALSE);
          $object->load_from_filename($file);
          out('Done.');
          
        //  out('Making uploaded file public...', FALSE);
        //  $uri = $container->make_public();
        //  out('Done.');
          
        //  out('Obtaining public URI for uploaded file...');
        //  echo "\t". $object->public_uri() ."\n";
        
//          out('Done.'."\n");
          unset($object);          
        }
      }
      closedir($dh);
    }
  }
}

exit(0);