<?php

# http://en.wikipedia.org/wiki/BMP_file_format

namespace TheFox\Image\Format;

class Bmp{
	
	const BF_FILE_HEADER_SIZE = 14;
	const BF_INFO_HEADER_SIZE = 40;
	const BF_BI_RGB = 0;
	const BF_SIZE_MAX = 0xffffffff;
	
	private $bfType = '';
	private $bfSize = 0;
	private $bfOffBits = 0;
	private $biWidth = 0;
	private $biHeight = 0;
	private $biBitCount = 0;
	private $biCompression = 0;
	private $biSizeImage = 0;
	
	
	public function __construct(){
		$this->bfType = 'BM';
		$this->biBitCount = 24;
		$this->biCompression = static::BF_BI_RGB;
	}
	
	public function getBfSize(){
		return $this->bfSize;
	}
	
	public function setContentSize($size){
		print "content size: $size\n";
		$this->biSizeImage = $size;
		
		$this->bfSize = $this->getFileHeaderSize() + $this->getInfoHeaderSize() + $this->biSizeImage;
		if($this->bfSize > static::BF_SIZE_MAX){
			$this->bfSize = static::BF_SIZE_MAX;
		}
		
		#printf("bfSize = %d %02x\n", $this->bfSize, $this->bfSize);
		#printf("   getFileHeaderSize = %d %02x\n", $this->getFileHeaderSize(), $this->getFileHeaderSize());
		#printf("   getInfoHeaderSize = %d %02x\n", $this->getInfoHeaderSize(), $this->getInfoHeaderSize());
		#printf("   biSizeImage = %d %02x\n", $this->biSizeImage, $this->biSizeImage);
	}
	
	public function setBiWidth($w){
		$this->biWidth = $w;
	}
	
	public function setBiHeight($h){
		$this->biHeight = $h;
	}
	
	public function getFileHeaderSize(){
		return static::BF_FILE_HEADER_SIZE;
	}
	
	public function getFileHeader(){
		return ''
			.$this->bfType // bfType
			.pack('V', $this->bfSize) // bfSize
			.pack('xxxx') // bfReserved
			.pack('V', $this->getFileHeaderSize() + $this->getInfoHeaderSize()) // bfOffBits
		;
	}
	
	public function readFileHeader($raw){
		#var_export( $raw ); print "\n";
		
		if(strlen($raw) < $this->getFileHeaderSize()){
			throw new \Exception("Raw data length is < ".$this->getFileHeaderSize()." byte.");
		}
		
		$tmp = unpack('V', $raw[2].$raw[3].$raw[4].$raw[5]);
		if(isset($tmp[1])){
			$this->bfSize = $tmp[1];
		}
		
		$tmp = unpack('V', $raw[10].$raw[11].$raw[12].$raw[13]);
		if(isset($tmp[1])){
			$this->bfOffBits = $tmp[1];
		}
		
		#var_export( $tmp ); print "\n";
		#var_export( unpack('V', $raw[5].$raw[4].$raw[3].$raw[2]) ); print "\n";
		#var_export( unpack('v', $raw[0]) ); print "\n";
		#var_export( unpack('CCCC', 'AB') ); print "\n";
		
		return $this->bfOffBits;
	}
	
	public function getInfoHeaderSize(){
		return static::BF_INFO_HEADER_SIZE;
	}
	
	public function getInfoHeader(){
		return ''
			.pack('V', static::BF_INFO_HEADER_SIZE) // biSize
			.pack('V', $this->biWidth) // biWidth
			.pack('V', $this->biHeight) // biHeight
			.pack('v', 1) // biPlanes
			.pack('v', $this->biBitCount) // biBitCount
			.pack('V', $this->biCompression) // biCompression
			.pack('V', $this->biSizeImage) // biSizeImage
			.pack('V', 0) // biXPelsPerMeter
			.pack('V', 0) // biYPelsPerMeter
			.pack('V', 0) // biClrUsed
			.pack('V', 0) // biClrImportant
		;
	}
	
	public function readInfoHeader($raw){
		if(strlen($raw) < $this->getInfoHeaderSize()){
			throw new \Exception("Raw data length is < ".$this->getInfoHeaderSize()." byte.");
		}
		
		$tmp = unpack('V', $raw[20].$raw[21].$raw[22].$raw[23]);
		if(isset($tmp[1])){
			$this->biSizeImage = $tmp[1];
			#var_export( $tmp[1] ); print "\n";
		}
		
		return $this->biSizeImage;
	}
	
	public function calcSquareResolutionBySize($size){
		$x = 0;
		$y = 0;
		$remainder = 0;
		
		$pixel = 0;
		if($size % 3 == 0){
			$pixel = $size / 3;
		}
		else{
			$pixel = (int)($size / 3) + $size % 3;
		}
		
		$sqrt = sqrt($pixel);
		$sqrtMin = (int)$sqrt;
		$sqrtMax = (int)$sqrt + 1;
		
		#print "sqrt: ".$sqrt." ".$sqrtMin."/".$sqrtMax." \n";
		#print "min * min: $sqrtMin * $sqrtMin = ".($sqrtMin * $sqrtMin)." ".($sqrtMin * $sqrtMin - $pixel)."\n";
		#print "min * max: $sqrtMin * $sqrtMax = ".($sqrtMin * $sqrtMax)." ".($sqrtMin * $sqrtMax - $pixel)."\n";
		#print "max * max: $sqrtMax * $sqrtMax = ".($sqrtMax * $sqrtMax)." ".($sqrtMax * $sqrtMax - $pixel)."\n";
		
		if($sqrtMin * $sqrtMin - $pixel >= 0){
			#print "min * min: $sqrtMin * $sqrtMin = ".($sqrtMin * $sqrtMin)." ".($sqrtMin * $sqrtMin - $pixel)."\n";
			$x = $y = $sqrtMin;
		}
		elseif($sqrtMin * $sqrtMax - $pixel >= 0){
			#print "min * max: $sqrtMin * $sqrtMax = ".($sqrtMin * $sqrtMax)." ".($sqrtMin * $sqrtMax - $pixel)."\n";
			$x = $sqrtMin;
			$y = $sqrtMax;
		}
		elseif($sqrtMax * $sqrtMax - $pixel >= 0){
			#print "max * max: $sqrtMax * $sqrtMax = ".($sqrtMax * $sqrtMax)." ".($sqrtMax * $sqrtMax - $pixel)."\n";
			$x = $y = $sqrtMax;
		}
		
		$remainder = $x * $y * 3 - $size;
		
		$paddingPerLine = 4 - (($x * 3) % 4); // The size of each row is rounded up to a multiple of 4 bytes (a 32-bit DWORD) by padding.
		$padding = $paddingPerLine * $y;
		
		#print "raw size: $size\n";
		#print "pixel: ".$pixel."\n";
		#print "data len: ".($x * $y * 3)."\n";
		#print "remainder: ".$remainder." (data len - raw size)\n";
		#print "byte    per line: ".($x * 3)."\n";
		#print "padding per line: ".$paddingPerLine."\n";
		#print "padding: ".$padding." ($x lines * padding per line)\n";
		#print "\n\n";
		
		return array($x, $y, $remainder + $padding);
	}
	
}
