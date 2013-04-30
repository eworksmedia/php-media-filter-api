<?php
/**
 * Kelvin
 * 
 */
 
class Kelvin extends FilterBase {	
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
		throw new Exception('Kelvin is a static class. No instances can be created.');
	}	
	
	/**
     * Method for filtering an image
     *
     */
	public static function filter($original_image, $preview_image) {
		list(self::$width, self::$height) = getimagesize($original_image);
    	self::addCommand("convert " . $original_image . " \( -auto-gamma -modulate 120,50,100 \) \( -size ".self::$width."x".self::$height." -fill \"rgba(255,153,0,0.5)\" -draw \"rectangle 0,0 ".self::$width.",".self::$height."\" \) -compose multiply " . $preview_image);
		self::addCommand("convert " . $preview_image . " \( " . self::getFiltersBorderDirectory() . "kelvin.png -resize \"".self::$width."x".self::$height."\"! -unsharp 1.5×1.0+1.5+0.02 \) -flatten " . $preview_image);
		self::run();
	}
	
}
?>