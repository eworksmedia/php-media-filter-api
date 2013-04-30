<?php
#!/usr/bin/php

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

set_time_limit(0);

include_once('/var/www/html/api/aws-sdk/sdk.class.php');
include_once('/var/www/html/api/classes/FilterFactory.class.php');	// not in Class scope since called via exec

$start = time();
$unique = urldecode($argv[1]);
$session = substr($unique, strpos($unique, '/') + 1, strlen($unique));
$ffmpeg = '/usr/local/bin/ffmpeg';
$image_magick_base = '/usr/local/bin/';
$server_root = '/var/www/html';
$videos_dir = $server_root . '/sessions/' . $unique;
$uploaded_dir = $videos_dir . '/uploaded';
$original_video = 'original';
$working_video = 'filtered';
$encoded_dir = $videos_dir . '/encoded';
$video_dir = $encoded_dir . '/video';
$image_dir = $encoded_dir . '/images';
$progress_log = $encoded_dir . '/progress.log';
$subtitle_file = $encoded_dir . '/subs.ass';
$filter_options = file_get_contents($encoded_dir . '/filter-options.log');
if(!$filter_options){
	updateProgress($progress_log, false, $session, 0, 9, 'Unable to fulfill request', 103);
	exit;
}
$filter_options = json_decode($filter_options, true);
$filter_key = $filter_options['filter'];
$red = $filter_options['filterred'];
$blue = $filter_options['filterblue'];
$green = $filter_options['filtergreen'];
$saturation = $filter_options['saturation'];
$overlay_text = $filter_options['overlaytext'];
$overlay_font = $filter_options['overlayfont'];
$overlay_font_color = $filter_options['overlayfontcolor'];
$overlay_font_size = $filter_options['overlayfontsize'];
$overlay_font_bordersize = $filter_options['overlayfontbordersize'];
$overlay_font_bordercolor = $filter_options['overlayfontbordercolor'];
$overlay_shadow_size = $filter_options['overlayshadowsize'];
$overlay_shadow_color = $filter_options['overlayshadowcolor'];
$overlay_text_start = $filter_options['overlaytextstart'];
$overlay_text_end = $filter_options['overlaytextend'];
$overlay_position = $filter_options['overlaytextposition'];
$fade = $filter_options['fade'];
$images = getNonFilteredImagesAsArray($image_dir);
FilterFactory::setCommandBase($image_magick_base);
FilterFactory::setFiltersBorderDirectory($server_root . '/worker/filters/borders/');
FilterFactory::setFiltersColormapDirectory($server_root . '/worker/filters/colormaps/');
$first_filter_pass = true;

$fileextension = file_get_contents($uploaded_dir . '/ext');
if(!$fileextension){
	updateProgress($progress_log, false, $session, 0, 9, 'Unable to fulfill request', 103);
	exit;
}

if(!is_dir($encoded_dir)){
	if(!mkdir($encoded_dir)){
		updateProgress($progress_log, false, $session, 0, 9, 'Unable to fulfill request', 100);
		exit;	
	}
}
if(!is_dir($video_dir)){
	if(!mkdir($video_dir)){
		updateProgress($progress_log, false, $session, 0, 9, 'Unable to fulfill request', 100);	
		exit;
	}
}

$database = new AmazonDynamoDB();
$filter = array();
$matching_filter = $database->get_item(array(
	'TableName' => 'Filters',
	'Key' => $database->attributes(array(
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
} else {
	updateProgress($progress_log, false, $session, 0, 9, 'Specified filter not found', 6);
	exit;	
}

if(!touch($encoded_dir . '/filterlock')){
	updateProgress($progress_log, false, $session, 0, 9, 'Unable to fulfill request', 112);
	exit;
}
if(!touch($encoded_dir . '/pid')){
	updateProgress($progress_log, false, $session, 0, 9, 'Unable to fulfill request', 112);
	unlink($encoded_dir . '/filterlock');
	exit;	
}
if(!@file_put_contents($encoded_dir . '/pid', getmypid())){
	updateProgress($progress_log, false, $session, 0, 9, 'Unable to fulfill request', 104);
	unlink($encoded_dir . '/filterlock');
	exit;
}

$video_info = getVideoInfo($uploaded_dir . '/' . $working_video . '.' . $fileextension, $ffmpeg, $session); // getVideoInfo not in class scope since called via exec
$duration_parts = explode(':', $video_info['duration']['timecode']['rounded']);
$duration = ($duration_parts[0] * 3600) + ($duration_parts[1] * 60) + $duration_parts[2];
$bitrate = $video_info['bitrate'];
$width = $video_info['video']['dimensions']['width'];
$height = $video_info['video']['dimensions']['height'];
$framerate = $video_info['video']['frame_rate'];
$codec = $video_info['video']['codec'];

// copy subtitles template to encode directory / make subtitles if encountered
if(!copy($server_root . '/templates/subs.ass', $subtitle_file)){
	updateProgress($progress_log, false, $session, 0, 9, 'Unable to fulfill request', 102);
	unlink($encoded_dir . '/filterlock');
	exit;	
}
if($overlay_text != 'none') generateSubtitles($subtitle_file, $overlay_text, $width, $height, $overlay_font, $overlay_font_color, $overlay_font_size, $overlay_font_bordersize, $overlay_font_bordercolor, $overlay_shadow_size, $overlay_shadow_color, $overlay_text_start, $overlay_text_end, $overlay_position);

// grab audio from video
updateProgress($progress_log, true, $session, 1, 9, 'extract audio');
$exec_status = 0;
exec($ffmpeg . ' -i ' . $uploaded_dir . '/' . $working_video . '.' . $fileextension . ' -vn -ar 44100 -ac 2 -ab 192 -y -f mp3 ' . $encoded_dir . '/audio.mp3 &> /dev/null &', $buffer, $exec_status);
if($exec_status != 0){
	updateProgress($progress_log, false, $session, 1, 9, 'Unable to fulfill request with exit code [' . $exec_status . ']', 111);
	unlink($encoded_dir . '/filterlock');
	exit;
}
updateProgress($progress_log, true, $session, 2, 9, 'extract audio complete');

// filter images
updateProgress($progress_log, true, $session, 5, 9, 'filter start');
$filter_progress = array();
$filter_progress['text'] = 'filter progress';
while($first_filter_pass || filteringNotComplete($image_dir)){
	if($first_filter_pass){
		$images = getNonFilteredImagesAsArray($image_dir);
	} else {
		$images = getNonFilteredImagesDifferenceAsArray($image_dir);
	}
	$first_filter_pass = false;
	filter($session, $image_dir, $images, $filter, $filter_progress, $progress_log, $red, $green, $blue, $saturation);	
}
$images = getNonFilteredImagesAsArray($image_dir);
updateProgress($progress_log, true, $session, 7, 9, 'filter complete');

// build video ffmpeg filters
$filtereffect = '';
switch($fade){
	case 'fadein':
		$filtereffect .= 'fade=in:0:30';
	break;
	case 'fadeout':
		$filtereffect .= 'fade=out:' . (count($images) - 30) . ':30';
	break;
	case 'fadeboth':
		$filtereffect .= 'fade=in:0:30,fade=out:' . (count($images) - 30) . ':30';
	break;
}
if($overlay_text != 'none'){
	$filtereffect .= (!empty($filtereffect) ? ',' : '') . 'ass=' . $subtitle_file;
}

// convert image sequence into video && add audio
updateProgress($progress_log, true, $session, 8, 9, 'video compile start');
$exec_status = 0;
exec($ffmpeg . ' -i ' . $encoded_dir . '/audio.mp3 -f image2 -r ' . $framerate . ' -i ' . $image_dir . '/%6d-filtered.png -vcodec libx264 -acodec libfaac -ab "96k" -ac 2 -b:v "' . $bitrate . 'K" -pix_fmt yuv420p ' . ($filtereffect != '' ? '-vf "' . $filtereffect . '" ' : '') . '-y ' . $video_dir . '/' . $working_video . '.mp4' . ' 1>' . $encoded_dir . '/ffmpeg.log 2>&1', $buffer, $exec_status);
if($exec_status != 0){
	updateProgress($progress_log, false, $session, 8, 9, 'Unable to fulfill request with exit code [' . $exec_status . ']', 111);
	unlink($encoded_dir . '/filterlock');
	exit;
}
$done = time();
unlink($encoded_dir . '/filterlock');
updateProgress($progress_log, true, $session, 9, 9, array('text'=>'video compile complete', 'time'=>timeDiff($start, $done)));

// filter images
function filter($session, $image_dir, $images, $filter, $filter_progress, $progress_log, $red, $green, $blue, $saturation){
	for($i = 0; $i < count($images); $i++){
		$filtered_image_path = $image_dir . '/' . substr(basename($images[$i]), 0, -4) . '-filtered.png';
		if(is_array($filter)){
			FilterFactory::init($filter, $images[$i], $filtered_image_path, count($images), $i, $red, $green, $blue, $saturation);
		}
		$filter_progress['progress_percent'] = round($i / count($images) * 100);
		updateProgress($progress_log, true, $session, 6, 9, $filter_progress);
	}
	sleep(2);
}

// check if filtering is not complete
function filteringNotComplete($image_dir){
	$images = getNonFilteredImagesAsArray($image_dir);
	$images_filtered = getFilteredImagesAsArray($image_dir);
	return count($images) > count($images_filtered);
}

// updateProgress not in class scope since called via exec
function updateProgress($file, $success, $unique, $step, $steps, $message, $code = 0){
	$progress = array();
	$progress['success'] = $success;
	$progress['unique'] = $unique;
	$progress['step'] = $step;
	$progress['steps'] = $steps;
	$progress['message'] = $message;
	if(!$success){
		$progress['code'] = $code;
	}
	file_put_contents($file, json_encode($progress));
}

// return all non filtered images as array
function getNonFilteredImagesAsArray($folder){
	$dir = $folder;
	$folder = opendir($folder);
	$pic_types = array("png");
	$images = array();
	while ($file = readdir($folder)) {
		if(in_array(substr(strtolower($file), strrpos($file, ".") + 1), $pic_types) && !strstr($file, 'filtered')){
			array_push($images, $dir . '/' . $file);
		}
	}
	closedir($folder);
	sort($images);
	return $images;
}

// return all filtered images as array
function getFilteredImagesAsArray($folder){
	$dir = $folder;
	$folder = opendir($folder);
	$pic_types = array("png");
	$images = array();
	while ($file = readdir($folder)) {
		if(in_array(substr(strtolower($file), strrpos($file, ".") + 1), $pic_types) && strstr($file, 'filtered')){
			array_push($images, $dir . '/' . $file);
		}
	}
	closedir($folder);
	sort($images);
	return $images;
}

// return all non filtered images left
function getNonFilteredImagesDifferenceAsArray($folder){
	$dir = $folder;
	$folder = opendir($folder);
	$pic_types = array("png");
	$images = array();
	while ($file = readdir($folder)) {
		$filtered_image_path = $dir . '/' . substr(basename($file), 0, -4) . '-filtered.png';
		if(in_array(substr(strtolower($file), strrpos($file, ".") + 1), $pic_types) && !strstr($file, 'filtered') && !is_file($filtered_image_path)){
			array_push($images, $dir . '/' . $file);
		}
	}
	closedir($folder);
	sort($images);
	return $images;
}

// getVideoInfo not in class scope since called via exec
function getVideoInfo($file, $ffmpeg, $unique){
	$exec_status = 1;
	exec($ffmpeg . ' -i ' . $file . ' 2>&1', $buffer, $exec_status);
	if($exec_status != 1){
		updateProgress($progress_log, false, $unique, 0, 9, 'Unable to fulfill request with exit code [' . $exec_status . ']', 111);
		unlink($encoded_dir . '/filterlock');
		exit;
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

// generateSubtitles not in class scope since called via exec
function generateSubtitles($file, $text, $video_width, $video_height, $font, $font_color, $font_size, $border_size, $border_color, $shadow_size, $shadow_color, $overlay_text_start, $overlay_text_end, $overlay_position){
	$text = str_replace('{{break}}', '\n', $text);
	$subs = @file_get_contents($file);
	$subs = str_replace(
			array(
				'{{text}}',
				'{{width}}', 
				'{{height}}', 
				'{{font}}', 
				'{{fontcolor}}', 
				'{{fontsize}}', 
				'{{bordersize}}', 
				'{{bordercolor}}', 
				'{{shadowsize}}', 
				'{{shadowcolor}}', 
				'{{startseconds}}', 
				'{{endseconds}}', 
				'{{position}}'
			), 
			array(
				$text,
				$video_width,
				$video_height,
				$font,
				$font_color,
				$font_size,
				$border_size,
				$border_color,
				$shadow_size,
				$shadow_color,
				$overlay_text_start,
				$overlay_text_end,
				$overlay_position
			),
			$subs
	);
	file_put_contents($file, $subs);
}

function timeDiff($time1, $time2, $precision = 6) {
	// If not numeric then convert texts to unix timestamps
	if (!is_int($time1)) $time1 = strtotime($time1);
	if (!is_int($time2)) $time2 = strtotime($time2);
	
	// If time1 is bigger than time2
	// Then swap time1 and time2
	if ($time1 > $time2) {
		$ttime = $time1;
		$time1 = $time2;
		$time2 = $ttime;
	}
	
	// Set up intervals and diffs arrays
	$intervals = array('year','month','day','hour','minute','second');
	$diffs = array();
	
	// Loop thru all intervals
	foreach ($intervals as $interval) {
		// Set default diff to 0
		$diffs[$interval] = 0;
		// Create temp time from time1 and interval
		$ttime = strtotime("+1 " . $interval, $time1);
		// Loop until temp time is smaller than time2
		while ($time2 >= $ttime) {
			$time1 = $ttime;
			$diffs[$interval]++;
			// Create new temp time from time1 and interval
			$ttime = strtotime("+1 " . $interval, $time1);
		}
	}
	
	$count = 0;
	$times = array();
	// Loop thru all diffs
	foreach ($diffs as $interval => $value) {
		// Break if we have needed precission
		if ($count >= $precision) break;
		// Add value and interval 
		// if value is bigger than 0
		if ($value > 0) {
			// Add s if value is not 1
			if ($value != 1) $interval .= "s";
			// Add value and interval to times array
			$times[] = $value . " " . $interval;
			$count++;
		}
	}
	
	// Return string with times
	return implode(", ", $times);
}


?>
