<?php
/**
  *
  * @author http://www.e-worksmedia.com
  * @version 1.0.0
  *
  * LICENSE: BSD 3-Clause
  *
  * Copyright (c) 2013, e-works media, inc.
  * All rights reserved.
  * 
  * Redistribution and use in source and binary forms,
  * with or without modification, are permitted provided
  * that the following conditions are met:
  * 
  * -Redistributions of source code must retain the above
  * copyright notice, this list of conditions and the
  * following disclaimer.
  * 
  * -Redistributions in binary form must reproduce the
  * above copyright notice, this list of conditions and
  * the following disclaimer in the documentation and/or
  * other materials provided with the distribution.
  * 
  * -Neither the name of e-works media, inc. nor the names
  * of its contributors may be used to endorse or promote
  * products derived from this software without specific
  * prior written permission.
  * 
  * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS
  * AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED
  * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
  * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A
  * PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL
  * THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY
  * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
  * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
  * PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF
  * USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
  * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
  * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
  * OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
  * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
  * 
**/

require_once __DIR__ . '/first-party/config.inc.php';
require_once __DIR__ . '/aws-sdk/sdk.class.php';
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\HttpFoundation\Request;
use RestProxy\RestProxy;
use RestProxy\CurlWrapper;

$dynamodb = new AmazonDynamoDB();
$servers = array();
$server = array();
$session = '';

$available_servers = $dynamodb->scan(array(
	'TableName' => 'FilterServers', 
	'ScanFilter' => array( 
		'status' => array(
			'ComparisonOperator' => AmazonDynamoDB::CONDITION_EQUAL,
			'AttributeValueList' => array(
				array( AmazonDynamoDB::TYPE_STRING => 'active' )
			)
		)
	)
));

foreach ($available_servers->body->Items as $item){
	array_push($servers, array(
		'id'		=>	(int) $item->Id->{AmazonDynamoDB::TYPE_NUMBER},
		'host_name'	=>	(string) $item->host_name->{AmazonDynamoDB::TYPE_STRING},
		'status'	=>	(string) $item->status->{AmazonDynamoDB::TYPE_STRING}
	));
}

if(strstr($_SERVER['REQUEST_URI'], 'options')){
	// if API call is 'options' pick any server to handle request
	$server = array_rand($servers, 1);
	$server = $servers[$server];
} else if(strstr($_SERVER['REQUEST_URI'], 'initiate')){
	// new media
	// generate unique session, append to request, store session/server connection
	$session = getHash(15);
	$_SERVER['REQUEST_URI'] = $_SERVER['REQUEST_URI'] . '/' . $session;
	$_SERVER['REDIRECT_URL'] = $_SERVER['REDIRECT_URL'] . '/' . $session;
	$server = array_rand($servers, 1);
	$server = $servers[$server];
	$queue = new CFBatchRequest();
	$queue->use_credentials($dynamodb->credentials);
	$dynamodb->batch($queue)->put_item(array(
		'TableName' => 'Sessions',
		'Item' => array(
			'server'	=>	array( AmazonDynamoDB::TYPE_STRING => $server['id'] ),
			'session'	=>	array( AmazonDynamoDB::TYPE_STRING => $session )
		)
	));
	$response = $dynamodb->batch_write_item(array(
		'RequestItems' => array(
			'Sessions' => array(
				array(
					'PutRequest' => array(
						'Item' => $dynamodb->attributes(array(
							'session'	=> $session,
							'server'	=> $server['id']
						))
					)
				)
			)
		)
	));
	if ($response->isOK()){
		// let request flow
	} else {
		header('HTTP/1.0 400 ' . $http_codes[400], true, 400);
		print json_encode(array('code'=>114, 'message'=>'Unable to associate request'));
		exit;
	}
} else {
	// look up session in db to determine forwarding address
	$session = end(explode('/', $_SERVER['REQUEST_URI']));
	$matching_session = $dynamodb->get_item(array(
		'TableName' => 'Sessions',
		'Key' => $dynamodb->attributes(array(
			'HashKeyElement'  => $session
		)),
		'ConsistentRead' => 'true'
	));
	// check if session is found
	if($matching_session->isOK()){
		$server = NULL;
		$matched_session = array(
			'server'	=>	(int) $matching_session->body->Item->server->{AmazonDynamoDB::TYPE_NUMBER},
			'session'	=>	(string) $matching_session->body->Item->session->{AmazonDynamoDB::TYPE_STRING}
		);
		for($i = 0; $i < count($servers); $i++){
			if($matched_session['server'] == $servers[$i]['id']){
				$server = $servers[$i];	
			}
		}
		if($server == NULL){
			// something went wrong, the server wasn't found
			header('HTTP/1.0 400 ' . $http_codes[400], true, 400);
			print json_encode(array('code'=>114, 'message'=>$matched_session));
			exit;	
		}
	} else {
		// lookup failed	
		header('HTTP/1.0 400 ' . $http_codes[400], true, 400);
		print json_encode(array('code'=>115, 'message'=>'Session not found'));
		exit;
	}
}

$request = Request::createFromGlobals();
$proxy = new RestProxy(
	$request,
	new CurlWrapper(),
	$_FILES
);
$proxy->register('filtrme', $server['host_name']);

try {
	$proxy->run();
} catch(Exception $e){
	header('Content-type: application/json');
	header('HTTP/1.0 ' . $e->getCode() . ' ' . $http_codes[$e->getCode()], true, $e->getCode());
	print $e->getMessage();
	exit;
}

foreach($proxy->getHeaders() as $header) {
	header($header);
}

print $proxy->getContent();

?>