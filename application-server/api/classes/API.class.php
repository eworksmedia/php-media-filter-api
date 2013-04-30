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

include_once 'slim/Slim.php';
include_once 'aws-sdk/sdk.class.php';
include_once 'classes/FilterFactory.class.php';

class API {
	/**
	 * @contstant string SALT
	 */
	const SALT = 'RANDOM_STRING';
	/**
	 * @contstant string SESSION_EXPIRE_TIME
	 * as days
	 */
	const SESSION_EXPIRE_TIME = '10';
	/**
	 * @var class $rest
	 */
	private static $rest;
	/**
	 * @var class $database AmazonDynamoDB
	 */
	private static $database;
	/**
	 * @var string $apitoken
	 */
	private static $apitoken;
	/**
	 * @var string $php
	 */
	private static $php = '/usr/bin/php';
	/**
	 * @var string $ffmpeg
	 */
	private static $ffmpeg = '/usr/local/bin/ffmpeg';
	/**
	 * @var string $imageMagickBase
	 */
	private static $imageMagickBase = '/usr/local/bin/';
	/**
	 * @var string $server_root
	 */
	private static $server_root = '/var/www/html';
	/**
	 * @var string $sessions_dir_name
	 */
	private static $sessions_dir_name = 'sessions';
	/**
	 * @var string $uploaded_dir_name
	 */
	private static $uploaded_dir_name = 'uploaded';
	/**
	 * @var string $encoded_dir_name
	 */
	private static $encoded_dir_name = 'encoded';
	/**
	 * @var string $original_item_name
	 */
	private static $original_item_name = 'original';
	/**
	 * @var string $working_item_name
	 */
	private static $working_item_name = 'filtered';
	/**
	 * @var string $templates_dir_name
	 */
	private static $templates_dir_name = 'templates';
	/**
	 * @var string $storage_dir
	 */
	private static $storage_dir;
	/**
	 * @var string $session_dir
	 */
	private static $session_dir;
	/**
	 * @var string $uploaded_dir
	 */
	private static $uploaded_dir;
	/**
	 * @var string $encoded_dir
	 */
	private static $encoded_dir;
	/**
	 * @var string $file_extension
	 */
	private static $file_extension;
	/**
	 * @var string $unique
	 */
	private static $unique;
	
	/**
	 * __construct
	 *
	 * @return void
	 * @throws Exception
	 */
	public function __construct() {
		throw new Exception('API is a static class. No instances can be created.');
	}	
	
	/**
	 * Method for initiating the API Class
	 *
	 */
	public static function init() {
		self::setRest(new Slim(array('debug' =>	false, 'mode' => 'production')));
		self::setDatabase(new AmazonDynamoDB());
		self::setStorageDirectory(self::getServerRoot() . '/' . self::getSessionsDirectoryName());
		FilterFactory::setCommandBase(self::getImageMagickBase());
		FilterFactory::setFiltersBorderDirectory(self::getServerRoot() . '/worker/filters/borders/');
		FilterFactory::setFiltersColormapDirectory(self::getServerRoot() . '/worker/filters/colormaps/');
		
		self::getRest()->get('/options', 'API::options');
		/* photo endpoints */
		self::getRest()->post('/photo/initiate/:id', 'API::photoInitiate');
		self::getRest()->post('/photo/rotate/:id', 'API::photoRotate');
		self::getRest()->post('/photo/filter/:id', 'API::photoFilter');
		self::getRest()->post('/photo/:id', 'API::photoInformation');
		self::getRest()->delete('/photo/:id', 'API::photoDelete');
		/* end */
		/* video endpoints */
		self::getRest()->post('/video/initiate/:id', 'API::videoInitiate');
		self::getRest()->post('/video/rotate/:id', 'API::videoRotate');
		self::getRest()->post('/video/filterpreview/:id', 'API::videoFilterPreview');
		self::getRest()->post('/video/filter/:id', 'API::videoFilter');
		self::getRest()->post('/video/filterprogress/:id', 'API::videoFilterProgress');
		self::getRest()->post('/video/filtercancel/:id', 'API::videoFilterCancel');
		self::getRest()->post('/video/images/:id', 'API::videoImages');	
		self::getRest()->post('/video/:id', 'API::videoInformation');
		self::getRest()->delete('/video/:id', 'API::videoDelete');
		/* end */	
		/*
		 * TODO: re-implement account storage method taking into
		 *       consideration sessions being on multiple servers
		 * 
		 * self::getRest()->post('/calculatestorage/account', 'API::calculateAccountStorage');
		 */
		self::getRest()->post('/calculatestorage/session/:id', 'API::calculateSessionStorage');
		self::getRest()->run();
	}
	
	/**
	 * Rest Method for listing filter options
	 *
	 * @method POST
	 */
	public static function options() {
		$request = Slim::getInstance()->request();
		self::getRest()->contentType('application/json');
		self::setAPIToken($request->headers('X-Mashape-User-PublicKey'));
		if(!self::isValidUser()) {
			self::getRest()->halt(403, json_encode(array('code'=>1, 'message'=>'Invalid authentication')));
		}
		$filters = array();
		$filters_raw = self::getDatabase()->scan(array(
			'TableName' => 'Filters', 
			'ScanFilter' => array( 
				'status' => array(
					'ComparisonOperator' => AmazonDynamoDB::CONDITION_EQUAL,
					'AttributeValueList' => array(
						array( AmazonDynamoDB::TYPE_STRING => 'active' )
					)
				)
			)
		));
		foreach ($filters_raw->body->Items as $item){
			if((int) $item->private->{AmazonDynamoDB::TYPE_NUMBER}){
				if(str_replace('=', '', self::maskString((string) $item->owner->{AmazonDynamoDB::TYPE_STRING}, self::SALT)) != self::getAPIToken()) continue;
			}
			array_push($filters, array(
				'name'			=>	(string) $item->name->{AmazonDynamoDB::TYPE_STRING},
				'filter_key'	=>	(string) $item->filter_key->{AmazonDynamoDB::TYPE_STRING},
				'required'		=>	unserialize((string) $item->required->{AmazonDynamoDB::TYPE_STRING})
			));
		}
		$fonts = array();
		$fonts_raw = self::getDatabase()->scan(array(
			'TableName' => 'Fonts', 
			'ScanFilter' => array( 
				'status' => array(
					'ComparisonOperator' => AmazonDynamoDB::CONDITION_EQUAL,
					'AttributeValueList' => array(
						array( AmazonDynamoDB::TYPE_STRING => 'active' )
					)
				)
			)
		));
		foreach ($fonts_raw->body->Items as $item){
			array_push($fonts, array(
				'name'		=>	(string) $item->name->{AmazonDynamoDB::TYPE_STRING},
				'font_key'	=>	(string) $item->font_key->{AmazonDynamoDB::TYPE_STRING}
			));
		}
		$response = array();
		$response['success'] = true;
		$response['filters'] = $filters;
		$response['fonts'] = $fonts;
		echo json_encode($response);
		exit;
	}
	
	/**
	 * Rest Method for initiating a filter session
	 *
	 * @type video
	 * @method POST
	 * @required session id 
	 */
	public static function videoInitiate($id) {
		$request = Slim::getInstance()->request();
		self::getRest()->contentType('application/json');
		self::setUnique($id);
		self::setAPIToken($request->headers('X-Mashape-User-PublicKey'));
		self::setSessionDirectory(self::getStorageDirectory() . '/' . self::getAPIToken() . '/' . self::getUnique());
		if(!self::isValidUser()) {
			self::getRest()->halt(403, json_encode(array('code'=>1, 'message'=>'Invalid authentication')));
		}
		if(isset($_FILES['Filedata'])){ // requests sent from Flash Player
			$_FILES['file'] = $_FILES['Filedata'];
		}
		if(!isset($_FILES["file"]["tmp_name"])) {
			self::getRest()->halt(400, json_encode(array('code'=>2,'message'=>'Missing Required Parameters')));
		}
		if((!isset($_FILES["file"]["name"]) || !strstr($_FILES["file"]["name"], '.')) && !isset($_POST['name'])) {
			self::getRest()->halt(400, json_encode(array('code'=>2,'message'=>'Missing Required Parameters')));
		}
		self::setUploadedDirectory(self::getSessionDirectory() . '/' . self::getUploadedDirectoryName());
		self::setEncodedDirectory(self::getSessionDirectory() . '/' . self::getEncodedDirectoryName());
		$filename = isset($_FILES["file"]["name"]) && strstr($_FILES["file"]["name"], '.') ? $_FILES["file"]["name"] : $_POST['name'];
		self::setFileExtension(strtolower(pathinfo($filename, PATHINFO_EXTENSION)));
		if(!is_dir(self::getSessionDirectory())){
			if(!mkdir(self::getSessionDirectory(), 0777, true)){
				self::getRest()->halt(400, json_encode(array('code'=>100,'message'=>'Unable to fulfill request')));	
			}
		}
		if(!is_dir(self::getUploadedDirectory())){
			if(!mkdir(self::getUploadedDirectory(), 0777, true)){
				self::getRest()->halt(400, json_encode(array('code'=>100,'message'=>'Unable to fulfill request')));
			}
		}
		if(!is_dir(self::getEncodedDirectory())){
			if(!mkdir(self::getEncodedDirectory(), 0777, true)){
				self::getRest()->halt(400, json_encode(array('code'=>100,'message'=>'Unable to fulfill request')));
			}
		}
		if(!is_dir(self::getEncodedDirectory() . '/images')){
			if(!mkdir(self::getEncodedDirectory() . '/images', 0777, true)){
				self::getRest()->halt(400, json_encode(array('code'=>100,'message'=>'Unable to fulfill request')));
			}
		}
		if(!copy(self::getServerRoot() . '/' . self::getTemplatesDirectoryName() . '/progress.log', self::getEncodedDirectory() . '/progress.log')){
			self::getRest()->halt(400, json_encode(array('code'=>102,'message'=>'Unable to fulfill request')));	
		}
		if(!chmod(self::getEncodedDirectory() . '/progress.log', 0777)){
			self::getRest()->halt(400, json_encode(array('code'=>101,'message'=>'Unable to fulfill request')));	
		}
		if(!copy(self::getServerRoot() . '/' . self::getTemplatesDirectoryName() . '/ffmpeg.log', self::getEncodedDirectory() . '/ffmpeg.log')){
			self::getRest()->halt(400, json_encode(array('code'=>102,'message'=>'Unable to fulfill request')));	
		}
		if(!chmod(self::getEncodedDirectory() . '/ffmpeg.log', 0777)){
			self::getRest()->halt(400, json_encode(array('code'=>101,'message'=>'Unable to fulfill request')));
		}
		if(!file_put_contents(self::getUploadedDirectory() . '/ext', self::getFileExtension())){
			self::getRest()->halt(400, json_encode(array('code'=>104,'message'=>'Unable to fulfill request')));	
		}
		if(!file_put_contents(self::getSessionDirectory() . '/expire', strtotime('+' . self::SESSION_EXPIRE_TIME . ' days 12:00am', time()))){
			self::getRest()->halt(400, json_encode(array('code'=>104,'message'=>'Unable to fulfill request')));	
		}
		if(!move_uploaded_file($_FILES["file"]["tmp_name"], self::getUploadedDirectory() . '/' . self::getOriginalItemName() . '.' . self::getFileExtension())){
			self::getRest()->halt(400, json_encode(array('code'=>105,'message'=>'Unable to fulfill request')));	
		}
		if(!copy(self::getUploadedDirectory() . '/' . self::getOriginalItemName() . '.' . self::getFileExtension(), self::getUploadedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension())){
			self::getRest()->halt(400, json_encode(array('code'=>102,'message'=>'Unable to fulfill request')));	
		}
		$video_info = self::getVideoInfo(self::getUploadedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension());
		$duration_parts = explode(':', $video_info['duration']['timecode']['rounded']);
		$bitrate = $video_info['bitrate'];
		$width = $video_info['video']['dimensions']['width'];
		$height = $video_info['video']['dimensions']['height'];
		$framerate = $video_info['video']['frame_rate'];
		$codec = $video_info['video']['codec'];
		$duration = ($duration_parts[0] * 3600) + ($duration_parts[1] * 60) + $duration_parts[2];
		if($duration > 20){
			self::deleteDirectoryRecursive(self::getSessionDirectory());
			self::getRest()->halt(400, json_encode(array('code'=>5, 'message'=>'Video must be less than 20 seconds.')));
		}
		if($duration < 3){
			self::deleteDirectoryRecursive(self::getSessionDirectory());
			self::getRest()->halt(400, json_encode(array('code'=>5, 'message'=>'Video must be greater than 3 seconds.')));
		}
		if($height > 480){ // down sample
			$exec_status = 0;
			exec(self::getFFMPEG() . ' -i ' . self::getUploadedDirectory() . '/' . self::getOriginalItemName() . '.' . self::getFileExtension() . ' -vf "scale=((trunc(oh*a*2)/2-1)/2+1)*2:480" -y ' . self::getUploadedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension() . ' 2>&1', $buffer, $exec_status);
			if($exec_status != 0){
				self::deleteDirectoryRecursive(self::getSessionDirectory());
				self::getRest()->halt(400, json_encode(array('code'=>111,'message'=>'Unable to fulfill request with exit code [' . $exec_status . ']')));	
			}
			$video_info = self::getVideoInfo(self::getUploadedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension());
			$duration_parts = explode(':', $video_info['duration']['timecode']['rounded']);
			$bitrate = $video_info['bitrate'];
			$width = $video_info['video']['dimensions']['width'];
			$height = $video_info['video']['dimensions']['height'];
			$framerate = $video_info['video']['frame_rate'];
			$codec = $video_info['video']['codec'];
			$duration = ($duration_parts[0] * 3600) + ($duration_parts[1] * 60) + $duration_parts[2];
		}
		$exec_status = 0;
		exec(self::getFFMPEG() . ' -i ' . self::getUploadedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension() . ' -vcodec png -qscale 0 -ss 00:00:0' . ($duration < 3 ? $duration : 3) . '.00 -vframes 1 -f image2 ' . self::getEncodedDirectory() . '/preview.png' . ' 2>&1', $buffer, $exec_status);
		if($exec_status != 0){
			self::deleteDirectoryRecursive(self::getSessionDirectory());
			self::getRest()->halt(400, json_encode(array('code'=>111,'message'=>'Unable to fulfill request with exit code [' . $exec_status . ']')));	
		}
		if(!copy(self::getEncodedDirectory() . '/preview.png', self::getEncodedDirectory() . '/preview-working.png')){
			self::deleteDirectoryRecursive(self::getSessionDirectory());
			self::getRest()->halt(400, json_encode(array('code'=>102,'message'=>'Unable to fulfill request')));	
		}
		exec(self::getFFMPEG() . ' -i ' . self::getUploadedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension() . ' -vcodec png -qscale 0 -y ' . self::getEncodedDirectory() . '/images/%6d.png &> /dev/null &', $buffer, $exec_status);
		if($exec_status != 0){
			self::getRest()->halt(400, json_encode(array('code'=>111,'message'=>'Unable to fulfill request with exit code [' . $exec_status . ']')));
		}
		$expire_timestamp = file_get_contents(self::getSessionDirectory() . '/expire');
		if(!$expire_timestamp){
			self::getRest()->halt(400, json_encode(array('code'=>103,'message'=>'Unable to fulfill request')));	
		}
		$expire_diff_timestamp = time() - $expire_timestamp;
		$response = array();
		$response['success'] = true;
		$response['unique'] = self::getUnique();
		$response['original_image'] = 'http://' . $_SERVER['HTTP_HOST'] . '/video/original-image/' . self::getAPIToken() . '/' . self::getUnique();
		$response['preview_image'] = 'http://' . $_SERVER['HTTP_HOST'] . '/video/preview-image/' . self::getAPIToken() . '/' . self::getUnique();
		$response['video_width'] = $width;
		$response['video_height'] = $height;
		$response['video_framerate'] = $framerate;
		$response['video_length'] = $duration;
		$response['expire_days_remaining'] = -(floor($expire_diff_timestamp / (60 * 60 * 24)));
		$response['expire_ISO_8601_date'] = gmdate('Y-m-d\TH:i:s\Z', $expire_timestamp);
		echo json_encode($response);
		exit;
	}
	
	/**
	 * Rest Method for initiating a filter session
	 *
	 * @type photo
	 * @method POST
	 * @required session id 
	 */
	public static function photoInitiate($id) {
		$request = Slim::getInstance()->request();
		self::getRest()->contentType('application/json');
		self::setUnique($id);
		self::setAPIToken($request->headers('X-Mashape-User-PublicKey'));
		self::setSessionDirectory(self::getStorageDirectory() . '/' . self::getAPIToken() . '/' . self::getUnique());
		if(!self::isValidUser()) {
			self::getRest()->halt(403, json_encode(array('code'=>1, 'message'=>'Invalid authentication')));
		}
		if(isset($_FILES['Filedata'])){ // requests sent from Flash Player
			$_FILES['file'] = $_FILES['Filedata'];
		}
		if(!isset($_FILES["file"]["tmp_name"])) {
			self::getRest()->halt(400, json_encode(array('code'=>2,'message'=>'Missing Required Parameters')));
		}
		if((!isset($_FILES["file"]["name"]) || !strstr($_FILES["file"]["name"], '.')) && !isset($_POST['name'])) {
			self::getRest()->halt(400, json_encode(array('code'=>2,'message'=>'Missing Required Parameters')));
		}
		self::setUploadedDirectory(self::getSessionDirectory() . '/' . self::getUploadedDirectoryName());
		self::setEncodedDirectory(self::getSessionDirectory() . '/' . self::getEncodedDirectoryName());
		$filename = isset($_FILES["file"]["name"]) && strstr($_FILES["file"]["name"], '.') ? $_FILES["file"]["name"] : $_POST['name'];
		self::setFileExtension(strtolower(pathinfo($filename, PATHINFO_EXTENSION)));
		if(!is_dir(self::getSessionDirectory())){
			if(!mkdir(self::getSessionDirectory(), 0777, true)){
				self::getRest()->halt(400, json_encode(array('code'=>100,'message'=>'Unable to fulfill request')));	
			}
		}
		if(!is_dir(self::getUploadedDirectory())){
			if(!mkdir(self::getUploadedDirectory(), 0777, true)){
				self::getRest()->halt(400, json_encode(array('code'=>100,'message'=>'Unable to fulfill request')));
			}
		}
		if(!is_dir(self::getEncodedDirectory())){
			if(!mkdir(self::getEncodedDirectory(), 0777, true)){
				self::getRest()->halt(400, json_encode(array('code'=>100,'message'=>'Unable to fulfill request')));
			}
		}
		if(!file_put_contents(self::getUploadedDirectory() . '/ext', self::getFileExtension())){
			self::getRest()->halt(400, json_encode(array('code'=>104,'message'=>'Unable to fulfill request')));	
		}
		if(!file_put_contents(self::getSessionDirectory() . '/expire', strtotime('+' . self::SESSION_EXPIRE_TIME . ' days 12:00am', time()))){
			self::getRest()->halt(400, json_encode(array('code'=>104,'message'=>'Unable to fulfill request')));	
		}
		if(!move_uploaded_file($_FILES["file"]["tmp_name"], self::getUploadedDirectory() . '/' . self::getOriginalItemName() . '.' . self::getFileExtension())){
			self::getRest()->halt(400, json_encode(array('code'=>105,'message'=>'Unable to fulfill request')));	
		}
		$size = getimagesize(self::getUploadedDirectory() . '/' . self::getOriginalItemName() . '.' . self::getFileExtension());
		if($size[0] > 800 || $size[1] > 800){ // down sample
			$ratio = $size[1] / $size[0];
			if($size[0] > $size[1]){
				$new_width = 800;
				$new_height = round($new_width * $ratio);
			} else {
				$new_height = 800;
				$new_width = round($new_height * $ratio);
			}
			$exec_status = 0;
			exec(self::getImageMagickBase() . 'convert ' . self::getUploadedDirectory() . '/' . self::getOriginalItemName() . '.' . self::getFileExtension() . ' -resize "' . $new_width . 'x' . $new_height . '" ' . self::getUploadedDirectory() . '/' . self::getOriginalItemName() . '.' . self::getFileExtension() . ' 2>&1', $buffer, $exec_status);
			if($exec_status != 0){
				self::deleteDirectoryRecursive(self::getSessionDirectory());
				self::getRest()->halt(400, json_encode(array('code'=>111,'message'=>'Unable to fulfill request with exit code [' . $exec_status . ']')));	
			}			
		}
		if(!copy(self::getUploadedDirectory() . '/' . self::getOriginalItemName() . '.' . self::getFileExtension(), self::getUploadedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension())){
			self::deleteDirectoryRecursive(self::getSessionDirectory());
			self::getRest()->halt(400, json_encode(array('code'=>102,'message'=>'Unable to fulfill request')));	
		}
		if(!copy(self::getUploadedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension(), self::getEncodedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension())){
			self::deleteDirectoryRecursive(self::getSessionDirectory());
			self::getRest()->halt(400, json_encode(array('code'=>102,'message'=>'Unable to fulfill request')));	
		}
		$expire_timestamp = file_get_contents(self::getSessionDirectory() . '/expire');
		if(!$expire_timestamp){
			self::getRest()->halt(400, json_encode(array('code'=>103,'message'=>'Unable to fulfill request')));	
		}
		$expire_diff_timestamp = time() - $expire_timestamp;
		$size = getimagesize(self::getEncodedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension());
		$response = array();
		$response['success'] = true;
		$response['unique'] = self::getUnique();
		$response['photo_url_original'] = 'http://' . $_SERVER['HTTP_HOST'] . '/photo/original/' . self::getAPIToken() . '/' . self::getUnique() . '/' . self::getFileExtension();
		$response['photo_url_filtered'] = 'http://' . $_SERVER['HTTP_HOST'] . '/photo/filtered/' . self::getAPIToken() . '/' . self::getUnique() . '/' . self::getFileExtension();
		$response['photo_width'] = $size[0];
		$response['photo_height'] = $size[1];
		$response['expire_days_remaining'] = -(floor($expire_diff_timestamp / (60 * 60 * 24)));
		$response['expire_ISO_8601_date'] = gmdate('Y-m-d\TH:i:s\Z', $expire_timestamp);
		echo json_encode($response);
		exit;
	}
	
	/**
	 * Rest Method for rotating
	 *
	 * @type video
	 * @method POST
	 * @required session id 
	 */
	public static function videoRotate($id) {
		$request = Slim::getInstance()->request();
		self::getRest()->contentType('application/json');
		self::setUnique($id);
		self::setAPIToken($request->headers('X-Mashape-User-PublicKey')); 
		self::setSessionDirectory(self::getStorageDirectory() . '/' . self::getAPIToken() . '/' . self::getUnique());
		if(!self::isValidUser()) {
			self::getRest()->halt(403, json_encode(array('code'=>1, 'message'=>'Invalid authentication')));
		}
		if(!$request->params('rotation')) {
			self::getRest()->halt(400, json_encode(array('code'=>2,'message'=>'Missing Required Parameters')));
		}
		if(!self::isValidSession()){
			self::getRest()->halt(400, json_encode(array('code'=>3, 'message'=>'Specified session was not found')));
		}
		if(self::isVideoConverting()){
			self::getRest()->halt(403, json_encode(array('code'=>4, 'message'=>'Specified video session is currently filtering')));	
		}
		self::setUploadedDirectory(self::getSessionDirectory() . '/' . self::getUploadedDirectoryName());
		self::setEncodedDirectory(self::getSessionDirectory() . '/' . self::getEncodedDirectoryName());
		$rotation = $request->params('rotation');
		self::setFileExtension(@file_get_contents(self::getUploadedDirectory() . '/ext'));
		if(!self::getFileExtension()){
			self::getRest()->halt(400, json_encode(array('code'=>103,'message'=>'Unable to fulfill request')));	
		}
		$video_info = self::getVideoInfo(self::getUploadedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension());
		$duration_parts = explode(':', $video_info['duration']['timecode']['rounded']);
		$duration = ($duration_parts[0] * 3600) + ($duration_parts[1] * 60) + $duration_parts[2];
		$exec_status = 0;
		exec(self::getFFMPEG() . ' -i ' . self::getUploadedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension() . ' -vf "transpose=' . $rotation . '" -y ' . self::getUploadedDirectory() . '/' . self::getWorkingItemName() . '-rotate' . '.' . self::getFileExtension() . ' 2>&1', $buffer, $exec_status);
		if($exec_status != 0){
			self::getRest()->halt(400, json_encode(array('code'=>111,'message'=>'Unable to fulfill request with exit code [' . $exec_status . ']')));	
		}
		if(!copy(self::getUploadedDirectory() . '/' . self::getWorkingItemName() . '-rotate' . '.' . self::getFileExtension(), self::getUploadedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension())){
			self::getRest()->halt(400, json_encode(array('code'=>102,'message'=>'Unable to fulfill request')));	
		}
		$exec_status = 0;
		exec(self::getFFMPEG() . ' -i ' . self::getUploadedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension() . ' -vcodec png -ss 00:00:0' . ($duration < 3 ? $duration : 3) . '.00 -vframes 1 -f image2 -y ' . self::getEncodedDirectory() . '/preview.png 2>&1', $buffer, $exec_status);
		if($exec_status != 0){
			self::getRest()->halt(400, json_encode(array('code'=>111,'message'=>'Unable to fulfill request with exit code [' . $exec_status . ']')));	
		}
		exec(self::getFFMPEG() . ' -i ' . self::getUploadedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension() . ' -vcodec png -qscale 0 -y ' . self::getEncodedDirectory() . '/images/%6d.png &> /dev/null &', $buffer, $exec_status);
		if($exec_status != 0){
			self::getRest()->halt(400, json_encode(array('code'=>111,'message'=>'Unable to fulfill request with exit code [' . $exec_status . ']')));
		}
		if(!copy(self::getEncodedDirectory() . '/preview.png', self::getEncodedDirectory() . '/preview-working.png')){
			self::getRest()->halt(400, json_encode(array('code'=>102,'message'=>'Unable to fulfill request')));	
		}
		$video_info = self::getVideoInfo(self::getUploadedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension());
		$width = $video_info['video']['dimensions']['width'];
		$height = $video_info['video']['dimensions']['height'];
		$framerate = $video_info['video']['frame_rate'];
		$response = array();
		$response['success'] = true;
		$response['unique'] = self::getUnique();
		$response['video_width'] = $width;
		$response['video_height'] = $height;
		$response['video_framerate'] = $framerate;
		$response['preview_image'] = 'http://' . $_SERVER['HTTP_HOST'] . '/video/preview-image/' . self::getAPIToken() . '/' . self::getUnique();
		echo json_encode($response);
		exit;
	}
	
	/**
	 * Rest Method for rotating
	 *
	 * @type photo
	 * @method POST
	 * @required session id 
	 */
	public static function photoRotate($id) {
		$request = Slim::getInstance()->request();
		self::getRest()->contentType('application/json');
		self::setUnique($id);
		self::setAPIToken($request->headers('X-Mashape-User-PublicKey')); 
		self::setSessionDirectory(self::getStorageDirectory() . '/' . self::getAPIToken() . '/' . self::getUnique());
		if(!self::isValidUser()) {
			self::getRest()->halt(403, json_encode(array('code'=>1, 'message'=>'Invalid authentication')));
		}
		if($request->params('rotation') == NULL) {
			self::getRest()->halt(400, json_encode(array('code'=>2,'message'=>'Missing Required Parameters')));
		}
		if(!self::isValidSession()){
			self::getRest()->halt(400, json_encode(array('code'=>3, 'message'=>'Specified session was not found')));
		}
		self::setUploadedDirectory(self::getSessionDirectory() . '/' . self::getUploadedDirectoryName());
		self::setEncodedDirectory(self::getSessionDirectory() . '/' . self::getEncodedDirectoryName());
		$rotation = $request->params('rotation');
		self::setFileExtension(@file_get_contents(self::getUploadedDirectory() . '/ext'));
		if(!self::getFileExtension()){
			self::getRest()->halt(400, json_encode(array('code'=>103,'message'=>'Unable to fulfill request')));	
		}
		$degrees = 0;
		switch($rotation){
			case '1':
				$degrees = 90;
			break;
			case '2':
				$degrees = 270;
			break;
		}
		$exec_status = 0;
		exec(self::getImageMagickBase() . '/convert -rotate ' . $degrees . ' ' . self::getUploadedDirectory() . '/' . self::getOriginalItemName() . '.' . self::getFileExtension() . ' ' . self::getUploadedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension() . ' 2>&1', $buffer, $exec_status);
		if($exec_status != 0){
			self::getRest()->halt(400, json_encode(array('code'=>111,'message'=>'Unable to fulfill request with exit code [' . $exec_status . ']')));	
		}
		if(!copy(self::getUploadedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension(), self::getEncodedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension())){
			self::deleteDirectoryRecursive(self::getSessionDirectory());
			self::getRest()->halt(400, json_encode(array('code'=>102,'message'=>'Unable to fulfill request')));	
		}
		$size = getimagesize(self::getEncodedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension());
		$response = array();
		$response['success'] = true;
		$response['unique'] = self::getUnique();
		$response['photo_url_original'] = 'http://' . $_SERVER['HTTP_HOST'] . '/photo/original/' . self::getAPIToken() . '/' . self::getUnique() . '/' . self::getFileExtension();
		$response['photo_url_filtered'] = 'http://' . $_SERVER['HTTP_HOST'] . '/photo/filtered/' . self::getAPIToken() . '/' . self::getUnique() . '/' . self::getFileExtension();
		$response['photo_width'] = $size[0];
		$response['photo_height'] = $size[1];
		echo json_encode($response);
		exit;
	}
		
	/**
	 * Rest Method for previewing a filter
	 *
	 * @type video
	 * @method POST
	 * @required session id
	 */
	public static function videoFilterPreview($id) {
		$request = Slim::getInstance()->request();
		self::getRest()->contentType('application/json');
		self::setUnique($id);
		self::setAPIToken($request->headers('X-Mashape-User-PublicKey')); 
		self::setSessionDirectory(self::getStorageDirectory() . '/' . self::getAPIToken() . '/' . self::getUnique());
		if(!self::isValidUser()) {
			self::getRest()->halt(403, json_encode(array('code'=>1, 'message'=>'Invalid authentication')));
		}
		if(!self::isValidFilter($request->params('filter'))){
			self::getRest()->halt(400, json_encode(array('code'=>6, 'message'=>'Specified Filter is either inactive or not found')));
		}
		if(!self::isValidFilterRequires($request)){
			self::getRest()->halt(400, json_encode(array('code'=>7, 'message'=>'Missing Specified Filter\'s Required Parameters')));
		}
		if(!self::isValidSession()){
			self::getRest()->halt(400, json_encode(array('code'=>3, 'message'=>'Specified session was not found')));
		}
		if(self::isVideoConverting()){
			self::getRest()->halt(403, json_encode(array('code'=>4, 'message'=>'Specified video session is currently filtering')));	
		}
		self::setUploadedDirectory(self::getSessionDirectory() . '/' . self::getUploadedDirectoryName());
		self::setEncodedDirectory(self::getSessionDirectory() . '/' . self::getEncodedDirectoryName());
		$filter_key = $request->params('filter');
		$red = ($request->params('filterred') != NULL ? $request->params('filterred') : '');
		$blue = ($request->params('filterblue') != NULL ? $request->params('filterblue') : '');
		$green = ($request->params('filtergreen') != NULL ? $request->params('filtergreen') : '');
		$saturation = ($request->params('saturation') != NULL ? $request->params('saturation') : '');
		$file = self::getEncodedDirectory() . '/preview.png';
		$preview_file = self::getEncodedDirectory() . '/preview-working.png';
		if($filter_key == ''){
			if(!copy($file, $preview_file)){
				self::getRest()->halt(400, json_encode(array('code'=>102,'message'=>'Unable to fulfill request')));	
			}
		} else {
			$matching_filter = self::getDatabase()->get_item(array(
				'TableName' => 'Filters',
				'Key' => self::getDatabase()->attributes(array(
					'HashKeyElement'  => $filter_key
				)),
				'ConsistentRead' => 'true'
			));
			if($matching_filter->isOK()){
				$filter = array(
					'name'			=>	(string) $matching_filter->body->Item->name->{AmazonDynamoDB::TYPE_STRING},
					'class_name'	=>	(string) $matching_filter->body->Item->class_name->{AmazonDynamoDB::TYPE_STRING},
					'filter_key'	=>	(string) $matching_filter->body->Item->filter_key->{AmazonDynamoDB::TYPE_STRING},
					'required'		=>	(string) $matching_filter->body->Item->required->{AmazonDynamoDB::TYPE_STRING},
					'private'		=>	(int) $matching_filter->body->Item->private->{AmazonDynamoDB::TYPE_NUMBER},
					'owner'			=>	(string) $matching_filter->body->Item->owner->{AmazonDynamoDB::TYPE_STRING},
					'status'		=>	(string) $matching_filter->body->Item->status->{AmazonDynamoDB::TYPE_STRING}
				);
				FilterFactory::init($filter, $file, $preview_file, 1, 1, $red, $green, $blue, $saturation);
			} else {
				self::getRest()->halt(403, json_encode(array('code'=>6, 'message'=>'Specified filter not found')));		
			}
		}
		$response = array();
		$response['success'] = true;
		$response['unique'] = self::getUnique();
		$response['preview_image'] = 'http://' . $_SERVER['HTTP_HOST'] . '/video/preview-image/' . self::getAPIToken() . '/' . self::getUnique();
		echo json_encode($response);
		exit;
	}
	
	/**
	 * Rest Method for filtering
	 *
	 * @type video
	 * @method POST
	 * @required session id
	 */
	public static function videoFilter($id) {
		$request = Slim::getInstance()->request();
		self::getRest()->contentType('application/json');
		self::setUnique($id);
		self::setAPIToken($request->headers('X-Mashape-User-PublicKey')); 
		self::setSessionDirectory(self::getStorageDirectory() . '/' . self::getAPIToken() . '/' . self::getUnique());
		if(!self::isValidUser()) {
			self::getRest()->halt(403, json_encode(array('code'=>1, 'message'=>'Invalid authentication')));
		}
		if(!self::isValidFilter($request->params('filter'))){
			self::getRest()->halt(400, json_encode(array('code'=>6, 'message'=>'Specified Filter is either inactive or not found')));
		}
		if(!self::isValidFilterRequires($request)){
			self::getRest()->halt(400, json_encode(array('code'=>7, 'message'=>'Missing Specified Filter\'s Required Parameters')));
		}
		if(!self::isValidSession()){
			self::getRest()->halt(400, json_encode(array('code'=>3, 'message'=>'Specified session was not found')));
		}
		if(self::isVideoConverting()){
			self::getRest()->halt(403, json_encode(array('code'=>4, 'message'=>'Specified video session is currently filtering')));	
		}
		//Clear the log of previous convert
		self::setEncodedDirectory(self::getSessionDirectory() . '/' . self::getEncodedDirectoryName());
		if(is_file(self::getEncodedDirectory() . '/progress.log')){
			if(!file_put_contents(self::getEncodedDirectory() . '/progress.log', ' ')){
				self::getRest()->halt(400, json_encode(array('code'=>104,'message'=>'Unable to fulfill request')));	
			}
		}
		//
		$filter_options = array(
			'unique'					=>	self::getUnique(),
			'filter'					=>	$request->params('filter'),
			'filterred'					=>	($request->params('filterred') != NULL ? $request->params('filterred') : '0'),
			'filterblue'				=>	($request->params('filterblue') != NULL ? $request->params('filterblue') : '0'),
			'filtergreen'				=>	($request->params('filtergreen') != NULL ? $request->params('filtergreen') : '0'),
			'saturation'				=>	($request->params('saturation') != NULL ? $request->params('saturation') : '0'),
			'overlaytext'				=>	($request->params('overlaytext') != NULL ? $request->params('overlaytext') : 'none'),
			'overlayfont'				=>	($request->params('overlayfont') != NULL ? $request->params('overlayfont') : 'Liberation Sans'),
			'overlayfontcolor'			=>	($request->params('overlayfontcolor') != NULL ? $request->params('overlayfontcolor') : 'FFFFFF'),
			'overlayfontsize'			=>	($request->params('overlayfontsize') != NULL ? $request->params('overlayfontsize') : '40'),
			'overlayfontbordersize'		=>	($request->params('overlayfontbordersize') != NULL ? $request->params('overlayfontbordersize') : '0'),
			'overlayfontbordercolor'	=>	($request->params('overlayfontbordercolor') != NULL ? $request->params('overlayfontbordercolor') : '0'),
			'overlayshadowsize'			=>	($request->params('overlayshadowsize') != NULL ? $request->params('overlayshadowsize') : '1'),
			'overlayshadowcolor'		=>	($request->params('overlayshadowcolor') != NULL ? $request->params('overlayshadowcolor') : '000000'),
			'overlaytextstart'			=>	($request->params('overlaytextstart') != NULL ? $request->params('overlaytextstart') : '0'),
			'overlaytextend'			=>	($request->params('overlaytextend') != NULL ? $request->params('overlaytextend') : '1'),
			'overlaytextposition'		=>	($request->params('overlaytextposition') != NULL ? $request->params('overlaytextposition') : '2'),
			'fade'						=>	($request->params('fade') != NULL ? $request->params('fade') : 'none')
		);
		if(!file_put_contents(self::getEncodedDirectory() . '/filter-options.log', json_encode($filter_options))){
			self::getRest()->halt(400, json_encode(array('code'=>104,'message'=>'Unable to fulfill request')));	
		}
		$exec_status = 0;
		exec(self::getPHP() . ' -f ' . self::getServerRoot() . '/worker/filter.worker.php -- ' . urlencode(self::getAPIToken() . '/' . self::getUnique()) . ' &> /dev/null &', $buffer, $exec_status);
		if($exec_status != 0){
			self::getRest()->halt(400, json_encode(array('code'=>111,'message'=>'Unable to fulfill request with exit code [' . $exec_status . ']')));	
		}
		$response['success'] = true;
		$response['unique'] = self::getUnique();
		echo json_encode($response);
		exit;
	}
	
	/**
	 * Rest Method for filtering
	 *
	 * @type photo
	 * @method POST
	 * @required session id
	 */
	public static function photoFilter($id) {
		$request = Slim::getInstance()->request();
		self::getRest()->contentType('application/json');
		self::setUnique($id);
		self::setAPIToken($request->headers('X-Mashape-User-PublicKey')); 
		self::setSessionDirectory(self::getStorageDirectory() . '/' . self::getAPIToken() . '/' . self::getUnique());
		if(!self::isValidUser()) {
			self::getRest()->halt(403, json_encode(array('code'=>1, 'message'=>'Invalid authentication')));
		}
		if(!self::isValidFilter($request->params('filter'))){
			self::getRest()->halt(400, json_encode(array('code'=>6, 'message'=>'Specified Filter is either inactive or not found')));
		}
		if(!self::isValidFilterRequires($request)){
			self::getRest()->halt(400, json_encode(array('code'=>7, 'message'=>'Missing Specified Filter\'s Required Parameters')));
		}
		if(!self::isValidSession()){
			self::getRest()->halt(400, json_encode(array('code'=>3, 'message'=>'Specified session was not found')));
		}
		self::setUploadedDirectory(self::getSessionDirectory() . '/' . self::getUploadedDirectoryName());
		self::setEncodedDirectory(self::getSessionDirectory() . '/' . self::getEncodedDirectoryName());
		self::setFileExtension(@file_get_contents(self::getUploadedDirectory() . '/ext'));
		if(!self::getFileExtension()){
			self::getRest()->halt(400, json_encode(array('code'=>103,'message'=>'Unable to fulfill request')));	
		}
		$filter_key = $request->params('filter');
		$red = ($request->params('filterred') != NULL ? $request->params('filterred') : '');
		$blue = ($request->params('filterblue') != NULL ? $request->params('filterblue') : '');
		$green = ($request->params('filtergreen') != NULL ? $request->params('filtergreen') : '');
		$saturation = ($request->params('saturation') != NULL ? $request->params('saturation') : '');
		$file = self::getUploadedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension();
		$filtered_file = self::getEncodedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension();
		$filter_options = array(
			'unique'					=>	self::getUnique(),
			'filter'					=>	$filter_key,
			'filterred'					=>	($request->params('filterred') != NULL ? $request->params('filterred') : '0'),
			'filterblue'				=>	($request->params('filterblue') != NULL ? $request->params('filterblue') : '0'),
			'filtergreen'				=>	($request->params('filtergreen') != NULL ? $request->params('filtergreen') : '0'),
			'saturation'				=>	($request->params('saturation') != NULL ? $request->params('saturation') : '0')
		);
		if(!file_put_contents(self::getEncodedDirectory() . '/filter-options.log', json_encode($filter_options))){
			self::getRest()->halt(400, json_encode(array('code'=>104,'message'=>'Unable to fulfill request')));	
		}
		if($filter_key == ''){
			if(!copy($file, $filtered_file)){
				self::getRest()->halt(400, json_encode(array('code'=>102,'message'=>'Unable to fulfill request')));	
			}
		} else {
			$matching_filter = self::getDatabase()->get_item(array(
				'TableName' => 'Filters',
				'Key' => self::getDatabase()->attributes(array(
					'HashKeyElement'  => $filter_key
				)),
				'ConsistentRead' => 'true'
			));
			if($matching_filter->isOK()){
				$filter = array(
					'name'			=>	(string) $matching_filter->body->Item->name->{AmazonDynamoDB::TYPE_STRING},
					'class_name'	=>	(string) $matching_filter->body->Item->class_name->{AmazonDynamoDB::TYPE_STRING},
					'filter_key'	=>	(string) $matching_filter->body->Item->filter_key->{AmazonDynamoDB::TYPE_STRING},
					'required'		=>	(string) $matching_filter->body->Item->required->{AmazonDynamoDB::TYPE_STRING},
					'private'		=>	(int) $matching_filter->body->Item->private->{AmazonDynamoDB::TYPE_NUMBER},
					'owner'			=>	(string) $matching_filter->body->Item->owner->{AmazonDynamoDB::TYPE_STRING},
					'status'		=>	(string) $matching_filter->body->Item->status->{AmazonDynamoDB::TYPE_STRING}
				);
				FilterFactory::init($filter, $file, $filtered_file, 1, 1, $red, $green, $blue, $saturation);
			} else {
				self::getRest()->halt(403, json_encode(array('code'=>6, 'message'=>'Specified filter not found')));		
			}
		}
		$expire_timestamp = file_get_contents(self::getSessionDirectory() . '/expire');
		if(!$expire_timestamp){
			self::getRest()->halt(400, json_encode(array('code'=>103,'message'=>'Unable to fulfill request')));	
		}
		$expire_diff_timestamp = time() - $expire_timestamp;
		$response = array();
		$response['success'] = true;
		$response['unique'] = self::getUnique();
		$response['photo_url_filtered'] = 'http://' . $_SERVER['HTTP_HOST'] . '/photo/filtered/' . self::getAPIToken() . '/' . self::getUnique() . '/' . self::getFileExtension();
		$response['photo_url_original'] = 'http://' . $_SERVER['HTTP_HOST'] . '/photo/original/' . self::getAPIToken() . '/' . self::getUnique() . '/' . self::getFileExtension();
		$response['expire_days_remaining'] = -(floor($expire_diff_timestamp / (60 * 60 * 24)));
		$response['expire_ISO_8601_date'] = gmdate('Y-m-d\TH:i:s\Z', $expire_timestamp);
		echo json_encode($response);
		exit;
	}
	
	/**
	 * Rest Method for checking a filter job's progress
	 *
	 * @type video
	 * @method POST
	 * @required session id
	 */
	public static function videoFilterProgress($id) {
		$request = Slim::getInstance()->request();
		self::getRest()->contentType('application/json');
		self::setUnique($id);
		self::setAPIToken($request->headers('X-Mashape-User-PublicKey')); 
		self::setSessionDirectory(self::getStorageDirectory() . '/' . self::getAPIToken() . '/' . self::getUnique());
		if(!self::isValidUser()) {
			self::getRest()->halt(403, json_encode(array('code'=>1, 'message'=>'Invalid authentication')));
		}
		if(!self::isValidSession()){
			self::getRest()->halt(400, json_encode(array('code'=>3, 'message'=>'Specified session was not found')));
		}
		self::setUploadedDirectory(self::getSessionDirectory() . '/' . self::getUploadedDirectoryName());
		self::setEncodedDirectory(self::getSessionDirectory() . '/' . self::getEncodedDirectoryName());
		$progress_log = self::getEncodedDirectory() . '/progress.log';
		$conversion_log = self::getEncodedDirectory() . '/ffmpeg.log';
		$progress_json = @file_get_contents($progress_log);
		if(!$progress_json){
			self::getRest()->halt(400, json_encode(array('code'=>103,'message'=>'Unable to fulfill request')));	
		}
		$progress = json_decode($progress_json, true);
		$response = array();
		$response['success'] = $progress['success'];
		$response['unique'] = self::getUnique();
		$response['step'] = $progress['step'];
		$response['steps'] = $progress['steps'];
		if($progress['step'] == 8){ // if its compiling a video, check the progress and send back complete %
			$conversion_progress = @file_get_contents($conversion_log);
			if(!$conversion_progress){
				self::getRest()->halt(400, json_encode(array('code'=>103,'message'=>'Unable to fulfill request')));
			}
			preg_match("/Duration:([^,]+)/", $conversion_progress, $matches);
			list($hours, $minutes, $seconds) = preg_split('/:/', $matches[1]);
			$seconds = (($hours * 3600) + ($minutes * 60) + $seconds);
			$seconds = round($seconds);
			$page = join("", file("$conversion_log"));
			$kw = explode("time=", $page);
			$last = array_pop($kw);
			$values = explode(' ', $last);
			$time = explode(':', $values[0]);
			$time = implode('', $time);
			$current_time = round($time);
			$percent_extracted = round(($current_time * 100) / ($seconds));
			$convert_progress = array();
			$convert_progress['text'] = 'video compile progress';
			$convert_progress['progress_percent'] = $percent_extracted;
			$response['message'] = $convert_progress;
		} else {
			$response['message'] = $progress['message'];
			if(!$response['success']){
				$response['code'] = $progress['code'];	
			}
		}
		echo json_encode($response);
		exit;
	}
	
	/**
	 * Rest Method for cancelling a filter session
	 *
	 * @type video
	 * @method POST
	 * @required session id
	 */
	public static function videoFilterCancel($id) {
		$request = Slim::getInstance()->request();
		self::getRest()->contentType('application/json');
		self::setUnique($id);
		self::setAPIToken($request->headers('X-Mashape-User-PublicKey')); 
		self::setSessionDirectory(self::getStorageDirectory() . '/' . self::getAPIToken() . '/' . self::getUnique());
		if(!self::isValidUser()) {
			self::getRest()->halt(403, json_encode(array('code'=>1, 'message'=>'Invalid authentication')));
		}
		if(!self::isValidSession()){
			self::getRest()->halt(400, json_encode(array('code'=>3, 'message'=>'Specified session was not found')));
		}
		if(!self::isVideoConverting()){
			self::getRest()->halt(400, json_encode(array('code'=>10,'message'=>'Specified video session is not currently filtering')));
		}
		self::setUploadedDirectory(self::getSessionDirectory() . '/' . self::getUploadedDirectoryName());
		self::setEncodedDirectory(self::getSessionDirectory() . '/' . self::getEncodedDirectoryName());
		$process_id = @file_get_contents(self::getEncodedDirectory() . '/pid');
		if(!$process_id){
			self::getRest()->halt(400, json_encode(array('code'=>103,'message'=>'Unable to fulfill request')));
		}
		$exec_status = 0;
		exec('ps ' . $process_id . ' 2>&1', $buffer, $exec_status);
		if($exec_status != 0){
			self::getRest()->halt(400, json_encode(array('code'=>111,'message'=>'Unable to fulfill request with exit code [' . $exec_status . ']')));	
		}
		if(count($buffer) >= 2){
			$exec_status = 0;
			exec('kill ' . $process_id . ' 2>&1', $buffer, $exec_status);
			if($exec_status != 0){
				unlink(self::getEncodedDirectory() . '/filterlock');
				self::getRest()->halt(400, json_encode(array('code'=>111,'message'=>'Unable to fulfill request with exit code [' . $exec_status . ']')));	
			}
		}
		unlink(self::getEncodedDirectory() . '/filterlock');
		$response = array();
		$response['success'] = true;
		$response['unique'] = self::getUnique();
		echo json_encode($response);
		exit;
	}
	
	/**
	 * Rest Method for getting a session's information
	 *
	 * @type video
	 * @method POST
	 * @required session id
	 */
	public static function videoInformation($id) {
		$request = Slim::getInstance()->request();
		self::getRest()->contentType('application/json');
		self::setUnique($id);
		self::setAPIToken($request->headers('X-Mashape-User-PublicKey')); 
		self::setSessionDirectory(self::getStorageDirectory() . '/' . self::getAPIToken() . '/' . self::getUnique());
		if(!self::isValidUser()) {
			self::getRest()->halt(403, json_encode(array('code'=>1, 'message'=>'Invalid authentication')));
		}
		if(!self::isValidSession()){
			self::getRest()->halt(400, json_encode(array('code'=>3, 'message'=>'Specified session was not found')));
		}
		self::setUploadedDirectory(self::getSessionDirectory() . '/' . self::getUploadedDirectoryName());
		self::setEncodedDirectory(self::getSessionDirectory() . '/' . self::getEncodedDirectoryName());		
		if(!file_exists(self::getEncodedDirectory() . '/video/' . self::getWorkingItemName() . '.mp4')){
			$expire_timestamp = file_get_contents(self::getSessionDirectory() . '/expire');
			if(!$expire_timestamp){
				self::getRest()->halt(400, json_encode(array('code'=>103,'message'=>'Unable to fulfill request')));	
			}
			$expire_diff_timestamp = time() - $expire_timestamp;
			self::getRest()->halt(400, json_encode(array('code'=>8,'message'=>'Filtered video not found','preview_image'=>'http://' . $_SERVER['HTTP_HOST'] . '/video/preview-image/' . self::getAPIToken() . '/' . self::getUnique(),'expire_days_remaining'=>-(floor($expire_diff_timestamp / (60 * 60 * 24))),'expire_ISO_8601_date'=>gmdate('Y-m-d\TH:i:s\Z', $expire_timestamp))));	
		}
		self::setFileExtension(file_get_contents(self::getUploadedDirectory() . '/ext'));
		if(!self::getFileExtension()){
			self::getRest()->halt(400, json_encode(array('code'=>103,'message'=>'Unable to fulfill request')));	
		}
		$video_info = self::getVideoInfo(self::getEncodedDirectory() . '/video/' . self::getWorkingItemName() . '.mp4');
		$duration_parts = explode(':', $video_info['duration']['timecode']['rounded']);
		$bitrate = $video_info['bitrate'];
		$width = $video_info['video']['dimensions']['width'];
		$height = $video_info['video']['dimensions']['height'];
		$framerate = $video_info['video']['frame_rate'];
		$codec = $video_info['video']['codec'];
		$duration = ($duration_parts[0] * 3600) + ($duration_parts[1] * 60) + $duration_parts[2];
		$images_raw = @scandir(self::getEncodedDirectory() . '/images');
		if(!$images_raw){
			self::getRest()->halt(400, json_encode(array('code'=>108,'message'=>'Unable to fulfill request')));	
		}
		$images = array();
		for($i = 0; $i < count($images_raw); $i++) {
			if(strstr($images_raw[$i], 'filtered') && count($images) < 10){
				array_push($images, 'http://' . $_SERVER['HTTP_HOST'] . '/video/processed-image/' . self::getAPIToken() . '/' . self::getUnique() . '/' . $images_raw[$i]);
			}
		}
		$filter_options = file_get_contents(self::getEncodedDirectory() . '/filter-options.log');
		if(!$filter_options){
			self::getRest()->halt(400, json_encode(array('code'=>103,'message'=>'Unable to fulfill request')));	
		}
		$filter_options = json_decode($filter_options, true);
		unset($filter_options['unique']);
		$expire_timestamp = file_get_contents(self::getSessionDirectory() . '/expire');
		if(!$expire_timestamp){
			self::getRest()->halt(400, json_encode(array('code'=>103,'message'=>'Unable to fulfill request')));	
		}
		$expire_diff_timestamp = time() - $expire_timestamp;
		$response = array();
		$response['success'] = true;
		$response['unique'] = self::getUnique();
		$response['storage_usage'] = self::formatBytes(self::calculateDirectorySizeAsBytes(self::getSessionDirectory()));
		$response['preview_images'] = $images;
		$response['video_url_filtered'] = 'http://' . $_SERVER['HTTP_HOST'] . '/video/filtered/' . self::getAPIToken() . '/' . self::getUnique();
		$response['video_url_original'] = 'http://' . $_SERVER['HTTP_HOST'] . '/video/original/' . self::getAPIToken() . '/' . self::getUnique() . '/' . self::getFileExtension();
		$response['video_bitrate'] = $bitrate . 'kb/s';
		$response['video_width'] = $width;
		$response['video_height'] = $height;
		$response['video_framerate'] = $framerate;
		$response['video_length'] = $duration;
		$response['video_codec'] = $codec;
		$response['filter_options'] = $filter_options;
		$response['expire_days_remaining'] = -(floor($expire_diff_timestamp / (60 * 60 * 24)));
		$response['expire_ISO_8601_date'] = gmdate('Y-m-d\TH:i:s\Z', $expire_timestamp);
		echo json_encode($response);
		exit;
	}
	
	/**
	 * Rest Method for getting a session's information
	 *
	 * @type photo
	 * @method POST
	 * @required session id
	 */
	public static function photoInformation($id) {
		$request = Slim::getInstance()->request();
		self::getRest()->contentType('application/json');
		self::setUnique($id);
		self::setAPIToken($request->headers('X-Mashape-User-PublicKey')); 
		self::setSessionDirectory(self::getStorageDirectory() . '/' . self::getAPIToken() . '/' . self::getUnique());
		if(!self::isValidUser()) {
			self::getRest()->halt(403, json_encode(array('code'=>1, 'message'=>'Invalid authentication')));
		}
		if(!self::isValidSession()){
			self::getRest()->halt(400, json_encode(array('code'=>3, 'message'=>'Specified session was not found')));
		}
		self::setUploadedDirectory(self::getSessionDirectory() . '/' . self::getUploadedDirectoryName());
		self::setEncodedDirectory(self::getSessionDirectory() . '/' . self::getEncodedDirectoryName());
		self::setFileExtension(@file_get_contents(self::getUploadedDirectory() . '/ext'));
		if(!self::getFileExtension()){
			self::getRest()->halt(400, json_encode(array('code'=>103,'message'=>'Unable to fulfill request')));	
		}
		$filter_options = file_get_contents(self::getEncodedDirectory() . '/filter-options.log');
		if($filter_options) $filter_options = json_decode($filter_options, true);
		if($filter_options) unset($filter_options['unique']);
		$expire_timestamp = file_get_contents(self::getSessionDirectory() . '/expire');
		if(!$expire_timestamp){
			self::getRest()->halt(400, json_encode(array('code'=>103,'message'=>'Unable to fulfill request')));	
		}
		$expire_diff_timestamp = time() - $expire_timestamp;
		$size = getimagesize(self::getEncodedDirectory() . '/' . self::getWorkingItemName() . '.' . self::getFileExtension());
		$response = array();
		$response['success'] = true;
		$response['unique'] = self::getUnique();
		$response['photo_url_filtered'] = 'http://' . $_SERVER['HTTP_HOST'] . '/photo/filtered/' . self::getAPIToken() . '/' . self::getUnique() . '/' . self::getFileExtension();
		$response['photo_url_original'] = 'http://' . $_SERVER['HTTP_HOST'] . '/photo/original/' . self::getAPIToken() . '/' . self::getUnique() . '/' . self::getFileExtension();
		if($filter_options) $response['filter_options'] = $filter_options;
		$response['expire_days_remaining'] = -(floor($expire_diff_timestamp / (60 * 60 * 24)));
		$response['expire_ISO_8601_date'] = gmdate('Y-m-d\TH:i:s\Z', $expire_timestamp);
		$response['photo_width'] = $size[0];
		$response['photo_height'] = $size[1];
		echo json_encode($response);
		exit;
	}
	
	/**
	 * Rest Method for getting a filtered video's images
	 *
	 * @type video
	 * @method POST
	 * @required session id
	 */
	public static function videoImages($id) {
		$request = Slim::getInstance()->request();
		self::getRest()->contentType('application/json');
		self::setUnique($id);
		self::setAPIToken($request->headers('X-Mashape-User-PublicKey')); 
		self::setSessionDirectory(self::getStorageDirectory() . '/' . self::getAPIToken() . '/' . self::getUnique());
		if(!self::isValidUser()) {
			self::getRest()->halt(403, json_encode(array('code'=>1, 'message'=>'Invalid authentication')));
		}
		if(!self::isValidSession()){
			self::getRest()->halt(400, json_encode(array('code'=>3, 'message'=>'Specified session was not found')));
		}
		self::setEncodedDirectory(self::getSessionDirectory() . '/' . self::getEncodedDirectoryName());
		$requested_count = $request->params('count') != NULL ? $request->params('count') : 20;
		$requested_type = $request->params('type') != NULL ? $request->params('type') : 'filtered';
		$images = @scandir(self::getEncodedDirectory() . '/images');
		if (!$images || count($images) <= 2) {
			self::getRest()->halt(400, json_encode(array('code'=>9,'message'=>'Specified video session has not been filtered yet')));
		}
		$requested_count = $requested_count == 'all' ? count($images) : $requested_count;
		$gathered_images = array();
		for($i = 0; $i < count($images); $i++) {
			if($requested_type == 'filtered'){
				if(strstr($images[$i], 'filtered') && count($gathered_images) < $requested_count){
					array_push($gathered_images, 'http://' . $_SERVER['HTTP_HOST'] . '/video/processed-image/' . self::getAPIToken() . '/' . self::getUnique() . '/' . $images[$i]);
				}
			} else {
				if(!strstr($images[$i], 'filtered') && count($gathered_images) < $requested_count){
					array_push($gathered_images, 'http://' . $_SERVER['HTTP_HOST'] . '/video/processed-image/' . self::getAPIToken() . '/' . self::getUnique() . '/' . $images[$i]);
				}
			}
		}
		$response = array();
		$response['success'] = true;
		$response['unique'] = self::getUnique();
		$response['images'] = $gathered_images;
		echo json_encode($response);
		exit;
	}
	
	/**
	 * Rest Method for deleting a session
	 *
	 * @type video
	 * @method POST
	 * @required session id
	 */
	public static function videoDelete($id) {
		$request = Slim::getInstance()->request();
		self::getRest()->contentType('application/json');
		self::setUnique($id);
		self::setAPIToken($request->headers('X-Mashape-User-PublicKey')); 
		self::setSessionDirectory(self::getStorageDirectory() . '/' . self::getAPIToken() . '/' . self::getUnique());
		if(!self::isValidUser()) {
			self::getRest()->halt(403, json_encode(array('code'=>1, 'message'=>'Invalid authentication')));
		}
		if(!self::isValidSession()){
			self::getRest()->halt(400, json_encode(array('code'=>3, 'message'=>'Specified session was not found')));
		}
		if(self::isVideoConverting()){
			self::getRest()->halt(403, json_encode(array('code'=>4, 'message'=>'Specified video session is currently filtering')));	
		}
		if(is_dir(self::getSessionDirectory())) self::deleteDirectoryRecursive(self::getSessionDirectory());
		$response = array();
		$response['success'] = true;
		$response['unique'] = self::getUnique();
		echo json_encode($response);
		exit;
	}
	
	/**
	 * Rest Method for deleting a session
	 *
	 * @type photo
	 * @method POST
	 * @required session id
	 */
	public static function photoDelete($id) {
		$request = Slim::getInstance()->request();
		self::getRest()->contentType('application/json');
		self::setUnique($id);
		self::setAPIToken($request->headers('X-Mashape-User-PublicKey')); 
		self::setSessionDirectory(self::getStorageDirectory() . '/' . self::getAPIToken() . '/' . self::getUnique());
		if(!self::isValidUser()) {
			self::getRest()->halt(403, json_encode(array('code'=>1, 'message'=>'Invalid authentication')));
		}
		if(!self::isValidSession()){
			self::getRest()->halt(400, json_encode(array('code'=>3, 'message'=>'Specified session was not found')));
		}
		if(is_dir(self::getSessionDirectory())) self::deleteDirectoryRecursive(self::getSessionDirectory());
		$response = array();
		$response['success'] = true;
		$response['unique'] = self::getUnique();
		echo json_encode($response);
		exit;
	}
	
	/**
	 * Rest Method for calculating total storage used by a developer account
	 * TODO: No long valid since an account's sessions can be split
	 *       across X number of servers
	 *
	 * @method 		POST
	 */
	public static function calculateAccountStorage() {
		$request = Slim::getInstance()->request();
		self::getRest()->contentType('application/json');
		self::setAPIToken($request->headers('X-Mashape-User-PublicKey')); 
		self::setSessionDirectory(self::getStorageDirectory() . '/' . self::getAPIToken());
		if(!self::isValidUser()) {
			self::getRest()->halt(403, json_encode(array('code'=>1, 'message'=>'Invalid authentication')));
		}
		$response = array();
		$response['success'] = true;
		$response['storage_bytes'] = self::calculateDirectorySizeAsBytes(self::getSessionDirectory());
		$response['storage_formatted'] = self::formatBytes(self::calculateDirectorySizeAsBytes(self::getSessionDirectory()));
		echo json_encode($response);
		exit;
	}
	
	/**
	 * Rest Method for calculating total storage used by the specified session
	 *
	 * @method 		POST
	 * @required	Video session id
	 */
	public static function calculateSessionStorage($id) {
		$request = Slim::getInstance()->request();
		self::getRest()->contentType('application/json');
		self::setUnique($id);
		self::setAPIToken($request->headers('X-Mashape-User-PublicKey')); 
		self::setSessionDirectory(self::getStorageDirectory() . '/' . self::getAPIToken() . '/' . self::getUnique());
		if(!self::isValidUser()) {
			self::getRest()->halt(403, json_encode(array('code'=>1, 'message'=>'Invalid authentication')));
		}
		if(!self::isValidSession()){
			self::getRest()->halt(400, json_encode(array('code'=>3, 'message'=>'Specified session was not found')));
		}
		$response = array();
		$response['success'] = true;
		$response['storage_bytes'] = self::calculateDirectorySizeAsBytes(self::getSessionDirectory());
		$response['storage_formatted'] = self::formatBytes(self::calculateDirectorySizeAsBytes(self::getSessionDirectory()));
		echo json_encode($response);
		exit;
	}
	
	/**	
	 * Method for checking if an API user is valid
	 *
	 * @return boolean
	 */
	private static function isValidUser() {
		return self::getAPIToken() != NULL;
	}
	
	/**
	 * Method for checking if a filter is valid
	 *
	 * @param string $filter_key
	 * @return boolean
	 */
	private static function isValidFilter($filter_key) {
		if($filter_key == '') return true;
		$matching_filter = self::getDatabase()->get_item(array(
			'TableName' => 'Filters',
			'Key' => self::getDatabase()->attributes(array(
				'HashKeyElement'  => $filter_key
			)),
			'ConsistentRead' => 'true'
		));
		if($matching_filter->isOK()){
			if((string) $matching_filter->body->Item->status->{AmazonDynamoDB::TYPE_STRING} != 'active'){
				return false;	
			}
			if((int) $matching_filter->body->Item->private->{AmazonDynamoDB::TYPE_NUMBER}){
				if(str_replace('=', '', self::maskString((string) $matching_filter->body->Item->owner->{AmazonDynamoDB::TYPE_STRING}, self::SALT)) != self::getAPIToken()){
					return false;	
				}
			}
			return true;
		} else {
			return false;	
		}
	}
	
	/**
	 * Method for checking if all filter required params are set
	 *
	 * @param Slim Request $request
	 * @return boolean
	 */
	private static function isValidFilterRequires($request) {
		if($request->params('filter') == '' || $request->params('filter') == NULL) return true;
		$matching_filter = self::getDatabase()->get_item(array(
			'TableName' => 'Filters',
			'Key' => self::getDatabase()->attributes(array(
				'HashKeyElement'  => $request->params('filter')
			)),
			'ConsistentRead' => 'true'
		));
		if($matching_filter->isOK()){
			$filter_requires = unserialize((string) $matching_filter->body->Item->required->{AmazonDynamoDB::TYPE_STRING});
			if(is_array($filter_requires) && count($filter_requires)){
				for($i = 0; $i < count($filter_requires); $i++){
					$required = $filter_requires[$i];
					if($request->params($required) == NULL && $required != 'total' && $required != 'iteration'){
						return false;
					}
				}
				return true;
			} else {
				return true;	
			}
		} else {
			return false;	
		}
	}
	
	/**
	 * Method for checking if a video is currently filtering
	 *
	 * @return boolean
	 */
	private static function isVideoConverting() {
		return is_file(self::getSessionDirectory() . '/' . self::getEncodedDirectoryName() . '/filterlock');
	}
	
	/**
	 * Method for checking if a video session exists
	 *
	 * @return boolean
	 */
	private static function isValidSession() {
		return is_dir(self::getSessionDirectory());
	}
	
	/**
	 * Method for calculating the size of a directory as bytes
	 *
	 * @return number
	 */
	private static function calculateDirectorySizeAsBytes($directory) { 
		$size = 0; 
		foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file){ 
			$size += $file->getSize(); 
		} 
		return $size; 
	} 
	
	/**
	 * Method for formating bytes
	 *
	 * @return string
	 */
	private static function formatBytes($bytes, $precision = 2) { 
		$units = array('b', 'kb', 'mb', 'gb', 'tb');
		$bytes = max($bytes, 0); 
		$pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
		$pow = min($pow, count($units) - 1); 
		$bytes /= pow(1024, $pow);
		return (string) round($bytes, $precision) . $units[$pow]; 
	} 
	
	/**
	 * Method for masking a string
	 *
	 * @return string
	 */
	public static function maskString($first, $second){
		return strrev(base64_encode($first . '.' . $second));	
	}
	
	/**
	 * Method for unmasking a string that was masked by maskString()
	 *
	 * @return string
	 */
	public static function unMaskString($str){
		return str_replace('.', '/', base64_decode(strrev($str)));
	}
	
	/**
	 * Method for generating a unique hash
	 *
	 * @return string
	 */
	private static function getHash($length = 15){
		$hash = crypt(uniqid(rand() ,1)); 
		$hash = strip_tags(stripslashes($hash));
		$hash = str_replace(array('.', '/'), '', $hash);
		$hash = strrev($hash);
		$hash = substr($hash, 0, $length > strlen($hash) ? strlen($hash) : $length);
		return $hash;
	}
	
	/**
	 * Method for deleting a directory and its contents
	 *
	 * @param string $directory
	 */
	private static function deleteDirectoryRecursive($directory) {
		if(!scandir($directory)){
			self::getRest()->halt(400, json_encode(array('code'=>109,'message'=>'Unable to fulfill request')));
		}
		foreach (scandir($directory) as $item) {
			if ($item == '.' || $item == '..') continue;
			if(is_dir($directory.DIRECTORY_SEPARATOR.$item)){
				self::deleteDirectoryRecursive($directory.DIRECTORY_SEPARATOR.$item);
			} else {
				if(!unlink($directory.DIRECTORY_SEPARATOR.$item)){
					self::getRest()->halt(400, json_encode(array('code'=>106,'message'=>'Unable to fulfill request')));	
				}
			}
		}
		if(!rmdir($directory)){
			self::getRest()->halt(400, json_encode(array('code'=>110,'message'=>'Unable to fulfill request')));	
		}
	}
	
	/**
	 * Method for getting information of a video file
	 *
	 * @param string $file_path
	 * @return array
	 */
	private static function getVideoInfo($file_path){
		$exec_status = 1;
		exec(self::getFFMPEG() . ' -i ' . $file_path . ' 2>&1', $buffer, $exec_status);
		if($exec_status != 1){
			self::getRest()->halt(400, json_encode(array('code'=>111,'message'=>'Unable to fulfill request with exit code [' . $exec_status . ']')));	
		}
		$buffer = implode("\r\n", $buffer);
		$data = array();
		preg_match_all('/Duration: (.*)/', $buffer, $matches);
		if(count($matches) > 0){
			$line = trim($matches[0][0]);
			preg_match_all('/(Duration|start|bitrate): ([^,]*)/', $line, $matches);
			$data['duration'] = array(
				'timecode' => array(
					'seconds' => array(
						'exact' => -1,
						'excess' => -1
					),
					'rounded' => -1,
				)
			);
			foreach ($matches[1] as $key => $detail){
				$value = $matches[2][$key];
				switch(strtolower($detail)){
					case 'duration' :
						$data['duration']['timecode']['rounded'] = substr($value, 0, 8);
						$data['duration']['timecode']['frames'] = array();
						$data['duration']['timecode']['frames']['exact'] = $value;
						$data['duration']['timecode']['frames']['excess'] = intval(substr($value, 9));
					break;
					case 'bitrate' :
						$data['bitrate'] = strtoupper($value) === 'N/A' ? -1 : intval($value);
					break;
					case 'start' :
						$data['duration']['start'] = $value;
					break;
				}
			}
		}
		preg_match('/Stream(.*): Video: (.*)/', $buffer, $matches);
		if(count($matches) > 0){
			$data['video'] = array();
			preg_match_all('/([0-9]{1,5})x([0-9]{1,5})/', $matches[2], $dimensions_matches);
			$dimensions_value = $dimensions_matches[0];
			$data['video']['dimensions'] 	= array(
				'width' => floatval($dimensions_matches[1][1]),
				'height' => floatval($dimensions_matches[2][1])
			);
			$data['video']['time_bases'] = array();  
			preg_match_all('/([0-9\.k]+) (fps|tbr|tbc|tbn)/', $matches[0], $fps_matches);  
			if(count($fps_matches[0]) > 0){   
				foreach ($fps_matches[2] as $key => $abrv){
					$data['video']['time_bases'][$abrv] = $fps_matches[1][$key];
				}  
			}                
			$fps = isset($data['video']['time_bases']['fps']) === true ? $data['video']['time_bases']['fps'] : (isset($data['video']['time_bases']['tbr']) === true ? $data['video']['time_bases']['tbr'] : false);   
			if($fps !== false){     
				$fps = floatval($fps);
				$data['duration']['timecode']['frames']['frame_rate'] = $data['video']['frame_rate'] = $fps;
				$data['duration']['timecode']['seconds']['total'] = $data['duration']['seconds'] = $data['duration']['timecode']['frames']['exact'];
			}
			$fps_value = $fps_matches[0];
			preg_match('/\[PAR ([0-9\:\.]+) DAR ([0-9\:\.]+)\]/', $matches[0], $ratio_matches);
			if(count($ratio_matches)){
				$data['video']['pixel_aspect_ratio'] 	= $ratio_matches[1];
				$data['video']['display_aspect_ratio'] 	= $ratio_matches[2];
			}
			if(isset($data['duration']) === true && isset($data['video']) === true){
				$data['video']['frame_count'] = ceil($data['duration']['seconds'] * $data['video']['frame_rate']);
				$data['duration']['timecode']['seconds']['excess'] = floatval($data['duration']['seconds']) - floor($data['duration']['seconds']);
				$data['duration']['timecode']['seconds']['exact'] = $data['duration']['seconds'];
				$data['duration']['timecode']['frames']['exact'] = $data['video']['frame_count'];
				$data['duration']['timecode']['frames']['total'] = $data['video']['frame_count'];
			}
			$parts = explode(',', $matches[2]);
			$other_parts = array($dimensions_value, $fps_value);
			$formats = array();
			foreach($parts as $key=>$part){
				$part = trim($part);
				if(!in_array($part, $other_parts)){
					array_push($formats, $part);
				}
			}
			$data['video']['pixel_format'] 	= $formats[1];
			$data['video']['codec'] = $formats[0];
		}
	
		return $data;
	}
	
	
	/**
	 * @param string $unique
	 */
	public static function setUnique($unique) {
		self::$unique = $unique;
	}
	
	/**
	 * @return string
	 */
	public static function getUnique() {
		return self::$unique;
	}
	
	/**
	 * @param string $file_extension
	 */
	public static function setFileExtension($file_extension) {
		self::$file_extension = $file_extension;
	}
	
	/**
	 * @return string
	 */
	public static function getFileExtension() {
		return self::$file_extension;
	}
	
	/**
	 * @param string $working_item_name
	 */
	public static function setWorkingVideoName($working_item_name) {
		self::$working_item_name = $working_item_name;
	}
	
	/**
	 * @return string
	 */
	public static function getWorkingItemName() {
		return self::$working_item_name;
	}
	
	/**
	 * @param string $original_item_name
	 */
	public static function setOriginalItemName($original_item_name) {
		self::$original_item_name = $original_item_name;
	}
	
	/**
	 * @return string
	 */
	public static function getOriginalItemName() {
		return self::$original_item_name;
	}
	
	/**
	 * @param string $encoded_dir
	 */
	public static function setEncodedDirectory($encoded_dir) {
		self::$encoded_dir = $encoded_dir;
	}
	
	/**
	 * @return string
	 */
	public static function getEncodedDirectory() {
		return self::$encoded_dir;
	}
	
	/**
	 * @param string $encoded_dir_name
	 */
	public static function setEncodedDirectoryName($encoded_dir_name) {
		self::$encoded_dir_name = $encoded_dir_name;
	}
	
	/**
	 * @return string
	 */
	public static function getEncodedDirectoryName() {
		return self::$encoded_dir_name;
	}
	
	/**
	 * @param string $uploaded_dir
	 */
	public static function setUploadedDirectory($uploaded_dir) {
		self::$uploaded_dir = $uploaded_dir;
	}
	
	/**
	 * @return string
	 */
	public static function getUploadedDirectory() {
		return self::$uploaded_dir;
	}
	
	/**
	 * @param string $uploaded_dir_name
	 */
	public static function setUploadedDirectoryName($uploaded_dir_name) {
		self::$uploaded_dir_name = $uploaded_dir_name;
	}
	
	/**
	 * @return string
	 */
	public static function getUploadedDirectoryName() {
		return self::$uploaded_dir_name;
	}
	
	/**
	 * @param string $session_dir
	 */
	public static function setSessionDirectory($session_dir) {
		self::$session_dir = $session_dir;
	}
	
	/**
	 * @return string
	 */
	public static function getSessionDirectory() {
		return self::$session_dir;
	}
	
	/**
	 * @param string $storage_dir
	 */
	public static function setStorageDirectory($storage_dir) {
		self::$storage_dir = $storage_dir;
	}
	
	/**
	 * @return string
	 */
	public static function getStorageDirectory() {
		return self::$storage_dir;
	}
	
	/**
	 * @param string $templates_dir_name
	 */
	public static function setTemplatesDirectoryName($templates_dir_name) {
		self::$templates_dir_name = $templates_dir_name;
	}
	
	/**
	 * @return string
	 */
	public static function getTemplatesDirectoryName() {
		return self::$templates_dir_name;
	}
	
	/**
	 * @param string $sessions_dir_name
	 */
	public static function setSessionsDirectoryName($sessions_dir_name) {
		self::$sessions_dir_name = $sessions_dir_name;
	}
	
	/**
	 * @return string
	 */
	public static function getSessionsDirectoryName() {
		return self::$sessions_dir_name;
	}
	
	/**
	 * @param string $server_root
	 */
	public static function setServerRoot($server_root) {
		self::$server_root = $server_root;
	}
	
	/**
	 * @return string
	 */
	public static function getServerRoot() {
		return self::$server_root;
	}
	
	/**
	 * @param string $imageMagickBase
	 */
	public static function setImageMagickBase($imageMagickBase) {
		self::$imageMagickBase = $imageMagickBase;
	}
	
	/**
	 * @return string
	 */
	public static function getImageMagickBase() {
		return self::$imageMagickBase;
	}
	
	/**
	 * @param string $ffmpeg
	 */
	public static function setFFMPEG($ffmpeg) {
		self::$ffmpeg = $ffmpeg;
	}
	
	/**
	 * @return string
	 */
	public static function getFFMPEG() {
		return self::$ffmpeg;
	}
	
	/**
	 * @param string $php
	 */
	public static function setPHP($php) {
		self::$php = $php;
	}
	
	/**
	 * @return string
	 */
	public static function getPHP() {
		return self::$php;
	}
	
	/**
	 * @param string $apitoken
	 */
	public static function setAPIToken($apitoken) {	
		if($apitoken == '' || $apitoken == NULL) return;
		$apitoken = str_replace('=', '', self::maskString($apitoken, self::SALT));
		self::$apitoken = $apitoken;
	}
	
	/**
	 * @return string
	 */
	public static function getAPIToken() {
		return self::$apitoken;
	}
	
	/**
	 * @param class $database
	 */
	public static function setDatabase($database) {
		self::$database = $database;
	}
	
	/**
	 * @return class
	 */
	public static function getDatabase() {
		return self::$database;
	}
	
	/**
	 * @param class $rest
	 */
	public static function setRest($rest) {
		self::$rest = $rest;
	}
	
	/**
	 * @return class
	 */
	public static function getRest() {
		return self::$rest;
	}
}

?>