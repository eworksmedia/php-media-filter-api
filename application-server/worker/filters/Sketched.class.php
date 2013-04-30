<?php
/**
 * Sketched
 * 
 */
 
class Sketched extends FilterBase {	
	/**
     * __construct
     *
     * @return void
     * @throws Exception
     */
	public function __construct() {
		throw new Exception('Sketched is a static class. No instances can be created.');
	}	
	
	/**
     * Method for filtering an image
     *
     */
	public static function filter($original_image, $preview_image) {
		self::addCommand("convert " . $original_image . " -colorspace gray \( +clone -tile " . self::getFiltersColormapDirectory() . "pencil_tile.gif -draw \"color 0,0 reset\" +clone +swap -compose color_dodge -composite \) -fx 'u*.2+v*.8' " . $preview_image);
		self::run();
	}
	
}
?>