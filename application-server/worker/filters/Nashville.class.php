<?php
/**
 * Nashville
 * 
 */
 
class Nashville extends FilterBase {
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
		throw new Exception('Nashville is a static class. No instances can be created.');
	}	
	
	/**
     * Method for filtering an image
     *
     */
	public static function filter($original_image, $preview_image) {
		list(self::$width, self::$height) = getimagesize($original_image);
		self::addCommand(self::colortone($original_image, $preview_image, '#222b6d', 100, 0));
    	self::addCommand(self::colortone($preview_image, $preview_image, '#f7daae', 100, 1));
    	self::addCommand("convert " . $preview_image . " -contrast -modulate 100,150,100 -auto-gamma " . $preview_image);
		self::addCommand("convert " . $preview_image . " \( " . self::getFiltersBorderDirectory() . "nashville.png -resize \"".self::$width."x".self::$height."\"! -unsharp 1.5×1.0+1.5+0.02 \) -flatten " . $preview_image);
		self::run();
	}
	
}
?>