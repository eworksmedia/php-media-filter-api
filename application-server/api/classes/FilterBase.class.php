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

/**
 * FilterBase
 * 
 */
class FilterBase {	
	/**
     * @var unix path to command base
	 * @required
     */
	public static $commandBase = '';
	/**
     * @var string $command
     */
	public static $command = '';
	
	/**
     * @var server path to filters border directory
	 * @required
     */
	private static $filters_border_directory;
	
	/**
     * @var server path to filters border directory
	 * @required
     */
	private static $filters_colormaps_directory;
	
	/**
     * __construct
     *
     * @return void
     * @throws Exception
     */
	public function __construct() {
		throw new Exception('FilterBase is a static class. No instances can be created.');
	}	

	/**
     * Method for adding a command to the command queue
     *
	 * @required	command as string 
     */
	public static function addCommand($str) {
		if(strpos($str, self::getCommandBase()) === false) $str = self::getCommandBase() . $str;
		self::$command .= $str . (strstr($str, ';') ? '' : ';');
	}
	
	/**
     * Method for clearing the command queue
     *
     */
	public static function clearCommand() {
		self::$command = '';
	}
	
	/**
     * Method for running an ImageMagick filter command
     *
     */
	public static function run() {
		exec(self::$command);
		self::clearCommand();
	}
	
	/**
     * Method for creating a Colortone filter command
     *
     */
	public static function colortone($input, $output, $color, $level, $type = 0) {
        $args[0] = $level;
        $args[1] = 100 - $level;
        $negate = $type == 0 ? '-negate': '';
 		
        return "convert " . $input . " \( -clone 0 -fill \"" . $color . "\" -colorize 100% \) \( -clone 0 -colorspace gray " . $negate . " \) -compose blend -define compose:args=" . $args[0] . "," . $args[1] . " -composite " . $output . ";";
    }
	
	/**
     * Method for creating a Vignette filter command
     *
     */
	public static function vignette($input, $output, $width, $height, $color_1 = 'none', $color_2 = 'black', $crop_factor = 1.5) {
        $crop_x = floor($width * $crop_factor);
        $crop_y = floor($height * $crop_factor);
 
        return "convert " . $input . " \( -size " . $crop_x . "x" . $crop_y . " radial-gradient:" . $color_1 . "-" . $color_2 . " -gravity center -crop " . $width . "x" . $height . "+0+0 +repage \) -compose multiply -flatten " . $output . ";";
    }
	
	/**
     * @param string $filters_colormaps_directory
     */
	public static function setFiltersColormapDirectory($filters_colormaps_directory) {
		self::$filters_colormaps_directory = $filters_colormaps_directory;
	}
	
	/**
     * @return string
     */
	public static function getFiltersColormapDirectory() {
		return self::$filters_colormaps_directory;
	}
	
	/**
     * @param string $filters_border_directory
     */
	public static function setFiltersBorderDirectory($filters_border_directory) {
		self::$filters_border_directory = $filters_border_directory;
	}
	
	/**
     * @return string
     */
	public static function getFiltersBorderDirectory() {
		return self::$filters_border_directory;
	}
	
	/**
     * @param string $commandBase
     */
	public static function setCommandBase($commandBase) {
		self::$commandBase = $commandBase;
	}
	
	/**
     * @return string
     */
	public static function getCommandBase() {
		return self::$commandBase;
	}
	
}
?>