<?php
/**
 * Tint
 * 
 */
 
class Tint extends FilterBase {
	/**
     * @var number $red
     */
	private static $red;
	
	/**
     * @var number $green
     */
	private static $green;
	
	/**
     * @var number $blue
     */
	private static $blue;
	
	/**
     * __construct
     *
     * @return void
     * @throws Exception
     */
	public function __construct() {
		throw new Exception('Tint is a static class. No instances can be created.');
	}	
	
	/**
     * Method for filtering an image
     *
     */
	public static function filter($original_image, $preview_image, $options) {
		self::$red = $options['red'];
		self::$green = $options['green'];
		self::$blue = $options['blue'];
		self::addCommand(self::colortone($original_image, $preview_image, self::RGBtoHEX(self::$red, self::$green, self::$blue), 100, 1));
		self::run();
	}
	
	public static function RGBtoHEX($r, $g=-1, $b=-1) {
		if (is_array($r) && sizeof($r) == 3) list($r, $g, $b) = $r;
		$r = intval($r); $g = intval($g);
		$b = intval($b);
		$r = dechex($r<0?0:($r>255?255:$r));
		$g = dechex($g<0?0:($g>255?255:$g));
		$b = dechex($b<0?0:($b>255?255:$b));
		$color = (strlen($r) < 2?'0':'').$r;
		$color .= (strlen($g) < 2?'0':'').$g;
		$color .= (strlen($b) < 2?'0':'').$b;
		return '#'.$color;
	}
	
}
?>