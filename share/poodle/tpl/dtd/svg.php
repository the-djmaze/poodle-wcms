<?php

// NOTE: required attributes must come first
// http://www.w3.org/TR/html-markup/global-attributes.html
// http://www.w3.org/TR/html-markup/elements.html

$DTD = array_merge($DTD, array(

	'DOCTYPE'    => '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">',
	'EMPTY-TAGS' => array(),

	'XML'        => true,

	'STANDARD_ATTRIBUTES' => array('id'=>0, 'class'=>0, 'style'=>0, 'x'=>0, 'y'=>0, 'height'=>0, 'width'=>0,
		'fill'=>0, 'fill-opacity'=>0,
		'clip-path'=>0,'clip-rule'=>0,
		'stroke'=>0,'stroke-linecap'=>0,'stroke-linejoin'=>0,'stroke-miterlimit'=>0,'stroke-opacity'=>0,'stroke-width'=>0),

	'a' => array(),
	'altGlyph' => array(),
	'altGlyphDef' => array(),
	'altGlyphItem' => array(),
	'animate' => array(),
	'animateColor' => array(),
	'animateMotion' => array(),
	'animateTransform' => array(),
	'circle' => array('cx'=>0, 'cy'=>0, 'r'=>0),
	'clipPath' => array(),
	'color-profile' => array(),
	'cursor' => array(),
	'defs' => array(),
	'desc' => array(),
	'ellipse' => array(),
	'feBlend' => array(),
	'feColorMatrix' => array(),
	'feComponentTransfer' => array(),
	'feComposite' => array(),
	'feConvolveMatrix' => array(),
	'feDiffuseLighting' => array(),
	'feDisplacementMap' => array(),
	'feDistantLight' => array(),
	'feFlood' => array(),
	'feFuncA' => array(),
	'feFuncB' => array(),
	'feFuncG' => array(),
	'feFuncR' => array(),
	'feGaussianBlur' => array(),
	'feImage' => array(),
	'feMerge' => array(),
	'feMergeNode' => array(),
	'feMorphology' => array(),
	'feOffset' => array(),
	'fePointLight' => array(),
	'feSpecularLighting' => array(),
	'feSpotLight' => array(),
	'feTile' => array(),
	'feTurbulence' => array(),
	'filter' => array(),
	'font' => array(),
	'font-face' => array(),
	'font-face-format' => array(),
	'font-face-name' => array(),
	'font-face-src' => array(),
	'font-face-uri' => array(),
	'foreignObject' => array(),
	'g' => array('mask'=>0, 'opacity'=>0, 'transform'=>0),
	'glyph' => array(),
	'glyphRef' => array(),
	'hkern' => array(),
	'image' => array(),
	'line' => array(),
	'linearGradient' => array(),
	'marker' => array(),
	'mask' => array(),
	'metadata' => array(),
	'missing-glyph' => array(),
	'mpath' => array(),
	'path' => array('d'=>0),
	'pattern' => array(),
	'polygon' => array(),
	'polyline' => array(),
	'radialGradient' => array(),
	'rect' => array(),
	'script' => array(),
	'set' => array(),
	'stop' => array(),
	'style' => array(),
	'svg' => array('xmlns'=>'http://www.w3.org/2000/svg', 'version'=>'1.1', 'viewBox'=>0,
		'enable-background'=>0, 'preserveAspectRatio'=>0, 'style'=>0,
	),
	'switch' => array(),
	'symbol' => array(),
	'text' => array(),
	'textPath' => array(),
	'title' => array(),
	'tref' => array(),
	'tspan' => array(),
	'use' => array(),
	'view' => array(),
	'vkern' => array(),
));
