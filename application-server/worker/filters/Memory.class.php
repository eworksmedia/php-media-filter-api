<?php
/**
 * Memory
 * 
 */
 
class Memory extends FilterBase {
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
		throw new Exception('Memory is a static class. No instances can be created.');
	}	
	
	/**
     * Method for filtering an image
     *
     */
	public static function filter($original_image, $preview_image) {
		list(self::$width, self::$height) = getimagesize($original_image);
		self::addCommand(self::colortone($original_image, $preview_image, '#332900', 90, 0));
		self::addCommand("convert " . $preview_image . " -modulate 120,70,100 -gamma 1.5 -contrast -contrast -blur 1 " . $preview_image);
		self::addCommand(self::vignette($preview_image, $preview_image, self::$width, self::$height, 'LavenderBlush3', '#271f00', 1.5));
		self::run();
	}
	
}
?>