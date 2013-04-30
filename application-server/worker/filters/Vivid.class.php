<?php
/**
 * Vivid
 * 
 */
 
class Vivid extends FilterBase {
	/**
     * __construct
     *
     * @return void
     * @throws Exception
     */
	public function __construct() {
		throw new Exception('Vivid is a static class. No instances can be created.');
	}	
	
	/**
     * Method for filtering an image
     *
     */
	public static function filter($original_image, $preview_image) {
		self::addCommand("convert " . $original_image . " -channel R -level 23% -channel G -level 15% -channel B -level 15% " . $preview_image);
		self::addCommand("convert " . $preview_image . " -set option:modulate:colorspace hsb -modulate 90 " . $preview_image);
		self::addCommand("convert " . $preview_image . " -sigmoidal-contrast 15x50% " . $preview_image);
		self::run();
	}
	
}
?>