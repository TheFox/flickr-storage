<?php

namespace TheFox\Image;

use TheFox\Image\Format\Bmp;

class Converter{
	
	const BUFFER_LEN = 2048;
	const CONVERTER_VERSION = 1;
	const CONVERTER_HEADER_SIZE = 12;
	
	private $exit = 0;
	private $destFormat = 'bmp';
	private $remainder = 0;
	
	public function __construct($format = 'bmp'){
		$format = strtolower($format);
		
		if($format != 'bmp'){
			throw new \Exception('Unsupported format.');
		}
		
		$this->destFormat = $format;
	}
	
	public function getHeaderSize(){
		return static::CONVERTER_HEADER_SIZE;
	}
	
	public function getHeader(){
		return 'TFIC' // TheFox Image Converter
			.pack('v', static::CONVERTER_VERSION) // Version
			.pack('v', $this->getHeaderSize()) // Header size
			.pack('V', $this->remainder) // Image padding
			;
	}
	
	public function readHeader($raw){
		#var_export( $raw ); print "\n";
		
		if($raw[0].$raw[1].$raw[2].$raw[3] != 'TFIC'){
			throw new \Exception('Not TFIC format.');
		}
		
		$tmp = unpack('v', $raw[4].$raw[5]);
		#var_export( $tmp ); print "\n";
		$version = 0;
		if(isset($tmp[1])){
			$version = $tmp[1];
		}
		
		$tmp = unpack('v', $raw[6].$raw[7]);
		#var_export( $tmp ); print "\n";
		$headerSize = 0;
		if(isset($tmp[1])){
			$headerSize = $tmp[1];
		}
		
		$tmp = unpack('V', $raw[8].$raw[9].$raw[10].$raw[11]);
		#var_export( $tmp ); print "\n";
		$this->remainder = 0;
		if(isset($tmp[1])){
			$this->remainder = $tmp[1];
		}
		
	}
	
	public function cover($fileInput, $fileOutput){
		
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
			if($oh = fopen($fileOutput, 'wb')){
				
				if($ih = fopen($fileInput, 'rb')){
					$size = filesize($fileInput);
					
					list($x, $y, $remainder) = $format->calcSquareResolutionBySize($size);
					$this->remainder = $remainder;
					
					$format->setBiWidth($x);
					$format->setBiHeight($y);
					$format->setContentSize($this->getHeaderSize() + $size + $this->remainder);
					
					fwrite($oh, $format->getFileHeader());
					fwrite($oh, $format->getInfoHeader());
					
					fwrite($oh, $this->getHeader());
					#exit();
					
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
					
					return true;
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
		
		return false;
	}
	
	public function recover($fileInput, $fileOutput){
		
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
			if($oh = fopen($fileOutput, 'wb')){
				
				if($ih = fopen($fileInput, 'rb')){
					$size = filesize($fileInput);
					
					$bfOffBits = $format->readFileHeader(fread($ih, $format->getFileHeaderSize()));
					$biSizeImage = $format->readInfoHeader(fread($ih, $format->getInfoHeaderSize()));
					print "biSizeImage: ".$biSizeImage."\n";
					#print "bfSize: ".$format->getBfSize()."\n";
					if($bfOffBits != ftell($ih)){
						throw new \Exception("Wrong posistion: bfOffBits=$bfOffBits, ftell=".ftell($ih)."");
					}
					
					$this->readHeader(fread($ih, $this->getHeaderSize()));
					print "remainder: ".$this->remainder."\n";
					print "tell: ".ftell($ih)."\n";
					
					
					$readed = 0;
					$t = time();
					$bytesToReadLeft = $biSizeImage - $this->getHeaderSize() - $this->remainder;
					#$bytesToReadLeft = $biSizeImage;
					
					while($bytesToReadLeft > 0){
						
						$bytesToRead = static::BUFFER_LEN;
						
						#print "read  ".ftell($ih).", ".$bytesToReadLeft.", ".$bytesToRead."\n";
						
						if($bytesToReadLeft < $bytesToRead){
							$bytesToRead = $bytesToReadLeft;
						}
						$bytesToReadLeft -= $bytesToRead;
						
						#print "read  ".ftell($ih).", ".$bytesToReadLeft.", ".$bytesToRead."\n";
						
						$buffer = fread($ih, $bytesToRead);
						$bufferLen = strlen($buffer);
						$readed += $bufferLen;
						
						fwrite($oh, $buffer, $bufferLen);
						
						$ct = time();
						if($t != $ct){
							$t = $ct;
							print "read ".siPrefix($readed)."\n";
						}
						
						#sleep(1);
					}
					
					print "tell: ".ftell($ih)."\n";
					fread($ih, $this->remainder);
					print "tell: ".ftell($ih)."\n";
					
					fread($ih, 1);
					print "tell: ".ftell($ih)."\n";
					
					if(!feof($ih)){
						throw new \Exception("Input handler is not at the end of the file.");
					}
					
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
