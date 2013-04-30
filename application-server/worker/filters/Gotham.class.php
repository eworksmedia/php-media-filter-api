<?php
/**
 * Gotham
 * 
 */
 
class Gotham extends FilterBase {	
	/**
     * __construct
     *
     * @return void
     * @throws Exception
     */
	public function __construct() {
		throw new Exception('Gotham is a static class. No instances can be created.');
	}	
	
	/**
     * Method for filtering an image
     *
     */
	public static function filter($original_image, $preview_image) {
		self::addCommand("convert " . $original_image . " -modulate 120,10,100 -fill '#222b6d' -colorize 20 -gamma 0.5 -contrast -contrast " . $preview_image);
		self::run();
	}
	
}
?>