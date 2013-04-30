<?php
/**
 * Saturation
 * 
 */
 
class Saturation extends FilterBase {
	/**
     * @var number $saturation
     */
	private static $saturation;
		
	/**
     * __construct
     *
     * @return void
     * @throws Exception
     */
	public function __construct() {
		throw new Exception('Saturation is a static class. No instances can be created.');
	}	
	
	/**
     * Method for filtering an image
     *
     */
	public static function filter($original_image, $preview_image, $options) {
		self::$saturation = $options['saturation'];
		$saturation_new = 100;
		$saturation_new = $saturation_new + self::$saturation;
		self::addCommand("convert " . $original_image . " -set option:modulate:colorspace hsb -modulate 100," . $saturation_new . " " . $preview_image);
		self::run();
	}
	
}
?>