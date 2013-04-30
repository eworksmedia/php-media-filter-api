<?php
/**
 * TiltShift
 * 
 */
 
class TiltShift extends FilterBase {
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
		throw new Exception('TiltShift is a static class. No instances can be created.');
	}	
	
	/**
     * Method for filtering an image
     *
     */
	public static function filter($original_image, $preview_image) {
		list(self::$width, self::$height) = getimagesize($original_image);
		self::addCommand("convert " . $original_image . " -sigmoidal-contrast 15x30% " . $preview_image);
		self::addCommand("convert " . $preview_image . "  \( " . self::getFiltersColormapDirectory() . "tilt_shift.jpg -resize \"".self::$width."x".self::$height."\"! \) -compose Blur -set option:compose:args 10 -composite " . $preview_image);
		self::run();
	}
	
}
?>