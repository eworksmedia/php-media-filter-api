<?php
/**
 * Grayscale
 * 
 */
 
class Grayscale extends FilterBase {	
	/**
     * __construct
     *
     * @return void
     * @throws Exception
     */
	public function __construct() {
		throw new Exception('Grayscale is a static class. No instances can be created.');
	}	
	
	/**
     * Method for filtering an image
     *
     */
	public static function filter($original_image, $preview_image) {
		self::addCommand("convert " . $original_image . " -colorspace Gray " . $preview_image);
		self::run();
	}
	
}
?>