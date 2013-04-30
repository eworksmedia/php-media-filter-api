<?php
/**
 * Painted
 * 
 */
 
class Painted extends FilterBase {	
	/**
     * __construct
     *
     * @return void
     * @throws Exception
     */
	public function __construct() {
		throw new Exception('Painted is a static class. No instances can be created.');
	}	
	
	/**
     * Method for filtering an image
     *
     */
	public static function filter($original_image, $preview_image) {
    	self::addCommand("convert " . $original_image . " -normalize -posterize 8 -despeckle -despeckle -despeckle -blur 1 -paint 2 " . $preview_image);
		self::addCommand("convert " . $preview_image . " -paint 2 " . $preview_image);
		self::run();
	}
	
}
?>