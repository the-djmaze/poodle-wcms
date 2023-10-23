<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.
*/

namespace Poodle\Image\Adapter;

if (!class_exists('Imagick',false)) { return; }

class IMagick extends \Imagick
{
	function __construct($file=null)
	{
		parent::__construct($file);
		// Strip meta data
		if ($file) { parent::stripImage(); }
	}

	function __destruct()
	{
		$this->clear();
	}

	public function free()
	{
		$this->clear();
	}

	public function newPixelObject($color = null)
	{
		return new \ImagickPixel($color);
	}

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
		$params['color']= strtolower($params['color']);
		$draw  = new \ImagickDraw();
		$pixel = new \ImagickPixel($params['color']);
		$draw->setfillcolor($pixel);
		$draw->setfontsize($params['size']);
		$draw->setfont($params['font']);
		return $this->annotateimage($draw, $params['x'], $params['y'], $params['angle'], $params['text']);
	}

	public function readImage($file)
	{
		throw new \BadMethodCallException('readImage() not supported');
	}

	public function writeImage(string $filename=null) : bool
	{
		if ($filename) {
			$dir = \dirname($filename);
			if (!\is_dir($dir) && !\mkdir($dir, 0777, true)) {
				throw new \Exception("Failed to create directory {$dir}");
			}
		}
		return parent::writeImage($filename);
	}

	public function rotate($degrees)
	{
		return $this->rotateImage(new \ImagickPixel(), $degrees);
	}

	public function getImageFormat()
	{
		return strtolower(parent::getImageFormat());
	}

/*
bool adaptiveBlurImage  ( float $radius  , float $sigma  [, int $channel  ] )
bool adaptiveResizeImage ( int $columns , int $rows [, bool $fit ] )
bool adaptiveSharpenImage ( float $radius , float $sigma [, int $channel ] )
bool adaptiveThresholdImage ( int $width , int $height , int $offset )
bool addImage ( Imagick $source )
bool addNoiseImage ( int $noise_type [, int $channel ] )
bool affineTransformImage ( ImagickDraw $matrix )
Imagick appendImages ( bool $stack )
Imagick averageImages ( void )
bool blackThresholdImage ( mixed $threshold )
bool blurImage ( float $radius , float $sigma [, int $channel ] )
bool borderImage ( mixed $bordercolor , int $width , int $height )
bool charcoalImage ( float $radius , float $sigma )
bool chopImage ( int $width , int $height , int $x , int $y )
bool clipImage ( void )
bool clipPathImage ( string $pathname , bool $inside )
Imagick clone ( void )
bool clutImage ( Imagick $lookup_table [, int $channel ] )
Imagick coalesceImages ( void )
bool colorFloodfillImage ( mixed $fill , float $fuzz , mixed $bordercolor , int $x , int $y )
bool colorizeImage ( mixed $colorize , mixed $opacity )
Imagick combineImages ( int $channelType )
bool commentImage ( string $comment )
Imagick compareImageChannels ( Imagick $image , int $channelType , int $metricType )
Imagick compareImageLayers ( int $method )
array compareImages ( Imagick $compare , int $metric )
Imagick __construct ([ mixed $files ] )
bool contrastImage ( bool $sharpen )
bool contrastStretchImage ( float $black_point , float $white_point [, int $channel ] )
bool convolveImage ( array $kernel [, int $channel ] )
Imagick current ( void )
bool cycleColormapImage ( int $displace )
bool deconstructImages ( void )
bool despeckleImage ( void )
bool displayImage ( string $servername )
bool displayImages ( string $servername )
bool distortImage ( int $method , array $arguments , bool $bestfit )
bool drawImage ( ImagickDraw $draw )
bool edgeImage ( float $radius )
bool embossImage ( float $radius , float $sigma )
bool enhanceImage ( void )
bool equalizeImage ( void )
bool evaluateImage ( int $op , float $constant [, int $channel ] )
Imagick flattenImages ( void )
bool frameImage ( mixed $matte_color , int $width , int $height , int $inner_bevel , int $outer_bevel )
Imagick fxImage ( string $expression [, int $channel ] )
bool gaussianBlurImage ( float $radius , float $sigma [, int $channel ] )
int getCompression ( void )
int getCompressionQuality ( void )
string getCopyright ( void )
string getFilename ( void )
string getFormat ( void )
string getHomeURL ( void )
Imagick getImage ( void )
ImagickPixel getImageBackgroundColor ( void )
ImagickPixel getImageBluePrimary ( float $x , float $y )
ImagickPixel getImageBorderColor ( void )
int getImageChannelDepth ( int $channelType )
float getImageChannelDistortion ( Imagick $reference , int $channel , int $metric )
array getImageChannelExtrema ( int $channel )
array getImageChannelMean ( int $channel )
array getImageChannelStatistics ( void )
ImagickPixel getImageColormapColor ( int $index )
int getImageColors ( void )
int getImageColorspace ( void )
int getImageCompose ( void )
int getImageDelay ( void )
int getImageDepth ( void )
int getImageDispose ( void )
float getImageDistortion ( MagickWand $reference , int $metric )
array getImageExtrema ( void )
float getImageGamma ( void )
array getImageGeometry ( void )
array getImageGreenPrimary ( void )
array getImageHistogram ( void )
int getImageIndex ( void )
int getImageInterlaceScheme ( void )
int getImageInterpolateMethod ( void )
int getImageIterations ( void )
int getImageLength ( void )
string getImageMagickLicense ( void )
int getImageMatte ( void )
ImagickPixel getImageMatteColor ( void )
int getImageOrientation ( void )
array getImagePage ( void )
ImagickPixel getImagePixelColor ( int $x , int $y )
string getImageProfile ( string $name )
array getImageProfiles ([ string $pattern [, bool $only_names ]] )
array getImageProperties ([ string $pattern [, bool $only_names ]] )
string getImageProperty ( string $name )
array getImageRedPrimary ( void )
Imagick getImageRegion ( int $width , int $height , int $x , int $y )
int getImageRenderingIntent ( void )
array getImageResolution ( void )
int getImageScene ( void )
string getImageSignature ( void )
int getImageSize ( void )
int getImageTicksPerSecond ( void )
float getImageTotalInkDensity ( void )
int getImageUnits ( void )
int getImageVirtualPixelMethod ( void )
array getImageWhitePoint ( void )
int getInterlaceScheme ( void )
int getIteratorIndex ( void )
int getNumberImages ( void )
string getOption ( string $key )
string getPackageName ( void )
array getPage ( void )
ImagickPixelIterator getPixelIterator ( void )
ImagickPixelIterator getPixelRegionIterator ( int $x , int $y , int $columns , int $rows )
array getQuantumDepth ( void )
array getQuantumRange ( void )
string getReleaseDate ( void )
int getResource ( int $type )
int getResourceLimit ( int $type )
array getSamplingFactors ( void )
array getSize ( void )
int getSizeOffset ( void )
array getVersion ( void )
bool hasNextImage ( void )
bool hasPreviousImage ( void )
array identifyImage ([ bool $appendRawOutput ] )
bool implodeImage ( float $radius )
bool labelImage ( string $label )
bool levelImage ( float $blackPoint , float $gamma , float $whitePoint [, int $channel ] )
bool linearStretchImage ( float $blackPoint , float $whitePoint )
bool mapImage ( Imagick $map , bool $dither )
bool matteFloodfillImage ( float $alpha , float $fuzz , mixed $bordercolor , int $x , int $y )
bool medianFilterImage ( float $radius )
bool modulateImage ( float $brightness , float $saturation , float $hue )
Imagick montageImage ( ImagickDraw $draw , string $tile_geometry , string $thumbnail_geometry , int $mode , string $frame )
Imagick morphImages ( int $number_frames )
Imagick mosaicImages ( void )
bool motionBlurImage ( float $radius , float $sigma , float $angle )
bool negateImage ( bool $gray [, int $channel ] )
bool newImage ( int $cols , int $rows , mixed $background [, string $format ] )
bool newPseudoImage ( int $columns , int $rows , string $pseudoString )
bool nextImage ( void )
bool normalizeImage ([ int $channel ] )
bool oilPaintImage ( float $radius )
bool optimizeImageLayers ( void )
bool paintFloodfillImage ( mixed $fill , float $fuzz , mixed $bordercolor , int $x , int $y )
bool paintOpaqueImage ( mixed $target , mixed $fill , float $fuzz [, int $channel ] )
bool paintTransparentImage ( mixed $target , float $alpha , float $fuzz )
bool pingImage ( string $filename )
bool pingImageBlob ( string $image )
bool pingImageFile ( resource $filehandle [, string $fileName ] )
bool polaroidImage ( ImagickDraw $properties , float $angle )
bool posterizeImage ( int $levels , bool $dither )
bool previewImages ( int $preview )
bool previousImage ( void )
bool profileImage ( string $name , string $profile )
bool quantizeImage ( int $numberColors , int $colorspace , int $treedepth , bool $dither , bool $measureError )
bool quantizeImages ( int $numberColors , int $colorspace , int $treedepth , bool $dither , bool $measureError )
array queryFontMetrics ( ImagickDraw $properties , string $text [, bool $multiline ] )
array queryFonts ([ string $pattern ] )
array queryFormats ([ string $pattern ] )
bool radialBlurImage ( float $angle [, int $channel ] )
bool raiseImage ( int $width , int $height , int $x , int $y , bool $raise )
bool randomThresholdImage ( float $low , float $high [, int $channel ] )
bool readImageBlob ( string $image [, string $filename ] )
bool readImageFile ( resource $filehandle [, string $fileName ] )
bool reduceNoiseImage ( float $radius )
bool removeImage ( void )
string removeImageProfile ( string $name )
bool render ( void )
bool rollImage ( int $x , int $y )
bool roundCorners ( float $x_rounding , float $y_rounding [, float $stroke_width [, float $displace [, float $size_correction ]]] )
bool separateImageChannel ( int $channel )
bool sepiaToneImage ( float $threshold )
bool setBackgroundColor ( mixed $background )
bool setCompression ( int $compression )
bool setCompressionQuality ( int $quality )
bool setFilename ( string $filename )
bool setFirstIterator ( void )
bool setFormat ( string $format )
bool setImage ( Imagick $replace )
bool setImageBackgroundColor ( mixed $background )
bool setImageBias ( float $bias )
bool setImageBluePrimary ( float $x , float $y )
bool setImageBorderColor ( mixed $border )
bool setImageChannelDepth ( int $channel , int $depth )
bool setImageColormapColor ( int $index , ImagickPixel $color )
bool setImageColorspace ( int $colorspace )
bool setImageCompose ( int $compose )
bool setImageCompression ( int $compression )
bool setImageDelay ( int $delay )
bool setImageDepth ( int $depth )
bool setImageDispose ( int $dispose )
bool setImageExtent ( int $columns , int $rows )
bool setImageGamma ( float $gamma )
bool setImageGreenPrimary ( float $x , float $y )
bool setImageIndex ( int $index )
bool setImageInterlaceScheme ( int $interlace_scheme )
bool setImageInterpolateMethod ( int $method )
bool setImageIterations ( int $iterations )
bool setImageMatte ( bool $matte )
bool setImageMatteColor ( mixed $matte )
bool setImageOpacity ( float $opacity )
bool setImageOrientation ( int $orientation )
bool setImagePage ( int $width , int $height , int $x , int $y )
bool setImageProfile ( string $name , string $profile )
bool setImageProperty ( string $name , string $value )
bool setImageRedPrimary ( float $x , float $y )
bool setImageRenderingIntent ( int $rendering_intent )
bool setImageResolution ( float $x_resolution , float $y_resolution )
bool setImageScene ( int $scene )
bool setImageTicksPerSecond ( int $ticks_per-second )
bool setImageType ( int $image_type )
bool setImageUnits ( int $units )
bool setImageVirtualPixelMethod ( int $method )
bool setImageWhitePoint ( float $x , float $y )
bool setInterlaceScheme ( int $interlace_scheme )
bool setIteratorIndex ( int $index )
bool setLastIterator ( void )
bool setOption ( string $key , string $value )
bool setPage ( int $width , int $height , int $x , int $y )
bool setResolution ( float $x_resolution , float $y_resolution )
bool setResourceLimit ( int $type , int $limit )
bool setSamplingFactors ( array $factors )
bool setSize ( int $columns , int $rows )
bool setSizeOffset ( int $columns , int $rows , int $offset )
bool setType ( int $image_type )
bool shadeImage ( bool $gray , float $azimuth , float $elevation )
bool shadowImage ( float $opacity , float $sigma , int $x , int $y )
bool sharpenImage ( float $radius , float $sigma [, int $channel ] )
bool shaveImage ( int $columns , int $rows )
bool shearImage ( mixed $background , float $x_shear , float $y_shear )
bool sigmoidalContrastImage ( bool $sharpen , float $alpha , float $beta [, int $channel ] )
bool sketchImage ( float $radius , float $sigma , float $angle )
bool solarizeImage ( int $threshold )
bool spliceImage ( int $width , int $height , int $x , int $y )
bool spreadImage ( float $radius )
Imagick steganoImage ( Imagick $watermark_wand , int $offset )
bool stereoImage ( Imagick $offset_wand )
bool stripImage ( void )
bool swirlImage ( float $degrees )
bool textureImage ( Imagick $texture_wand )
bool thresholdImage ( float $threshold [, int $channel ] )
bool tintImage ( mixed $tint , mixed $opacity )
Imagick transformImage ( string $crop , string $geometry )
bool transverseImage ( void )
bool trimImage ( float $fuzz )
bool uniqueImageColors ( void )
bool unsharpMaskImage ( float $radius , float $sigma , float $amount , float $threshold [, int $channel ] )
bool valid ( void )
bool vignetteImage ( float $blackPoint , float $whitePoint , int $x , int $y )
bool waveImage ( float $amplitude , float $length )
bool whiteThresholdImage ( mixed $threshold )
bool writeImages ( string $filename , bool $adjoin )
*/
}
