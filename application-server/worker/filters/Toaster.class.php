<?php
/**
 * Toaster
 * 
 */
 
class Toaster extends FilterBase {	
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
		throw new Exception('Toaster is a static class. No instances can be created.');
	}	
	
	/**
     * Method for filtering an image
     *
     */
	public static function filter($original_image, $preview_image) {
		list(self::$width, self::$height) = getimagesize($original_image);
		self::addCommand(self::colortone($original_image, $preview_image, '#330000', 100, 0));
		self::addCommand("convert " . $preview_image . " -modulate 150,80,100 -gamma 1.2 -contrast -contrast " . $preview_image);
		self::addCommand(self::vignette($preview_image, $preview_image, self::$width, self::$height, 'none', 'LavenderBlush3'));
		self::addCommand(self::vignette($preview_image, $preview_image, self::$width, self::$height, '#ff9966', 'none'));
		self::run();
	}
	
}
?>