<?php

namespace TheFox\OAuth\Common\Http\Client;

use OAuth\Common\Http\Client\AbstractClient;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\UriInterface;
use Guzzle\Http\Client;

class GuzzleStreamClient extends AbstractClient{
	
	public function retrieveResponse(UriInterface $endpoint, $requestBody, array $extraHeaders = array(), $method = 'POST'){
		$responseHtml = '';
		
		$method = strtoupper($method);
		
		$client = new Client();
		$headers = array('Connection' => 'close');
		$headers = array_merge($headers, $extraHeaders);
		
		#print "requestBody\n"; var_export($requestBody); print "\n";
		
		if($method == 'POST'){
			$request = $client->post($endpoint->getAbsoluteUri(), $headers, $requestBody);
			
			#print "request\n"; var_export($request); print "\n";
			#fgets(STDIN);
			
			$response = $request->send();
		}
		elseif($method == 'GET'){
			throw new \InvalidArgumentException('"GET" request not implemented.');
		}
		
		if(!$response->isSuccessful()){
			throw new TokenResponseException('Failed to request token.');
		}
		
		$responseHtml = (string)$response->getBody();
		
		#print "responseHtml: '$responseHtml'\n";
		
		return $responseHtml;
	}
	
}
