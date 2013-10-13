<?php

namespace TheFox\Image;

class Converter{
	
	private $destFormat = 'bmp';
	
	public function __construct($format = 'bmp'){
		$format = strtolower($format);
		
		if($format != 'bmp'){
			throw new \Exception('Unsupported format.');
		}
		
	}
	
	public function convert(){
		
	}
	
}
