<?php
/**
 * Unpack a zip file with PclZip, the pure-PHP zip reader in WordPress core.
 * Used when the image is built (Dockerfile): php unzip.php <file.zip> <directory>
 */
require '/var/www/html/wp-admin/includes/class-pclzip.php';

$archive = new PclZip( $argv[1] );
if ( 0 === $archive->extract( PCLZIP_OPT_PATH, $argv[2] ) ) {
	fwrite( STDERR, 'Could not unpack ' . $argv[1] . ': ' . $archive->errorInfo( true ) . "\n" );
	exit( 1 );
}
