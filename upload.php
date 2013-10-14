<?php

define('UPLOAD_PROGRESSBAR_ITEMS', 35);
define('CLEAR_CHAR', ' ');

require __DIR__.'/bootstrap.php';

use Symfony\Component\Yaml\Yaml;
use Rezzza\Flickr\Metadata;
use Rezzza\Flickr\Http\GuzzleAdapter;
use Rezzza\Flickr\ApiFactory;
use Guzzle\Http\Client;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\ErrorLogHandler;
use Ulrichsg\Getopt;

use TheFox\Image\Converter;


$logFormatter = new LineFormatter("[%datetime%] %level_name%: %message%\n"); # %context%
$log = new Logger('upload');

$logHandlerStderr = new StreamHandler('php://stderr', Logger::DEBUG);
$logHandlerStderr->setFormatter($logFormatter);
$log->pushHandler($logHandlerStderr);

$logHandlerFile = new StreamHandler(__DIR__.'/upload.log', Logger::INFO);
$logHandlerFile->setFormatter($logFormatter);
$log->pushHandler($logHandlerFile);

$logFilesSuccessfulStream = new StreamHandler(__DIR__.'/upload.files.log', Logger::INFO);
$logFilesSuccessfulStream->setFormatter($logFormatter);
$logFilesSuccessful = new Logger('upload');
$logFilesSuccessful->pushHandler($logFilesSuccessfulStream);

$log->info('start');
$logFilesSuccessful->info('start');

$exit = 0;
if(function_exists('pcntl_signal')){
	function signalHandler($signo){
		global $exit, $log;
		$exit++;
		print "\n";
		$log->info('[main] abort registered ['.$exit.']');
		if($exit >= 3) exit();
	}
	pcntl_signal(SIGTERM, 'signalHandler');
	pcntl_signal(SIGINT, 'signalHandler');
}

$getopt = new Getopt(array(
    array('d', 'description', Getopt::OPTIONAL_ARGUMENT, 'Description'),
    array('t', 'tags', Getopt::OPTIONAL_ARGUMENT, 'Comma separated tags.'),
    array('s', 'sets', Getopt::OPTIONAL_ARGUMENT, 'Comma separated sets.'),
    array('h', 'help', Getopt::NO_ARGUMENT, 'This help.'),
));
$getopt->parse();

if( $getopt->getOption('help') || !count($getopt->getOptions()) ){
	$getopt->showHelp();
	exit(3);
}

$paramtersFilePath = __DIR__.'/parameters.yml';
if(!file_exists($paramtersFilePath)){
	$log->critical('[main] first run accesstoken.php');
	exit(1);
}

$paramters = Yaml::parse($paramtersFilePath);

if(
	!isset($paramters)
	|| !isset($paramters['flickr'])
	|| !isset($paramters['flickr']['consumer_key'])
	|| !isset($paramters['flickr']['consumer_secret'])
){
	$log->critical('[main] parameters invalid');
	exit(1);
}

$photosetNames = array();
$photosetNamesLower = array();
if($getopt->getOption('sets')){
	$photosetNames = preg_split('/,/', $getopt->getOption('sets'));
	$photosetNamesLower = preg_split('/,/', strtolower($getopt->getOption('sets')));
}

$log->info('[main] description: '.($getopt->getOption('description') ? $getopt->getOption('description') : ''));
$log->info('[main] tags: '.($getopt->getOption('tags') ? $getopt->getOption('tags') : ''));
$log->info('[main] sets: '.($getopt->getOption('sets') ? $getopt->getOption('sets') : ''));

$uploadedTotal = 0;
$totalFiles = 0;
$totalFilesUploaded = 0;
$fileErrors = 0;
$filesFailed = array();


$metadata = new Metadata($paramters['flickr']['consumer_key'], $paramters['flickr']['consumer_secret']);
$metadata->setOauthAccess($paramters['flickr']['token'], $paramters['flickr']['token_secret']);

$guzzleAdapter = new GuzzleAdapter();
$guzzleAdapterVerbose = new GuzzleAdapter();
$guzzleAdapterClient = $guzzleAdapterVerbose->getClient();
$guzzleAdapterClientConfig = $guzzleAdapterClient->getConfig();

$curlOptions = $guzzleAdapterClientConfig->get(Client::CURL_OPTIONS);
$curlOptions[CURLOPT_CONNECTTIMEOUT] = 60;
$curlOptions[CURLOPT_NOPROGRESS] = false;

$size = 0;
$sizeLen = 0;
$dataLenPrev = 0;
$uploaded = 0;
$timePrev = 0;
$uploadedPrev = 0;
$uploadedDiff = 0;
$uploadedDiffPrev = array(0, 0, 0, 0, 0);
$termLinePrevLen = 0;

$curlOptions[CURLOPT_PROGRESSFUNCTION] = function($ch, $dltotal = 0, $dlnow = 0, $ultotal = 0, $ulnow = 0){
	global $exit, $uploadedTotal, $size, $sizeLen, $dataLenPrev, $uploaded, $timePrev, $uploadedPrev, $uploadedDiff, $uploadedDiffPrev, $termLinePrevLen;
	
	$dataLen = $ultotal - $dataLenPrev;
	$dataLenPrev = $ultotal;
	
	$uploaded += $dataLen;
	$uploadedTotal += $dataLen;
	$percent = $ultotal / $size * 100;
	if($percent > 100){
		$percent = 100;
	}
	$progressbarUploaded = round($percent / 100 * UPLOAD_PROGRESSBAR_ITEMS);
	$progressbarRest = UPLOAD_PROGRESSBAR_ITEMS - $progressbarUploaded;
	
	$timeCur = time();
	if($timeCur != $timePrev){
		$timePrev = $timeCur;
		$uploadedDiff = $uploaded - $uploadedPrev;
		$uploadedPrev = $uploaded;
		
		$uploadedDiff = ($uploadedDiff + array_sum($uploadedDiffPrev)) / 6;
		array_shift($uploadedDiffPrev);
		$uploadedDiffPrev[] = $uploadedDiff;
	}
	
	$termLine = sprintf("[file] %6.2f%% [%s%s] %".$sizeLen."s %10s\r",
		$percent,
		str_repeat('#', $progressbarUploaded),
		str_repeat(' ', $progressbarRest),
		number_format($uploaded),
		$uploadedDiff > 0 ? siPrefix($uploadedDiff).'/s' : ''
	);
	$termLinePrevLen = strlen($termLine);
	print $termLine;
	
	return $exit >= 2 ? 1 : 0;
};
$guzzleAdapterClientConfig->set(Client::CURL_OPTIONS, $curlOptions);

$apiFactory = new ApiFactory($metadata, $guzzleAdapter);
$apiFactoryVerbose = new ApiFactory($metadata, $guzzleAdapterVerbose);


$photosetAll = array();
$photosetAllLower = array();

$xml = null;
try{
	$xml = $apiFactory->call('flickr.photosets.getList');
}
catch(Exception $e){
	$log->error('[main] getList: '.$e->getMessage());
	exit(1);
}

foreach($xml->photosets->photoset as $n => $photoset){
	if($exit) break;
	
	$photosetAll[(int)$photoset->attributes()->id] = (string)$photoset->title;
	$photosetAllLower[(int)$photoset->attributes()->id] = strtolower((string)$photoset->title);
}

$photosets = array();
$photosetsNew = array();
foreach($photosetNames as $photosetTitle){
	$id = 0;
	
	foreach($photosetAllLower as $photosetAllId => $photosetAllTitle){
		if(strtolower($photosetTitle) == $photosetAllTitle){
			$id = $photosetAllId;
			break;
		}
	}
	if($id){
		$photosets[] = $id;
	}
	else{
		$photosetsNew[] = $photosetTitle;
	}
}


foreach($getopt->getOperands() as $filePath){
	$size = filesize($filePath);
	$sizeLen = strlen(number_format($size));
	
	$log->info("[file] upload '".$filePath."'  ".siPrefix($size));
	
	$pathinfo = pathinfo($filePath);
	
	try{
		$converter = new Converter();
		$converter->registerExitVar($exit);
		
		$outputFilePath = $pathinfo['dirname'].'/'.$pathinfo['basename'].'.bmp';
		$log->info('[file] cover ...');
		$converter->cover($filePath, $outputFilePath);
		
		
		if(file_exists($outputFilePath)){
			$filePath = $outputFilePath;
			$pathinfo = pathinfo($filePath);
			
			$log->info('[file] cover OK');
		}
		else{
			$log->error('[file] cover FAILED');
			$fileErrors++;
			$filesFailed[] = $file;
			continue;
		}
	}
	catch(Exception $e){
		$log->error('[file] cover FAILED: '.$e->getMessage());
		$fileErrors++;
		$filesFailed[] = $file;
		continue;
	}
	
	
	$dataLenPrev = 0;
	$timePrev = time();
	
	$fileName = null;
	$fileExt = '';
	
	if(isset($pathinfo['filename'])){
		$fileName = $pathinfo['filename'];
	}
	if(isset($pathinfo['extension'])){
		$fileExt = strtolower($pathinfo['extension']);
	}
	
	$photoId = 0;
	$stat = '';
	$successful = false;
	
	$xml = null;
	try{
		$xml = $apiFactoryVerbose->upload($filePath, $fileName, $getopt->getOption('description'), $getopt->getOption('tags'));
		print str_repeat(' ', $termLinePrevLen)."\r";
	}
	catch(Exception $e){
		$log->error('[file] upload: '.$e->getMessage());
		$xml = null;
	}
	
	if($xml){
		$photoId = isset($xml->photoid) ? (int)$xml->photoid : 0;
		$stat = isset($xml->attributes()->stat) ? strtolower((string)$xml->attributes()->stat) : '';
		$successful = $stat == 'ok' && $photoId != 0;
	}
	
	$logLine = '';
	if($successful){
		$logLine = 'OK';
		$totalFilesUploaded++;
		
		$logFilesSuccessful->info(''.$file);
	}
	else{
		$logLine = 'FAILED';
		$fileErrors++;
		$filesFailed[] = $file;
	}
	$log->info('[file] status: '.$logLine.' - ID '.$photoId);
	
	if($successful){
		
		if($photosetsNew){
			foreach($photosetsNew as $photosetTitle){
				$log->info('[photoset] create '.$photosetTitle.' ... ');
				
				$photosetId = 0;
				$xml = null;
				try{
					$xml = $apiFactory->call('flickr.photosets.create', array('title' => $photosetTitle, 'primary_photo_id' => $photoId));
				}
				catch(Exception $e){
					$log->critical('[photoset] create '.$photosetTitle.' FAILED: '.$e->getMessage());
					exit(1);
				}
				if($xml){
					if( (string)$xml->attributes()->stat == 'ok' ){
						$photosetId = (int)$xml->photoset->attributes()->id;
						$photosets[] = $photosetId;
						
						$log->info('[photoset] create '.$photosetTitle.' OK - ID '.$photosetId);
					}
					else{
						$code = (int)$xml->err->attributes()->code;
						$log->critical('[photoset] create '.$photosetTitle.' FAILED: '.$code);
						exit(1);
					}
				}
				else{
					$log->critical('[photoset] create '.$photosetTitle.' FAILED');
					exit(1);
				}
			}
			$photosetsNew = null;
		}
		
		if(count($photosets)){
			$log->info('[file] add to sets ... ');
			
			$logLine = '';
			foreach($photosets as $photosetId){
				$logLine .= substr($photosetId, -5).' ';
				
				$xml = null;
				try{
					$xml = $apiFactory->call('flickr.photosets.addPhoto', array('photoset_id' => $photosetId, 'photo_id' => $photoId));
				}
				catch(Exception $e){
					$log->critical('[file] add to sets FAILED: '.$e->getMessage());
					exit(1);
				}
				if($xml){
					if($xml->attributes()->stat == 'ok'){
						$logLine .= 'OK';
					}
					else{
						if(isset($xml->err)){
							$code = (int)$xml->err->attributes()->code;
							if($code == 3){
								$logLine .= 'OK';
							}
							else{
								$log->critical('[file] add to sets FAILED: '.$code);
								exit(1);
							}
						}
						else{
							$log->critical('[file] add to sets FAILED');
							exit(1);
						}
					}
				}
			}
			$log->info('[file] added to sets: '.$logLine);
		}
		
	}
}

$log->info('[main] total uploaded: '.($uploadedTotal > 0 ? siPrefix($uploadedTotal) : 0));
$log->info('[main] total files:    '.$totalFiles);
$log->info('[main] files uploaded: '.$totalFilesUploaded);
$log->info('[main] files failed:   '.$fileErrors.( count($filesFailed) ? "\n".join("\n", $filesFailed) : '' ));

$log->info('end');
$logFilesSuccessful->info('end');
