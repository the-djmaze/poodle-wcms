<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	See http://www.imagemagick.org/api/resize.php for nice info
*/

namespace Poodle\Image\Adapter;

class GD2
{
	const
		COLOR_BLACK   = 11,
		COLOR_BLUE    = 12,
		COLOR_CYAN    = 13,
		COLOR_GREEN   = 14,
		COLOR_RED     = 15,
		COLOR_YELLOW  = 16,
		COLOR_MAGENTA = 17,
		COLOR_OPACITY = 18,
		COLOR_ALPHA   = 19,
		COLOR_FUZZ    = 20,

		FILTER_UNDEFINED = 0,
		FILTER_POINT     = 1,
		FILTER_BOX       = 2,
		FILTER_TRIANGLE  = 3,
		FILTER_HERMITE   = 4,
		FILTER_HANNING   = 5,
		FILTER_HAMMING   = 6,
		FILTER_BLACKMAN  = 7,
		FILTER_GAUSSIAN  = 8,
		FILTER_QUADRATIC = 9,
		FILTER_CUBIC     = 10,
		FILTER_CATROM    = 11,
		FILTER_MITCHELL  = 12,
		FILTER_LANCZOS   = 13,
		FILTER_BESSEL    = 14,
		FILTER_SINC      = 15,

		CHANNEL_UNDEFINED = 0,
		CHANNEL_GRAY    = 1,
		CHANNEL_RED     = 1,
		CHANNEL_GREEN   = 2,
		CHANNEL_BLUE    = 4,
		CHANNEL_CYAN    = 1,
		CHANNEL_MAGENTA = 2,
		CHANNEL_YELLOW  = 4,
		CHANNEL_ALPHA   = 8,
		CHANNEL_OPACITY = 8,
		CHANNEL_MATTE   = 8,
		CHANNEL_BLACK   = 32,
		CHANNEL_INDEX   = 32,
		CHANNEL_ALL     = 255,

		COMPOSITE_DEFAULT     = 40,
		COMPOSITE_UNDEFINED   = 0,
		COMPOSITE_NO          = 1,
		COMPOSITE_ADD         = 2, // Deprecated
		COMPOSITE_ATOP        = 3, // Composites the inside of one layer with the other
		COMPOSITE_BLEND       = 4,
		COMPOSITE_BUMPMAP     = 5, // The same as COMPOSITE_MULTIPLY, except the source is converted to greyscale first.
		COMPOSITE_CLEAR       = 7,
		COMPOSITE_COLORBURN   = 8,
		COMPOSITE_COLORDODGE  = 9,
		COMPOSITE_COLORIZE    = 10,
		COMPOSITE_COPYBLACK   = 11,
		COMPOSITE_COPYBLUE    = 12,
		COMPOSITE_COPY        = 13, // Simply place the source on top of the destination.
		COMPOSITE_COPYCYAN    = 14,
		COMPOSITE_COPYGREEN   = 15,
		COMPOSITE_COPYMAGENTA = 16,
		COMPOSITE_COPYOPACITY = 17,
		COMPOSITE_COPYRED     = 18,
		COMPOSITE_COPYYELLOW  = 19,
		COMPOSITE_DARKEN      = 20,
		COMPOSITE_DSTATOP     = 21,
		COMPOSITE_DST         = 22,
		COMPOSITE_DSTIN       = 23,
		COMPOSITE_DSTOUT      = 24,
		COMPOSITE_DSTOVER     = 25,
		COMPOSITE_DIFFERENCE  = 26, // The difference in color values. Good for comparing images.
		COMPOSITE_DISPLACE    = 27,
		COMPOSITE_DISSOLVE    = 28,
		COMPOSITE_EXCLUSION   = 29,
		COMPOSITE_HARDLIGHT   = 30,
		COMPOSITE_HUE         = 31,
		COMPOSITE_IN          = 32, // Replaces the inside of one layer with another
		COMPOSITE_LIGHTEN     = 33,
		COMPOSITE_LUMINIZE    = 35,
		COMPOSITE_MINUS       = 36, // The source is subtracted to the destination and replaces the destination.
		COMPOSITE_MODULATE    = 37,
		COMPOSITE_MULTIPLY    = 38,
		COMPOSITE_OUT         = 39, // Replaces the outside of one layer with another
		COMPOSITE_OVER        = 40, // Overlay one image over the next
		COMPOSITE_OVERLAY     = 41,
		COMPOSITE_PLUS        = 42, // The source is added to the destination and replaces the destination.
		COMPOSITE_REPLACE     = 43,
		COMPOSITE_SATURATE    = 44,
		COMPOSITE_SCREEN      = 45,
		COMPOSITE_SOFTLIGHT   = 46,
		COMPOSITE_SRCATOP     = 47,
		COMPOSITE_SRC         = 48,
		COMPOSITE_SRCIN       = 49,
		COMPOSITE_SRCOUT      = 50,
		COMPOSITE_SRCOVER     = 51,
		COMPOSITE_SUBTRACT    = 52, // Deprecated
		COMPOSITE_THRESHOLD   = 53,
		COMPOSITE_XOR         = 54; // The part of the source that lies outside of the destination is combined with the part of the destination that lies outside the source.

	protected
		$img = null;

	private
		$error = null,
		$file,
		$format,
		$compression_q = 85,
		$type;

	function __construct($filename=null)
	{
		if (!extension_loaded('gd')) {
			throw new \Exception('GD image library not available');
		}
		\Poodle\PHP\INI::set('memory_limit', '64M');
		if (is_string($filename) && !$this->loadImage($filename)) {
			throw new \Exception($this->error);
		}
	}

	function __destruct()
	{
		$this->free();
	}

	function __toString()
	{
		return $this->getImageBlob();
	}

	public function free()
	{
		if ($this->img) {
			imagedestroy($this->img);
			$this->img = null;
		}
	}

	public function newPixelObject($color = null)
	{
		return new GD2Pixel($color);
	}

	private function setError($msg)
	{
		$this->error = $msg;
		return false;
	}

	private function store_image($filename) : bool
	{
		switch ($this->format)
		{
		case 'png':
		case 'png8':
		case 'png24':
		case 'png32':
			\imagesavealpha($this->img, true);
			return \imagepng($this->img, $filename, 9);

		case 'jpg':
		case 'jpeg':
			return \imagejpeg($this->img, $filename, $this->compression_q);

		case 'gif':
			return \imagegif($this->img, $filename);

		case 'webp':
			if (!\imageistruecolor($this->img)) {
				\imagepalettetotruecolor($this->img);
				if (-1 < \imagecolortransparent($this->img)) {
					\imagealphablending($this->img, true);
					\imagesavealpha($this->img, true);
				}
			}
			return \imagewebp($this->img, $filename);
		}
		return false;
	}

	public function newImage($cols, $rows, $background, $format='')
	{
		$this->img = $this->create_image($cols, $rows, true);
		if ('none' !== $background) {
//			imagefill($this->img, 0, 0, imagecolorallocate($this->img, 255, 0, 0));
		}
		if ($format) {
			$this->setImageFormat($format);
		}
	}

	private function create_image($width = -1, $height = -1, $trueColor = null)
	{
		if (-1 == $width) { $width = imagesx($this->img); }
		if (-1 == $height) { $height = imagesy($this->img); }
		if ($trueColor || ($this->img && imageistruecolor($this->img))) {
			$tmp_img = imagecreatetruecolor($width, $height);
			imagesavealpha($tmp_img, true);
			$trans_colour = imagecolorallocatealpha($tmp_img, 0, 0, 0, 127);
			imagefill($tmp_img, 0, 0, $trans_colour);
		} else {
			$tmp_img = imagecreate($width, $height);
			imagepalettecopy($tmp_img, $this->img);
			$t_clr_i = imagecolortransparent($this->img);
			if (-1 !== $t_clr_i) {
				imagecolortransparent($tmp_img, $t_clr_i);
				imagefill($tmp_img, 0, 0, $t_clr_i);
			}
		}
		return $tmp_img;
	}

	/**
	 * Imagick PECL similar methods
	 */

	public function clear()   { $this->free(); return true; }
	public function destroy() { $this->free(); return true; }

	public function compositeImage(GD2 $composite_object, $composite, $x, $y, $channel=255)
	{
		imagealphablending($this->img, $channel & 8);
		return imagecopy($this->img, $composite_object->img, $x, $y, 0, 0, $composite_object->getImageWidth(), $composite_object->getImageHeight());
	}

	public function cropImage($width, $height, $x, $y)
	{
		$x = min(imagesx($this->img), max(0, $x));
		$y = min(imagesy($this->img), max(0, $y));
		$width   = min($width,  imagesx($this->img) - $x);
		$height  = min($height, imagesy($this->img) - $y);
		$tmp_img = $this->create_image($width, $height);
		if (!imagecopy($tmp_img, $this->img, 0, 0, $x, $y, $width, $height)) {
			imagedestroy($tmp_img);
			throw new \Exception('Failed image transformation: crop()');
		}
		imagedestroy($this->img);
		$this->img = $tmp_img;
		return true;
	}

	public function cropThumbnailImage($width, $height)
	{
		$x = imagesx($this->img);
		$y = imagesy($this->img);
		$tx = $x/$width;
		$ty = $y/$height;
		if ($tx > $ty) {
			$x = round($x/$ty);
			$this->thumbnailImage($x, $height);
			$x = floor(($x-$width)/2);
			$y = 0;
		} else if ($tx < $ty) {
			$y = round($y/$tx);
			$this->thumbnailImage($width, $y);
			$x = 0;
			$y = floor(($y-$height)/2);
		} else {
			return $this->thumbnailImage($width, $height);
		}
		return $this->cropImage($width, $height, $x, $y);
	}

	public function flipImage()
	{
		return imageflip($this->img, IMG_FLIP_VERTICAL);
	}

	public function flopImage()
	{
		return imageflip($this->img, IMG_FLIP_HORIZONTAL);
	}

	public function gammaImage($gamma, $channel=0)
	{
		return ((int)$gamma < 1) ? imagegammacorrect($this->img, 1.0, $gamma) : true;
	}

	public function getImageBlob()
	{
		ob_start();
		if (!$this->store_image(null)) {
			ob_end_clean();
			throw new \Exception('Failed to generate image blob');
		}
		return ob_get_clean();
	}
	public function getImageFilename() { return $this->file; }
	public function getImageFormat()   { return $this->format; }
	public function getImageHeight()   { return imagesy($this->img); }
	public function getImageMimeType()
	{
		switch ($this->format)
		{
		case 'png':
		case 'png8':
		case 'png24':
		case 'png32':
			return 'image/png';
		case 'jpg':
		case 'jpeg':
			return 'image/jpeg';
		case 'gif':
			return 'image/gif';
		case 'webp':
			return 'image/webp';
		}
		return false;
	}
	public function getImageType()  { return $this->type; }
	public function getImageWidth() { return imagesx($this->img); }

	public function magnifyImage() { return $this->thumbnailImage(imagesx($this->img)*2, 0); }
	public function minifyImage()  { return $this->thumbnailImage(round(imagesx($this->img)/2), 0); }

	public function scaleImage($columns, $rows, $fit)
	{
		return $this->thumbnailImage($columns, $rows, $fit);
	}
	public function resizeImage($columns, $rows, $filter, $blur, $fit=false)
	{
		// imagescale()
		return $this->thumbnailImage($columns, $rows, $fit);
	}

	protected function loadImage($file)
	{
		if (!($imginfo = getimagesize($file))) {
			return $this->setError($file.' is not an image or not accessible');
		}
		switch ($imginfo[2])
		{
		case IMAGETYPE_GIF:
			$this->img = imagecreatefromgif($file);
			$this->format = 'gif';
			break;

		case IMAGETYPE_JPEG:
			$this->img = imagecreatefromjpeg($file);
			$this->format = 'jpeg';
			break;

		case IMAGETYPE_PNG:
			$this->img = imagecreatefrompng($file);
			$this->format = 'png';
			break;

		case IMAGETYPE_WEBP:
			$this->img = imagecreatefromwebp($file);
			$this->format = 'webp';
			break;

		default:
			return $this->setError('Unsupported fileformat: '.$imginfo['mime']);
		}
		if (!is_resource($this->img)) {
			return $this->setError('Failed to create image resource');
		}
		$this->file = $file;
		$this->type = (int)$imginfo[2];
/*
		imagick::IMGTYPE_UNDEFINED
		imagick::IMGTYPE_BILEVEL
		imagick::IMGTYPE_GRAYSCALE
		imagick::IMGTYPE_GRAYSCALEMATTE
		imagick::IMGTYPE_PALETTE
		imagick::IMGTYPE_PALETTEMATTE
		imagick::IMGTYPE_TRUECOLOR
		imagick::IMGTYPE_TRUECOLORMATTE
		imagick::IMGTYPE_COLORSEPARATION
		imagick::IMGTYPE_COLORSEPARATIONMATTE
		imagick::IMGTYPE_OPTIMIZE
*/
		return true;
	}

	public function rotate($degrees)
	{
		return $this->rotateImage(0, $degrees);
	}

	public function rotateImage($background, $degrees)
	{
		if (0 === ($degrees % 360)) { return true; }
		/** rotate clockwise */
		if (!function_exists('imagerotate')) { require(__DIR__.'/gd2/imagerotate.inc'); }
		$tmp_img = imagerotate($this->img, $degrees * -1, 0);
		if (!is_resource($tmp_img)) { return false; }
		imagedestroy($this->img);
		$this->img = $tmp_img;
		return true;
	}

	public function sampleImage($width, $height)
	{
		if (!$width && !$height) { return false; }
		if (0 > min($width, $height)) { return false; }
		$x = imagesx($this->img);
		$y = imagesy($this->img);
		if ($x != $width || $y != $height) {
			$tmp_img = $this->create_image($width, $height);
			if (!is_resource($tmp_img)) { return false; }
			if (!imagecopyresized($tmp_img, $this->img, 0, 0, 0, 0, $width, $height, $x, $y)) {
				imagedestroy($tmp_img);
				return false;
			}
			imagedestroy($this->img);
			$this->img = $tmp_img;
		}
		return true;
	}

	public function thumbnailImage($width, $height, $fit=false)
	{
		if (!$width && !$height) { return false; }
		if (0 > min($width, $height)) { return false; }
		$x = imagesx($this->img);
		$y = imagesy($this->img);
		$tx = $width  ? $x/$width : 0;
		$ty = $height ? $y/$height : 0;
		if (!$width  || ($fit && $tx < $ty)) { $width  = round($x / $ty); }
		if (!$height || ($fit && $tx > $ty)) { $height = round($y / $tx); }
		$tmp_img = $this->create_image($width, $height);
		if (!is_resource($tmp_img)) { return false; }
		imagealphablending($tmp_img, false);
		if (!imagecopyresampled($tmp_img, $this->img, 0, 0, 0, 0, $width, $height, $x, $y)) {
			if (!imagecopyresized($tmp_img, $this->img, 0, 0, 0, 0, $width, $height, $x, $y)) {
				imagedestroy($tmp_img);
				return false;
			}
		}
		imagedestroy($this->img);
		$this->img = $tmp_img;
		return true;
	}

	public function getImageCompressionQuality() { return $this->compression_q; }
	public function setImageCompressionQuality($q) { $this->compression_q = min(100,(int)$q); }
	public function setImageFilename($filename) { $this->file = $filename; }
	/** http://www.imagemagick.org/script/formats.php */
	public function setImageFormat($format)     { $this->format = strtolower($format); }

	public function valid() { is_resource($this->img); }

	public function writeImage(string $filename=null) : bool
	{
		if ($filename) {
			$this->setImageFilename($filename);
			$dir = \dirname($filename);
			if (!\is_dir($dir) && !\mkdir($dir, 0755, true)) {
				throw new \Exception("Failed to create directory {$dir}");
			}
		}
		return $this->store_image($this->getImageFilename());
	}

	public function getCopyright() { return 'GD'; }

	public function getPackageName() { return 'GD library'; }

	public function getReleaseDate() { return null; }

	public function getVersion()
	{
		return array(
			'versionNumber' => (int)GD_VERSION,
			'versionString' => GD_VERSION.(GD_BUNDLED?' (bundled)':'')
		);
	}

	public function queryFormats($pattern="*") { return array('GIF','JPEG','PNG'); }

	public function add_text($params)
	{
		$default_params = array(
			'text'  => 'Default text',
			'x'     => 10,
			'y'     => 20,
			'size'  => 12,
			'color' => '#000000',
			'font'  => dirname(__DIR__).'/fonts/default.ttf',
			'angle' => 0,
		);
		$params = array_merge($default_params, $params);
		if (preg_match('@^#([a-f0-9]{2})([a-f0-9]{2})([a-f0-9]{2})$@Di', $params['color'], $match)) {
			array_shift($match);
		} else {
			$match = array(0, 0, 0);
		}
		$params['angle'] = 360-$params['angle'];
		$c = imagecolorresolve($this->img, hexdec($match[0]), hexdec($match[1]), hexdec($match[2]));
		if ('.ttf' === substr($params['font'], -4)) {
			// TrueType font
			return imagettftext($this->img, $params['size']*0.8, $params['angle'], $params['x'], $params['y'], $c, $params['font'], $params['text']);
			// FreeType 2
//			return imagefttext($this->img, $params['size'], $params['angle'], $params['x'], $params['y'], $c, $params['font'], $params['text'], $extrainfo);
			// PostScript Type1 font
//			return imagepstext($this->img, $params['size'], $params['angle'], $params['x'], $params['y'], $c, $params['font'], $params['text']);
		}
		return imagestring($this->img, $params['size'], $params['x'], $params['y'], $params['text'], $c);
	}

	public function stripImage() { return $this; }
/*
	* gd_info — Retrieve information about the currently installed GD library
	* image_type_to_extension — Get file extension for image type
	* image_type_to_mime_type — Get Mime-Type for image-type returned by getimagesize, exif_read_data, exif_thumbnail, exif_imagetype
	* imageantialias — Should antialias functions be used or not
	* imagearc — Draws an arc
	* imagechar — Draw a character horizontally
	* imagecharup — Draw a character vertically
	* imagecolorallocate — Allocate a color for an image
	* imagecolorallocatealpha — Allocate a color for an image
	* imagecolorat — Get the index of the color of a pixel
	* imagecolorclosest — Get the index of the closest color to the specified color
	* imagecolorclosestalpha — Get the index of the closest color to the specified color + alpha
	* imagecolorclosesthwb — Get the index of the color which has the hue, white and blackness
	* imagecolordeallocate — De-allocate a color for an image
	* imagecolorexact — Get the index of the specified color
	* imagecolorexactalpha — Get the index of the specified color + alpha
	* imagecolormatch — Makes the colors of the palette version of an image more closely match the true color version
	* imagecolorresolvealpha — Get the index of the specified color + alpha or its closest possible alternative
	* imagecolorset — Set the color for the specified palette index
	* imagecolorsforindex — Get the colors for an index
	* imagecolorstotal — Find out the number of colors in an image's palette
	* imagecolortransparent — Define a color as transparent
	* imageconvolution — Apply a 3x3 convolution matrix, using coefficient and offset

	* imagecreatefromgd2 — Create a new image from GD2 file or URL
	* imagecreatefromgd2part — Create a new image from a given part of GD2 file or URL
	* imagecreatefromgd — Create a new image from GD file or URL
	* imagecreatefromstring — Create a new image from the image stream in the string
	* imagecreatefromwbmp — Create a new image from file or URL
	* imagecreatefromxbm — Create a new image from file or URL
	* imagecreatefromxpm — Create a new image from file or URL

	* imagedashedline — Draw a dashed line
	* imageellipse — Draw an ellipse
	* imagefill — Flood fill
	* imagefilledarc — Draw a partial ellipse and fill it
	* imagefilledellipse — Draw a filled ellipse
	* imagefilledpolygon — Draw a filled polygon
	* imagefilledrectangle — Draw a filled rectangle
	* imagefilltoborder — Flood fill to specific color
	* imagefilter — Applies a filter to an image
	* imagefontheight — Get font height
	* imagefontwidth — Get font width
	* imageftbbox — Give the bounding box of a text using fonts via freetype2
	* imagegammacorrect — Apply a gamma correction to a GD image

	* image2wbmp — Output image to browser or file
	* imagegd2 — Output GD2 image to browser or file
	* imagegd — Output GD image to browser or file
	* imagewbmp — Output image to browser or file
	* imagexbm — Output XBM image to browser or file

	* imagegrabscreen — Captures the whole M$ screen
	* imagegrabwindow — Captures a M$ window

	* imageinterlace — Enable or disable interlace
	* imagelayereffect — Set the alpha blending flag to use the bundled libgd layering effects
	* imageline — Draw a line
	* imageloadfont — Load a new font
	* imagepalettecopy — Copy the palette from one image to another
	* imagepolygon — Draws a polygon
	* imagepsbbox — Give the bounding box of a text rectangle using PostScript Type1 fonts
	* imagepsencodefont — Change the character encoding vector of a font
	* imagepsextendfont — Extend or condense a font
	* imagepsfreefont — Free memory used by a PostScript Type 1 font
	* imagepsloadfont — Load a PostScript Type 1 font from file
	* imagepsslantfont — Slant a font
	* imagerectangle — Draw a rectangle
	* imagesetbrush — Set the brush image for line drawing
	* imagesetpixel — Set a single pixel
	* imagesetstyle — Set the style for line drawing
	* imagesetthickness — Set the thickness for line drawing
	* imagesettile — Set the tile image for filling
	* imagestringup — Draw a string vertically
	* imagetruecolortopalette — Convert a true color image to a palette image
	* imagettfbbox — Give the bounding box of a text using TrueType fonts
	* imagetypes — Return the image types supported by this PHP build
	* iptcembed — Embed binary IPTC data into a JPEG image
	* iptcparse — Parse a binary IPTC block into single tags.
	* jpeg2wbmp — Convert JPEG image file to WBMP image file
	* png2wbmp — Convert PNG image file to WBMP image file
*/
}

class GD2Pixel
{
	protected $color;

	function __construct($color = '')
	{
		$this->setColor($color);
	}

	public function getColor()
	{
		return $this->color;
	}

	public function setColor($color)
	{
		$this->color = $color;
	}

}

/*
if (!class_exists('Imagick', false))
{
	class Imagick extends GD2 {}

	class ImagickDraw
	{
/*
	public function affine() {} // Adjusts the current affine transformation matrix
	public function annotation() {} // Draws text on the image
	public function arc() {} // Draws an arc
	public function bezier() {} // Draws a bezier curve
	public function circle() {} // Draws a circle
	public function clear() {} // Clears the ImagickDraw
	public function clone() {} // Makes an exact copy of the specified ImagickDraw object
	public function color() {} // Draws color on image
	public function comment() {} // Adds a comment
	public function composite() {} // Composites an image onto the current image
	public function __construct() {} // The ImagickDraw constructor
	public function destroy() {} // Frees all associated resources
	public function ellipse() {} // Draws an ellipse on the image
	public function getClipPath() {} // Obtains the current clipping path ID
	public function getClipRule() {} // Returns the current polygon fill rule
	public function getClipUnits() {} // Returns the interpretation of clip path units
	public function getFillColor() {} // Returns the fill color
	public function getFillOpacity() {} // Returns the opacity used when drawing
	public function getFillRule() {} // Returns the fill rule
	public function getFont() {} // Returns the font
	public function getFontFamily() {} // Returns the font family
	public function getFontSize() {} // Returns the font pointsize
	public function getFontStyle() {} // Returns the font style
	public function getFontWeight() {} // Returns the font weight
	public function getGravity() {} // Returns the text placement gravity
	public function getStrokeAntialias() {} // Returns the current stroke antialias setting
	public function getStrokeColor() {} // Returns the color used for stroking object outlines
	public function getStrokeDashArray() {} // Returns an array representing the pattern of dashes and gaps used to stroke paths
	public function getStrokeDashOffset() {} // Returns the offset into the dash pattern to start the dash
	public function getStrokeLineCap() {} // Returns the shape to be used at the end of open subpaths when they are stroked
	public function getStrokeLineJoin() {} // Returns the shape to be used at the corners of paths when they are stroked
	public function getStrokeMiterLimit() {} // Returns the stroke miter limit
	public function getStrokeOpacity() {} // Returns the opacity of stroked object outlines
	public function getStrokeWidth() {} // Returns the width of the stroke used to draw object outlines
	public function getTextAlignment() {} // Returns the text alignment
	public function getTextAntialias() {} // Returns the current text antialias setting
	public function getTextDecoration() {} // Returns the text decoration
	public function getTextEncoding() {} // Returns the code set used for text annotations
	public function getTextUnderColor() {} // Returns the text under color
	public function getVectorGraphics() {} // Returns a string containing vector graphics
	public function line() {} // Draws a line
	public function matte() {} // Paints on the image's opacity channel
	public function pathClose() {} // Adds a path element to the current path
	public function pathCurveToAbsolute() {} // Draws a cubic Bezier curve
	public function pathCurveToQuadraticBezierAbsolute() {} // Draws a quadratic Bezier curve
	public function pathCurveToQuadraticBezierRelative() {} // Draws a quadratic Bezier curve
	public function pathCurveToQuadraticBezierSmoothAbsolute() {} // Draws a quadratic Bezier curve
	public function pathCurveToQuadraticBezierSmoothRelative() {} // Draws a quadratic Bezier curve
	public function pathCurveToRelative() {} // Draws a cubic Bezier curve
	public function pathCurveToSmoothAbsolute() {} // Draws a cubic Bezier curve
	public function pathCurveToSmoothRelative() {} // Draws a cubic Bezier curve
	public function pathEllipticArcAbsolute() {} // Draws an elliptical arc
	public function pathEllipticArcRelative() {} // Draws an elliptical arc
	public function pathFinish() {} // Terminates the current path
	public function pathLineToAbsolute() {} // Draws a line path
	public function pathLineToHorizontalAbsolute() {} // Draws a horizontal line path
	public function pathLineToHorizontalRelative() {} // Draws a horizontal line
	public function pathLineToRelative() {} // Draws a line path
	public function pathLineToVerticalAbsolute() {} // Draws a vertical line
	public function pathLineToVerticalRelative() {} // Draws a vertical line path
	public function pathMoveToAbsolute() {} // Starts a new sub-path
	public function pathMoveToRelative() {} // Starts a new sub-path
	public function pathStart() {} // Declares the start of a path drawing list
	public function point() {} // Draws a point
	public function polygon() {} // Draws a polygon
	public function polyline() {} // Draws a polyline
	public function pop() {} // Destroys the current ImagickDraw in the stack, and returns to the previously pushed ImagickDraw
	public function popClipPath() {} // Terminates a clip path definition
	public function popDefs() {} // Terminates a definition list
	public function popPattern() {} // Terminates a pattern definition
	public function push() {} // Clones the current ImagickDraw and pushes it to the stack
	public function pushClipPath() {} // Starts a clip path definition
	public function pushDefs() {} // Indicates that following commands create named elements for early processing
	public function pushPattern() {} // Indicates that subsequent commands up to a ImagickDraw::opPattern() command comprise the definition of a named pattern
	public function rectangle() {} // Draws a rectangle
	public function render() {} // Renders all preceding drawing commands onto the image
	public function rotate() {} // Applies the specified rotation to the current coordinate space
	public function roundRectangle() {} // Draws a rounted rectangle
	public function scale() {} // Adjusts the scaling factor
	public function setClipPath() {} // Associates a named clipping path with the image
	public function setClipRule() {} // Set the polygon fill rule to be used by the clipping path
	public function setClipUnits() {} // Sets the interpretation of clip path units
	public function setFillAlpha() {} // Sets the opacity to use when drawing using the fill color or fill texture
	public function setFillColor() {} // Sets the fill color to be used for drawing filled objects
	public function setFillOpacity() {} // Sets the opacity to use when drawing using the fill color or fill texture
	public function setFillPatternURL() {} // Sets the URL to use as a fill pattern for filling objects
	public function setFillRule() {} // Sets the fill rule to use while drawing polygons
	public function setFont() {} // Sets the fully-specified font to use when annotating with text
	public function setFontFamily() {} // Sets the font family to use when annotating with text
	public function setFontSize() {} // Sets the font pointsize to use when annotating with text
	public function setFontStretch() {} // Sets the font stretch to use when annotating with text
	public function setFontStyle() {} // Sets the font style to use when annotating with text
	public function setFontWeight() {} // Sets the font weight
	public function setGravity() {} // Sets the text placement gravity
	public function setStrokeAlpha() {} // Specifies the opacity of stroked object outlines
	public function setStrokeAntialias() {} // Controls whether stroked outlines are antialiased
	public function setStrokeColor() {} // Sets the color used for stroking object outlines
	public function setStrokeDashArray() {} // Specifies the pattern of dashes and gaps used to stroke paths
	public function setStrokeDashOffset() {} // Specifies the offset into the dash pattern to start the dash
	public function setStrokeLineCap() {} // Specifies the shape to be used at the end of open subpaths when they are stroked
	public function setStrokeLineJoin() {} // Specifies the shape to be used at the corners of paths when they are stroked
	public function setStrokeMiterLimit() {} // Specifies the miter limit
	public function setStrokeOpacity() {} // Specifies the opacity of stroked object outlines
	public function setStrokePatternURL() {} // Sets the pattern used for stroking object outlines
	public function setStrokeWidth() {} // Sets the width of the stroke used to draw object outlines
	public function setTextAlignment() {} // Specifies a text alignment
	public function setTextAntialias() {} // Controls whether text is antialiased
	public function setTextDecoration() {} // Specifies a decoration
	public function setTextEncoding() {} // Specifies specifies the text code set
	public function setTextUnderColor() {} // Specifies the color of a background rectangle
	public function setVectorGraphics() {} // Sets the vector graphics
	public function setViewbox() {} // Sets the overall canvas size
	public function skewX() {} // Skews the current coordinate system in the horizontal direction
	public function skewY() {} // Skews the current coordinate system in the vertical direction
	public function translate() {} // Applies a translation to the current coordinate system
*//*
	}

	class ImagickPixel
	{
	function __construct($color=null){} // The ImagickPixel constructor
	public function clear(){} // Clears resources associated with this object
	public function destroy(){} // Deallocates resources associated with this object
	public function getColor($normalized=false){} // Returns the color
	public function getColorAsString(){} // Returns the color as a string
	public function getColorCount(){} // Returns the color count associated with this color
	public function getColorValue($color){} // Gets the normalized value of the provided color channel
	public function getHSL(){} // Returns the normalized HSL color of the ImagickPixel object
	public function isSimilar(){} // Check the distance between this color and another
	public function setColor($color){} // Sets the color
	public function setColorValue($color, $value){} // Sets the normalized value of one of the channels
	public function setHSL($hue, $saturation, $luminosity){} // Sets the normalized HSL color
	}
}
*/
