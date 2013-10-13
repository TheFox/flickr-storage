<?php

if(PHP_SAPI != 'cli') die('ERROR: You must run this script under shell.');

declare(ticks = 1);

require __DIR__.'/vendor/autoload.php';


function siPrefix($size){
	$base = log($size) / log(1024);
	$suffixes = array('', 'k', 'M', 'G', 'T');
	$suffix = $suffixes[floor($base)];
	
	return sprintf('%.1f', pow(1024, $base - floor($base))).$suffix;
}

if(!class_exists('Rezzza\Flickr\Metadata')){
	print "ERROR: run 'composer update' \n";
	exit(1);
}
