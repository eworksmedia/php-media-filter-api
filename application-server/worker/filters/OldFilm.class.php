<?php
/**
 * OldFilm
 * 
 */
 
class OldFilm extends FilterBase {
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
		throw new Exception('OldFilm is a static class. No instances can be created.');
	}	
	
	/**
     * Method for filtering an image
     *
     */
	public static function filter($original_image, $preview_image) {
		list(self::$width, self::$height) = getimagesize($original_image);
		self::addCommand(self::colortone($original_image, $preview_image, '#332900', 90, 0));
		self::addCommand("convert " . $preview_image . " -modulate 150,80,100 -gamma 1.0 -contrast -contrast " . $preview_image);
		self::addCommand("convert " . $preview_image . " \( " . self::getFiltersBorderDirectory() . "oldfilm.png -resize \"".self::$width."x".self::$height."\"! -unsharp 1.5×1.0+1.5+0.02 \) -flatten " . $preview_image);
		self::run();
	}
	
}
?>