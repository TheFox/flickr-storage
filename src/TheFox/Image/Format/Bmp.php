<?php

namespace TheFox\Image\Format;

class Bmp{
	
	const BF_FILE_HEADER_SIZE = 14;
	const BF_INFO_HEADER_SIZE = 40;
	const BF_BI_RGB = 0;
	const BF_SIZE_MAX = 0xffffffff;
	
	private $bfType = '';
	private $bfSize = 0;
	private $biBitCount = 0;
	private $biCompression = 0;
	private $biSizeImage = 0;
	private $biWidth = 0;
	private $biHeight = 0;
	
	public function __construct(){
		$this->bfType = 'BM';
		$this->biBitCount = 24;
		$this->biCompression = static::BF_BI_RGB;
	}
	
	public function setContentSize($size){
		$this->biSizeImage = $size;
		
		$this->bfSize = $this->getFileHeaderSize() + $this->getInfoHeaderSize() + $this->biSizeImage;
		if($this->bfSize > static::BF_SIZE_MAX){
			$this->bfSize = static::BF_SIZE_MAX;
		}
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
			.pack('V', 0) // biSizeImage
			.pack('V', 0) // biXPelsPerMeter
			.pack('V', 0) // biYPelsPerMeter
			.pack('V', 0) // biClrUsed
			.pack('V', 0) // biClrImportant
			;
	}
	
}
