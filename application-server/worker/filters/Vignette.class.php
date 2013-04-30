<?php
/**
 * Vignette
 * 
 */
 
class Vignette extends FilterBase {	
	/**
     * @var number $width
     */
	private static $width;
	
	/**
     * @var number $height
     */
	private static $height;
	
	/**
     * __construct
     *
     * @return void
     * @throws Exception
     */
	public function __construct() {
		throw new Exception('Vignette is a static class. No instances can be created.');
	}	
	
	/**
     * Method for filtering an image
     *
     */
	public static function filter($original_image, $preview_image) {
		list(self::$width, self::$height) = getimagesize($original_image);
    	self::addCommand("convert " . $original_image . " -colorspace Gray -sepia-tone \"90%\" " . $preview_image);
		self::addCommand(self::vignette($preview_image, $preview_image, self::$width, self::$height));
		self::run();
	}
	
}
?>