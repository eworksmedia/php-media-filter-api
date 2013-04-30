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

$session_directory = '/var/www/html/sessions';
$today = strtotime('12:00 am', time());

traverse($session_directory, $today);

function traverse($directory, $today) {
	$dir = opendir($directory);
	while ($developer = readdir($dir)) {
		if (is_dir($directory.DIRECTORY_SEPARATOR.$developer)) {
			$developer_dir = opendir($directory.DIRECTORY_SEPARATOR.$developer);
			while ($session = readdir($developer_dir)) {
				if (is_dir($directory.DIRECTORY_SEPARATOR.$developer.DIRECTORY_SEPARATOR.$session)) {
					$session_dir = $directory.DIRECTORY_SEPARATOR.$developer.DIRECTORY_SEPARATOR.$session;
					if(is_file($session_dir.DIRECTORY_SEPARATOR.'expire')){
						$session_expire_date = @file_get_contents($session_dir.DIRECTORY_SEPARATOR.'expire');
						if($today > $session_expire_date){
							deleteDirectoryRecursive($session_dir);
						}
					}
				}
			}
		}
	}
	closedir($dir);
}

function deleteDirectoryRecursive($directory) {
	foreach (scandir($directory) as $item) {
		if ($item == '.' || $item == '..') continue;
		if(is_dir($directory.DIRECTORY_SEPARATOR.$item)){
			deleteDirectoryRecursive($directory.DIRECTORY_SEPARATOR.$item);
		} else {
			unlink($directory.DIRECTORY_SEPARATOR.$item);
		}
	}
	rmdir($directory);
}

?>