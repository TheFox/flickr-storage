<?php

$DEBUG = 0;

if(PHP_SAPI != 'cli') die('ERROR: You must run this script under shell.');

declare(ticks = 1);
require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Dumper;
use OAuth\Common\Storage\Session;
use OAuth\Common\Consumer\Credentials;
use OAuth\Common\Http\Client\StreamClient;
use OAuth\OAuth1\Signature\Signature;
use OAuth\Common\Http\Uri\UriFactory;

use TheFox\OAuth\Common\Http\Client\GuzzleStreamClient;
use TheFox\OAuth\OAuth1\Service\Flickr;

$exit = 0;
if(function_exists('pcntl_signal')){
	function signalHandler($signo){ global $exit; $exit++; if($exit >= 2) exit(); }
	pcntl_signal(SIGTERM, 'signalHandler');
	pcntl_signal(SIGINT, 'signalHandler');
}

$paramtersFilePath = __DIR__.'/parameters.yml';
if(!file_exists($paramtersFilePath)){
	
	print "Consumer key: ";
	$consumerKey = trim(fgets(STDIN));
	
	print "Consumer secret: ";
	$consumerSecret = trim(fgets(STDIN));
	
	$paramters = array(
		'flickr' => array(
			'consumer_key' => $consumerKey,
			'consumer_secret' => $consumerSecret,
		),
	);
	file_put_contents($paramtersFilePath, Yaml::dump($paramters));
}

$paramters = Yaml::parse($paramtersFilePath);
if(!isset($paramters['flickr']['token']) && !isset($paramters['flickr']['token_secret'])){
	$uriFactory = new UriFactory();
	$currentUri = $uriFactory->createFromSuperGlobalArray(array(
		'HTTP_HOST' => 'fox21.at',
		'SERVER_PORT' => 80,
		'REQUEST_URI' => '/oauth.php',
		'QUERY_STRING' => '',
	));
	$currentUri->setQuery('');
	
	$storage = new Session(false);
	$credentials = new Credentials($paramters['flickr']['consumer_key'], $paramters['flickr']['consumer_secret'], $currentUri->getAbsoluteUri());
	
	$flickrService = new Flickr($credentials, new GuzzleStreamClient(), $storage, new Signature($credentials));
	
	
	if($token = $flickrService->requestRequestToken()){
		$accessToken = $token->getAccessToken();
		$accessTokenSecret = $token->getAccessTokenSecret();
		
		if($accessToken && $accessTokenSecret){
			
			$url = $flickrService->getAuthorizationUri(array('oauth_token' => $accessToken, 'perms' => 'write'));
			print "Authorization URI: ".$url."\n";
			system('open -a Firefox.app "'.$url.'"');
			
			
			print "Press return when you're finished...\n";
			fgets(STDIN);
			
			print "Token: ";
			$token = trim(fgets(STDIN));
			
			print "Verifier: ";
			$verifier = trim(fgets(STDIN));
			
			#if($token = $flickrService->requestAccessToken($accessTokenSecret)){
			try{
				if($token = $flickrService->requestAccessToken($token, $verifier, $accessTokenSecret)){
					$accessToken = $token->getAccessToken();
					$accessTokenSecret = $token->getAccessTokenSecret();
					
					print "save accessToken and accessTokenSecret\n";
					$paramters['flickr']['token'] = $accessToken;
					$paramters['flickr']['token_secret'] = $accessTokenSecret;
					file_put_contents($paramtersFilePath, Yaml::dump($paramters));
					
					#print "run this script again\n";exit();
				}
			}
			catch(Exception $e){
				print "ERROR: ".$e->getMessage()."\n";
				exit(1);
			}
			
		}
		
	}
	
}

try{
	$metadata = new Rezzza\Flickr\Metadata($paramters['flickr']['consumer_key'], $paramters['flickr']['consumer_secret']);
	$metadata->setOauthAccess($paramters['flickr']['token'], $paramters['flickr']['token_secret']);
	
	$factory = new Rezzza\Flickr\ApiFactory($metadata, new Rezzza\Flickr\Http\GuzzleAdapter());
	
	$xml = $factory->call('flickr.test.login');
	
	print "status: ".(string)$xml->attributes()->stat."\n";
	
}
catch(Exception $e){
	print "ERROR: ".$e->getMessage()."\n";
	exit(1);
}
