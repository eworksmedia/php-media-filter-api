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
 
include '/var/www/html/api/classes/FilterBase.class.php'; 

$filters_directory = dir('/var/www/html/worker/filters'); 
while (false !== ($filename = $filters_directory->read())) { 
	if (strstr($filename, 'class')) {
		include_once '/var/www/html/worker/filters/' . $filename;
	} 
} 
$filters_directory->close();
 
class FilterFactory {
	/**
     * @var unix path to command base
	 * @required
     */
	private static $commandBase;
	/**
     * @var array of filter properties $filter
	 * @required
     */
	private static $filter;
	
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
     * @var path to original image $original_image_path
	 * @required
     */
	private static $original_image_path;	

	/**
     * @var path to preview image $filtered_image_path
	 * @required
     */
	private static $filtered_image_path;
	
	/**
     * @var number $total
	 * @required
     */
	private static $total;
	
	/**
     * @var number $iteration
	 * @required
     */
	private static $iteration;
	
	/**
     * @var number $red
	 * @required
     */
	private static $red;
	
	/**
     * @var number $green
	 * @required
     */
	private static $green;
	
	/**
     * @var number $blue
	 * @required
     */
	private static $blue;
	
	/**
     * @var number $saturation
	 * @required
     */
	private static $saturation;

	
	/**
     * __construct
     *
     * @return void
     * @throws Exception
     */
	public function __construct() {
		throw new Exception('FilterFactory is a static class. No instances can be created.');
	}
	
	/**
     * Method for initiating a filter sessions
     *
     */
	public static function init($filter, $original_image_path, $filtered_image_path, $total, $iteration, $red, $green, $blue, $saturation) {
		FilterBase::setCommandBase(self::getCommandBase());
		FilterBase::setFiltersBorderDirectory(self::getFiltersBorderDirectory());
		FilterBase::setFiltersColormapDirectory(self::getFiltersColormapDirectory());
		self::setFilter($filter);
		self::setOriginalImagePath($original_image_path);
		self::setFilteredImagePath($filtered_image_path);
		self::setTotal($total);
		self::setIteration($iteration);
		self::setRed($red);
		self::setGreen($green);
		self::setBlue($blue);
		self::setSaturation($saturation);
		$filter_class = self::getFilterProperty('class_name');
		$filter_requires = unserialize(self::getFilterProperty('required'));
		$filter_options = array();
		if(is_array($filter_requires)){
			for($i = 0; $i < count($filter_requires); $i++){
				if(strstr($filter_requires[$i], 'saturation')){
					$filter_options['saturation'] = self::getSaturation();
				}
				if(strstr($filter_requires[$i], 'red')){
					$filter_options['red'] = self::getRed();
				}
				if(strstr($filter_requires[$i], 'green') ){
					$filter_options['green'] = self::getGreen();
				}
				if(strstr($filter_requires[$i], 'blue')){
					$filter_options['blue'] = self::getBlue();
				}
				if(strstr($filter_requires[$i], 'total')){
					$filter_options['total'] = self::getTotal();
				}
				if(strstr($filter_requires[$i], 'iteration')){
					$filter_options['iteration'] = self::getIteration();
				}
			}
		}
		if(count($filter_options)){
			$filter_class::filter(self::getOriginalImagePath(), self::getFilteredImagePath(), $filter_options);
		} else {
			$filter_class::filter(self::getOriginalImagePath(), self::getFilteredImagePath());	
		}
	}
	
	/**
     * @param number $saturation
     */
	public static function setSaturation($saturation) {
		self::$saturation = $saturation;
	}
	
	/**
     * @return number
     */
	public static function getSaturation() {
		return self::$saturation;
	}
	
	/**
     * @param number $blue
     */
	public static function setBlue($blue) {
		self::$blue = $blue;
	}
	
	/**
     * @return number
     */
	public static function getBlue() {
		return self::$blue;
	}
	
	/**
     * @param number $green
     */
	public static function setGreen($green) {
		self::$green = $green;
	}
	
	/**
     * @return number
     */
	public static function getGreen() {
		return self::$green;
	}
	
	/**
     * @param number $red
     */
	public static function setRed($red) {
		self::$red = $red;
	}
	
	/**
     * @return number
     */
	public static function getRed() {
		return self::$red;
	}
	
	/**
     * @param number $iteration
     */
	public static function setIteration($iteration) {
		self::$iteration = $iteration;
	}
	
	/**
     * @return number
     */
	public static function getIteration() {
		return self::$iteration;
	}
	
	/**
     * @param number $total
     */
	public static function setTotal($total) {
		self::$total = $total;
	}
	
	/**
     * @return number
     */
	public static function getTotal() {
		return self::$total;
	}
	
	/**
     * @param path to preview image $filtered_image_path
     */
	public static function setFilteredImagePath($filtered_image_path) {
		self::$filtered_image_path = $filtered_image_path;
	}
	
	/**
     * @return path to preview image
     */
	public static function getFilteredImagePath() {
		return self::$filtered_image_path;
	}
	
	/**
     * @param path to orignal image $original_image_path
     */
	public static function setOriginalImagePath($original_image_path) {
		self::$original_image_path = $original_image_path;
	}
	
	/**
     * @return path to orignal image
     */
	public static function getOriginalImagePath() {
		return self::$original_image_path;
	}

	/**
     * @return value
     */
	public static function getFilterProperty($prop) {
		return self::$filter[$prop];
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
     * @param array $filter
     */
	public static function setFilter($filter) {
		self::$filter = $filter;
	}
	
	/**
     * @return array
     */
	public static function getFilter() {
		return self::$filter;
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