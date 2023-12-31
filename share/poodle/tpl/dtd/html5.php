<?php

// NOTE: required attributes must come first
// http://www.w3.org/TR/html-markup/global-attributes.html
// http://www.w3.org/TR/html-markup/elements.html

$DTD = array(
//	'CDATA'      => array('<!--', '//-->'),
	'DOCTYPE'    => '<!DOCTYPE html>',
	'EMPTY-TAGS' => array('area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'),
//	'INLINE-TAGS'=> array('a','abbr','b','bdo','br','button','cite','code','del','dfn','em','i','iframe','img','input','ins','kbd','label','legend','map','noscript','object','param','q','rp','rt','ruby','samp','script','select','small','span','strong','sub','sup','textarea','var'),
	'XML'        => false,
	'CASE-INSENSITIVE' => true,
	'STANDARD_ATTRIBUTES' => array('class'=>0, 'dir'=>0, 'id'=>0, 'lang'=>0, 'style'=>0, 'title'=>0, 'lang'=>0
		,'contenteditable'=>0, 'contextmenu'=>0, 'draggable'=>0, 'dropzone'=>0, 'hidden'=>0, 'spellcheck'=>0, 'translate'=>0, 'accesskey'=>0, 'tabindex'=>0
		// HTML Living Standard
		,'autocapitalize'=>0, 'inputmode'=>0, 'is'=>0, 'slot'=>0
		// RDFa Lite 1.1
		,'prefix'=>0, 'property'=>0, 'resource'=>0, 'typeof'=>0, 'vocab'=>0
		// RDFa 1.1, extends Lite
		//,'about'=>0, 'content'=>0, 'datatype'=>0, 'href'=>0, 'inlist'=>0, 'rel'=>0, 'rev'=>0, 'src'=>0
		// Microdata
		,'itemscope'=>0,'itemtype'=>0,'itemprop'=>0,'itemref'=>0,'itemid'=>0
		// ARIA
		,'role'=>0
	),

	'a'          => array('charset'=>0, 'coords'=>0, 'href'=>0, 'hreflang'=>0, 'name'=>0, 'rel'=>0, 'rev'=>0, 'shape'=>0, 'target'=>0, 'type'=>0, 'download'=>0),
	'abbr'       => array(),
	'address'    => array(),
	'area'       => array('alt'=>'', 'coords'=>0, 'href'=>0, 'nohref'=>0, 'shape'=>0, 'target'=>0),
	'article'    => array(),
	'aside'      => array(),
	'audio'      => array('autoplay'=>0, 'controls'=>0, 'loop'=>0, 'preload'=>0, 'src'=>0),
	'b'          => array(),
	'base'       => array('href'=>0, 'target'=>0),
	'bdi'        => array(),
	'bdo'        => array(),
	'blockquote' => array('cite'=>0),
	'body'       => array(),
	'br'         => array('clear'=>0),
	// formaction, formenctype, formmethod & formnovalidate only when type=submit
	'button'     => array('disabled'=>0, 'name'=>0, 'type'=>0, 'value'=>0,'form'=>0,'formaction'=>0,'formenctype'=>0,'formmethod'=>0,'formnovalidate'=>0, 'formtarget'=>0),
	'canvas'     => array('height'=>0,'width'=>0),
	'caption'    => array(),
	'cite'       => array(),
	'code'       => array(),
	'col'        => array('span'=>0),
	'colgroup'   => array('span'=>0),
	'data'       => array('value'=>0),
	'datalist'   => array(),
	'dd'         => array(),
	'del'        => array('cite'=>0, 'datetime'=>0),
	'details'    => array('open'=>0),
	'dfn'        => array(),
	'dialog'     => array('open'=>0),
	'div'        => array(),
	'dl'         => array(),
	'dt'         => array(),
	'em'         => array(),
	'embed'      => array('src'=>0, 'type'=>0),
	'fieldset'   => array(),
	'figcaption' => array(),
	'figure'     => array(),
	'footer'     => array(),
	'form'       => array('action'=>'', 'accept-charset'=>0 /*\Poodle::CHARSET*/, 'autocomplete'=>0, 'enctype'=>0, 'method'=>0, 'name'=>0, 'novalidate'=>0, 'target'=>0),
	'h1'         => array(),
	'h2'         => array(),
	'h3'         => array(),
	'h4'         => array(),
	'h5'         => array(),
	'h6'         => array(),
	'head'       => array('profile'=>0),
	'header'     => array(),
	'hgroup'     => array(),
	'hr'         => array(),
	'html'       => array(),
	'i'          => array(),
	'iframe'     => array('allowfullscreen'=>0, 'height'=>0, 'sandbox'=>0, 'seamless'=>0, 'src'=>0, 'srcdoc'=>0, 'width'=>0),
	'img'        => array('alt'=>'', 'src'=>'', 'srcset'=>0, 'sizes'=>0, 'height'=>0, 'ismap'=>0, 'longdesc'=>0, 'usemap'=>0, 'width'=>0, 'crossorigin'=>0, 'loading'=>0),
	// formaction, formenctype, formmethod & formtarget only when type=submit|image
	'input'      => array('accept'=>0, 'alt'=>0, 'checked'=>0, 'disabled'=>0, 'maxlength'=>0, 'minlength'=>0, 'name'=>0, 'readonly'=>0, 'size'=>0, 'src'=>0, 'type'=>0, 'value'=>0,
		'autocomplete'=>0, 'autofocus'=>0, 'form'=>0, 'formnovalidate'=>0, 'list'=>0, 'max'=>0, 'min'=>0, 'multiple'=>0, 'pattern'=>0, 'placeholder'=>0, 'required'=>0, 'step'=>0),
	'ins'        => array('cite'=>0, 'datetime'=>0),
	'kbd'        => array(),
	'label'      => array('for'=>0),
	'legend'     => array(),
	'li'         => array('type'=>0, 'value'=>0),
	'link'       => array('href'=>0, 'hreflang'=>0, 'media'=>0, 'rel'=>0, 'rev'=>0, 'sizes'=>0, 'type'=>0, 'as'=>0),
	'main'       => array(),
	'map'        => array('name'=>''),
	'mark'       => array(),
	'menu'       => array(), # redefined
	'meta'       => array('content'=>'', 'http-equiv'=>0, 'name'=>0, 'scheme'=>0),
	'meter'      => array('high'=>0, 'low'=>0, 'max'=>0, 'min'=>0, 'optimum'=>0, 'value'=>0),
	'nav'        => array(),
	'noscript'   => array(),
	'object'     => array('archive'=>0, 'border'=>0, 'classid'=>0, 'codebase'=>0, 'codetype'=>0, 'data'=>0, 'declare'=>0, 'height'=>0, 'hspace'=>0, 'name'=>0, 'standby'=>0, 'type'=>0, 'usemap'=>0, 'vspace'=>0, 'width'=>0),
	'ol'         => array('type'=>0),
	'optgroup'   => array('label'=>'', 'disabled'=>0),
	'option'     => array('disabled'=>0, 'label'=>0, 'selected'=>0, 'value'=>0),
	'output'     => array('for'=>0, 'form'=>0, 'name'=>0),
	'p'          => array(),
	'param'      => array('name'=>'', 'type'=>0, 'value'=>0, 'valuetype'=>0),
	'picture'    => array(),
	'pre'        => array(),
	'progress'   => array('max'=>0, 'value'=>0),
	'q'          => array('cite'=>0),
	'rb'         => array(), # Ruby
	'rp'         => array(), # Ruby
	'rt'         => array(), # Ruby
	'rtc'        => array(), # Ruby
	'ruby'       => array(), # Ruby
	's'          => array(),
	'samp'       => array(),
	'script'     => array('type'=>0, 'charset'=>0, 'async'=>0, 'defer'=>0, 'src'=>0),
	'section'    => array('cite'=>0),
	'select'     => array('disabled'=>0, 'multiple'=>0, 'name'=>0, 'required'=>0, 'size'=>0),
	'slot'       => array('name'=>0),
	'small'      => array(),
	'source'     => array('media'=>0, 'src'=>0, 'srcset'=>0, 'sizes'=>0, 'type'=>0),
	'span'       => array(),
	'strong'     => array(),
	'style'      => array('type'=>'text/css', 'media'=>0),
	'sub'        => array(),
	'summary'    => array(),
	'sup'        => array(),
	'table'      => array('border'=>0),
	'tbody'      => array(),
	'td'         => array('colspan'=>0, 'headers'=>0, 'rowspan'=>0),
	'template'   => array(),
	'textarea'   => array('cols'=>0 /*20*/, 'rows'=>0 /*2*/, 'disabled'=>0, 'maxlength'=>0, 'name'=>0, 'placeholder'=>0, 'readonly'=>0, 'wrap'=>0, 'required'=>0),
	'tfoot'      => array(),
	'th'         => array('colspan'=>0, 'headers'=>0, 'rowspan'=>0, 'scope'=>0),
	'thead'      => array(),
	'time'       => array('datetime'=>0),
	'title'      => array(),
	'tr'         => array('char'=>0, 'charoff'=>0, 'valign'=>0),
	'track'      => array('kind'=>0, 'src'=>0, 'srclang'=>0, 'label'=>0, 'default'=>0),
	'u'          => array(),
	'ul'         => array(),
	'var'        => array(),
	'video'      => array('autoplay'=>0, 'controls'=>0, 'loop'=>0, 'preload'=>0, 'src'=>0, 'poster'=>0),
	'wbr'        => array(),
);
