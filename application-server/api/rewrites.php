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

require 'classes/API.class.php';

$media = $_GET['media'];
$type = $_GET['type'];
$key = $_GET['key'];
if(empty($media) || empty($type) || empty($key)){
	header('HTTP/1.0 404 Not Found');
	header('Status: 404 Not Found');
}

if($media == 'video'){
	switch($type){
		case 'original-image':
			header('Content-Type: ' . mime_content_type(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/preview.png'));		
			if (isset($_SERVER['HTTP_RANGE']))  {
				rangeDownload(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/preview.png');
			} else {
				header('Content-Length: '.filesize(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/preview.png'));
				readfile(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/preview.png');
			}
		break;
		case 'preview-image':
			header('Content-Type: ' . mime_content_type(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/preview-working.png'));		
			if (isset($_SERVER['HTTP_RANGE']))  {
				rangeDownload(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/preview-working.png');
			} else {
				header('Content-Length: '.filesize(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/preview-working.png'));
				readfile(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/preview-working.png');
			}
		break;
		case 'processed-image':
			$file = $_GET['file'];
			if(empty($file)){
				header('HTTP/1.0 404 Not Found');
				header('Status: 404 Not Found');
			}
			header('Content-Type: ' . mime_content_type(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/images/' . $file));		
			if (isset($_SERVER['HTTP_RANGE']))  {
				rangeDownload(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/images/' . $file);
			} else {
				header('Content-Length: '.filesize(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/images/' . $file));
				readfile(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/images/' . $file);
			}
		break;
		case 'filtered-video':
			header('Content-Type: ' . mime_content_type(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/video/filtered.mp4'));
			if (isset($_SERVER['HTTP_RANGE']))  {
				rangeDownload(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/video/filtered.mp4');
			} else {
				header('Content-Length: '.filesize(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/video/filtered.mp4'));
				readfile(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/video/filtered.mp4');
			}
		break;
		case 'original-video':
			$extension = $_GET['extension'];
			if(empty($extension)){
				header('HTTP/1.0 404 Not Found');
				header('Status: 404 Not Found');
			}
			header('Content-Type: ' . mime_content_type(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/uploaded/original.' . $extension));
			if (isset($_SERVER['HTTP_RANGE']))  {
				rangeDownload(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/video/filtered.mp4');
			} else {
				header('Content-Length: '.filesize(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/uploaded/original.' . $extension));
				readfile(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/uploaded/original.' . $extension);
			}
		break;
	}
} else if($media == 'photo'){
	switch($type){
		case 'original-image':
			$extension = $_GET['extension'];
			header('Content-Type: ' . mime_content_type(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/original.' . $extension));
			if (isset($_SERVER['HTTP_RANGE']))  {
				rangeDownload(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/original.' . $extension);
			} else {
				header('Content-Length: '.filesize(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/original.' . $extension));
				readfile(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/original.' . $extension);
			}
		break;
		case 'filtered-image':
			$extension = $_GET['extension'];
			header('Content-Type: ' . mime_content_type(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/filtered.' . $extension));
			if (isset($_SERVER['HTTP_RANGE']))  {
				rangeDownload(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/filtered.' . $extension);
			} else {
				header('Content-Length: '.filesize(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/filtered.' . $extension));
				readfile(API::getServerRoot() . '/' . API::getSessionsDirectoryName() . '/' . $key . '/encoded/filtered.' . $extension);
			}
		break;
	}
}

function rangeDownload($file) {
 
	$fp = @fopen($file, 'rb');
 
	$size   = filesize($file); // File size
	$length = $size;           // Content length
	$start  = 0;               // Start byte
	$end    = $size - 1;       // End byte
	// Now that we've gotten so far without errors we send the accept range header
	/* At the moment we only support single ranges.
	 * Multiple ranges requires some more work to ensure it works correctly
	 * and comply with the spesifications: http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2
	 *
	 * Multirange support annouces itself with:
	 * header('Accept-Ranges: bytes');
	 *
	 * Multirange content must be sent with multipart/byteranges mediatype,
	 * (mediatype = mimetype)
	 * as well as a boundry header to indicate the various chunks of data.
	 */
	header("Accept-Ranges: 0-$length");
	// header('Accept-Ranges: bytes');
	// multipart/byteranges
	// http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2
	if (isset($_SERVER['HTTP_RANGE'])) {
 
		$c_start = $start;
		$c_end   = $end;
		// Extract the range string
		list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
		// Make sure the client hasn't sent us a multibyte range
		if (strpos($range, ',') !== false) {
 
			// (?) Shoud this be issued here, or should the first
			// range be used? Or should the header be ignored and
			// we output the whole content?
			header('HTTP/1.1 416 Requested Range Not Satisfiable');
			header("Content-Range: bytes $start-$end/$size");
			// (?) Echo some info to the client?
			exit;
		}
		// If the range starts with an '-' we start from the beginning
		// If not, we forward the file pointer
		// And make sure to get the end byte if spesified
		if ($range0 == '-') {
 
			// The n-number of the last bytes is requested
			$c_start = $size - substr($range, 1);
		}
		else {
 
			$range  = explode('-', $range);
			$c_start = $range[0];
			$c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
		}
		/* Check the range and make sure it's treated according to the specs.
		 * http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
		 */
		// End bytes can not be larger than $end.
		$c_end = ($c_end > $end) ? $end : $c_end;
		// Validate the requested range and return an error if it's not correct.
		if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
 
			header('HTTP/1.1 416 Requested Range Not Satisfiable');
			header("Content-Range: bytes $start-$end/$size");
			// (?) Echo some info to the client?
			exit;
		}
		$start  = $c_start;
		$end    = $c_end;
		$length = $end - $start + 1; // Calculate new content length
		fseek($fp, $start);
		header('HTTP/1.1 206 Partial Content');
	}
	// Notify the client the byte range we'll be outputting
	header("Content-Range: bytes $start-$end/$size");
	header("Content-Length: $length");
 
	// Start buffered download
	$buffer = 1024 * 8;
	while(!feof($fp) && ($p = ftell($fp)) <= $end) {
 
		if ($p + $buffer > $end) {
 
			// In case we're only outputtin a chunk, make sure we don't
			// read past the length
			$buffer = $end - $p + 1;
		}
		set_time_limit(0); // Reset time limit for big files
		echo fread($fp, $buffer);
		flush(); // Free up memory. Otherwise large files will trigger PHP's memory limit.
	}
 
	fclose($fp);
 
}

?>