/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	@import "poodle"

	http://msdn.microsoft.com/en-us/library/ms533049%28v=vs.85%29.aspx
	* RemoveFormat
*/

if (PoodleWYSIWYG) {

	PoodleWYSIWYG.DOMDefaultAttributes = ['title','class','id'/*,'dir','lang','xml:lang'*/];
	PoodleWYSIWYG.DOMElements = {
		a          : {attr:['href','rel','hreflang'/*,'accesskey','charset','coords','name','rev','shape','tabindex','target','type'*/]},
		abbr       : {},
		address    : {},
		area       : {/*attr:['alt','accesskey','coords','href','nohref','shape','tabindex','target']*/},
		article    : {}, // HTML5
		aside      : {}, // HTML5
		audio      : {/*attr:['autoplay','controls','loop','preload','src']*/}, // HTML5
		b          : {},
	//	base       : {/*attr:['href','target']*/},
		bdo        : {},
		blockquote : {/*attr:['cite']*/},
	//	body       : {},
		br         : {/*attr:['clear']*/},
		button     : {attr:['name','type'/*'accesskey','disabled','tabindex','value'*/]},
		canvas     : {}, // HTML5
		caption    : {},
		cite       : {},
		code       : {},
		col        : {/*attr:['char','charoff','span','valign','width']*/},
		datalist   : {}, // HTML5
		dd         : {},
		del        : {/*attr:['cite','datetime']*/},
		details    : {/*attr:['open']*/}, // HTML5
		dfn        : {},
		div        : {},
		dl         : {},
		dt         : {},
		em         : {},
		embed      : {/*attr:['src','type']*/}, // HTML5
		fieldset   : {},
		figcaption : {}, // HTML5
		figure     : {}, // HTML5
		footer     : {}, // HTML5
		form       : {attr:['action'/*,'accept-charset','enctype','method','name'*/]},
		h1         : {},
		h2         : {},
		h3         : {},
		h4         : {},
		h5         : {},
		h6         : {},
	//	head       : {/*attr:['profile']*/},
		header     : {}, // HTML5
		hgroup     : {}, // HTML5
		hr         : {},
	//	html       : {},
		i          : {},
		iframe     : {},
		img        : {attr:['alt','src'/*,'height','ismap','longdesc','usemap','width'*/]},
		input      : {attr:['name','type'/*'accept','accesskey','alt','checked','disabled','maxlength','readonly','size','src','tabindex','value',
			'autocomplete','autofocus','formaction','formenctype','formmethod','formnovalidate','formtarget','list','max','min','multiple','pattern','placeholder','required','step'*/]},
		ins        : {/*attr:['cite','datetime']*/},
		keygen     : {/*attr:['autofocus','challenge','disabled','form','keytype','name']*/}, // HTML5
		kbd        : {},
		label      : {/*attr:['accesskey','for']*/},
		legend     : {/*attr:['accesskey']*/},
		li         : {/*attr:['type','value']*/},
	//	link       : {/*attr:['disabled','charset','href','hreflang','media','rel','rev','target','type']*/},
		main       : {}, // HTML5
		map        : {/*attr:['name']*/},
		mark       : {}, // HTML5
		menu       : {}, // HTML5 redefined
	//	meta       : {/*attr:['content','http-equiv','name','scheme']*/},
		meter      : {/*attr:['high','low','max','min','optimum','value']*/}, // HTML5
		nav        : {}, // HTML5
	//	noscript   : {},
		object     : {/*attr:['archive','border','classid','codebase','codetype','data','declare','height','hspace','name','standby','tabindex','type','usemap','vspace','width']*/},
		ol         : {},
		optgroup   : {/*attr:['label','disabled']*/},
		option     : {/*attr:['disabled','label','selected','value']*/},
		output     : {/*attr:['for','form','name']*/}, // HTML5
		p          : {},
		param      : {/*attr:['name','type','value','valuetype']*/},
		pre        : {},
		progress   : {/*attr:['max','value']*/}, // HTML5
		q          : {/*attr:['cite']*/},
		rp         : {}, // HTML5 Ruby
		rt         : {}, // HTML5 Ruby
		ruby       : {}, // HTML5 Ruby
		samp       : {},
	//	script     : {/*attr:['type'=>'text/javascript','charset','defer','src']*/},
		section    : {/*attr:['cite']*/}, // HTML5
		select     : {attr:['name'/*'disabled','multiple','size','tabindex'*/]},
		small      : {},
		source     : {/*attr:['media','src','type']*/}, // HTML5
		span       : {},
		strong     : {},
	//	style      : {/*attr:['type'=>'text/css','media']*/},
		sub        : {},
		summary    : {}, // HTML5
		sup        : {},
		table      : {/*attr:['border','cellpadding','cellspacing','frame','rules','summary','width']*/},
		tbody      : {/*attr:['char','charoff','valign']*/},
		td         : {/*attr:['abbr','axis','char','charoff','colspan','headers','rowspan','scope','valign']*/},
		textarea   : {attr:['name'/*'cols','rows','accesskey','disabled','readonly','tabindex'*/]},
		tfoot      : {/*attr:['char','charoff','valign']*/},
		th         : {},
		thead      : {/*attr:['char','charoff','valign']*/},
		time       : {/*attr:['datetime']*/}, // HTML5
		title      : {},
		tr         : {/*attr:['char','charoff','valign']*/},
		ul         : {},
		"var"      : {},
		video      : {/*attr:['autoplay','controls','loop','preload','src']*/} // HTML5
	};

}
