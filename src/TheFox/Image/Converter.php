<?php

namespace TheFox\Image;

use TheFox\Image\Format\Bmp;

class Converter{
	
	const BUFFER_LEN = 2048;
	
	private $exit = 0;
	private $destFormat = 'bmp';
	
	public function __construct($format = 'bmp'){
		$format = strtolower($format);
		
		if($format != 'bmp'){
			throw new \Exception('Unsupported format.');
		}
		
		$this->destFormat = $format;
	}
	
	public function convert($fileInput, $fileOutput){
		
		if(!file_exists($fileInput)){
			throw new \Exception('File not found: '.$fileInput);
		}
		
		if(file_exists($fileOutput)){
			#throw new \Exception('File exists already: '.$fileOutput);
		}
		
		$format = null;
		if($this->destFormat == 'bmp'){
			$format = new Bmp();
		}
		
		if($format){
			if($oh = fopen($fileOutput, 'w+b')){
				
				if($ih = fopen($fileInput, 'rb')){
					$size = filesize($fileInput);
					
					list($x, $y, $remainder) = $format->calcSquareResolutionBySize($size);
					
					$format->setBiWidth($x);
					$format->setBiHeight($y);
					$format->setContentSize($size + $remainder);
					
					fwrite($oh, $format->getFileHeader());
					fwrite($oh, $format->getInfoHeader());
					
					$readed = 0;
					$t = time();
					
					while(!feof($ih)){
						if($this->exit) break;
						
						$buffer = fread($ih, static::BUFFER_LEN);
						$bufferLen = strlen($buffer);
						$readed += $bufferLen;
						
						fwrite($oh, $buffer, $bufferLen);
						
						$ct = time();
						if($t != $ct){
							$t = $ct;
							print "read ".siPrefix($readed)."\n";
						}
						
					}
					for($n = 0; $n < $remainder; $n++){
						fwrite($oh, chr(0));
					}
					
					
					#fwrite($oh, chr(0xff).chr(0xff).chr(0xff).chr(0xff).chr(0xff).chr(0xff) .chr(0).chr(0) );
					#fwrite($oh, chr(0).chr(0).chr(0).chr(0).chr(0).chr(0) .chr(0).chr(0) );
					
					
					
					fclose($ih);
				}
				else{
					throw new \Exception("Can't open input file '".$fileInput."'.");
				}
				
				fclose($oh);
			}
			else{
				throw new \Exception("Can't open output file '".$fileOutput."'.");
			}
		}
		else{
			throw new \Exception('Format not found.');
		}
		
	}
	
	public function registerExitVar(&$exit){
		$this->exit = &$exit;
	}
	
}
