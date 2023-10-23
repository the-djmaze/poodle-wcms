/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	http://yura.thinkweb2.com/named-function-expressions/
	http://peter.michaux.ca/articles/an-important-pair-of-parens
*/

/**
 * PHP Alike
 */

//ENT_HTML_QUOTE_NONE   = 0;
//ENT_HTML_QUOTE_SINGLE = 1;
//ENT_HTML_QUOTE_DOUBLE = 2;
const ENT_NOQUOTES = 0,
	ENT_HTML401  = 1,
	ENT_COMPAT   = 2,
	ENT_QUOTES   = 3, // ENT_COMPAT | ENT_HTML401

	STR_PAD_LEFT  = 0,
	STR_PAD_RIGHT = 1,
	STR_PAD_BOTH  = 2,

	DATE_ISO8601 = "Y-m-d\\TH:i:sO",
	DATE_RFC822  = "D, d M y H:i:s O",
	DATE_RFC850  = "l, d-M-y H:i:s T",
	DATE_RFC1036 = DATE_RFC822,
	DATE_RFC1123 = "D, d M Y H:i:s O",
	DATE_RFC2822 = DATE_RFC1123,
	DATE_RFC3339 = "Y-m-d\\TH:i:sP",
	DATE_ATOM    = DATE_RFC3339,
//	DATE_COOKIE  = DATE_RFC850,
	DATE_RSS     = DATE_RFC1123,
	DATE_W3C     = DATE_RFC3339,

defined = v => undefined !== v,
is_array = v => v instanceof Array || Array.isArray(v),
is_bool = v => typeof v === 'boolean',
is_function = v => typeof v === 'function',
is_number = v => typeof v === 'number' && isFinite(v),
is_numeric = v => isFinite(v) && !isNaN(parseFloat(v)),
is_object = v => typeof v === 'object',
is_string = v => typeof v === 'string',
is_scalar = v => is_string(v) || is_number(v) || is_bool(v),
/*
is_int   = v => is_number(v) && !(v % 1),
is_float = v => is_number(v) && !!(v % 1),
is_null  = v => null === v,
*/

htmlspecialchars = ((de,se,gt,lt,sq,dq) => {
	return (str, quote_style, double_encode) => {
		str = (''+str)
			.replace((!defined(double_encode)||double_encode)?de:se,'&amp;')
			.replace(gt,'&lt;')
			.replace(lt,'&gt;');
		if (!is_number(quote_style)) { quote_style = 2; }
		if (quote_style & 1) { str = str.replace(sq,'&#039;'); }
		return (quote_style & 2) ? str.replace(dq,'&quot;') : str;
	};
})(/&/g,/&(?![\w#]+;)/gi,/</g,/>/g,/'/g,/"/g),

strip_tags = (m => {
	return (str) => str.replace(m, '');
})(/<\s*\/?\s*(\w+|!)[^>]*>/gi),

floatval = (v, def) => {
	v = parseFloat(v);
	return isFinite(v)?v:defined(def)?def:0.0;
},

intval = (v, d) => Math.round(floatval(v, d)),

sprintf = (r => {
	return function sprintf(f) {
		if (!is_string(f)) throw "sprintf: The first arguments need to be a valid format string.";
		var idx=0, a=arguments, n;
		/**
		 * The callback function arguments:
		 *      m = found substring
		 *      i = index specifier (\d+\$ or \d+#)
		 *      s = alignment specifier ("+" or "-" or empty)
		 *      p = padding specifier (space, 0 or defined as '.)
		 *      w = width specifier (\d*)
		 *      d = floating-point precision specifier (\.\d*)
		 *      t = type specifier ([bcdefgosuxX])
		 */
		return f.replace(r, function (m, i, s, p, w, d, t) {
			if ('%%'==m) return '%';
			i = 0<i ? i : ++idx;
			if (i >= a.length) throw "sprintf: At least one argument was missing.";
			d = intval(d);
			n = Number(a[i]);
			switch (t) {
			case 'b': return n.toString(2);
			case 'c': return String.fromCharCode(n);
			case 'd':
			case 'u': return n.toString(10);
			case 'e': return n.toExponential(d);
			case 'f': return n.toFixed(d);
			case 'g': return n.toPrecision(d);
			case 'o': return n.toString(8);
			case 's': return String(a[i]).pad(intval(w), ((p && p.length) ? p[p.length-1] : ''), '-'==s?1:0);
			case 'x': return n.toString(16).toLowerCase();
			case 'X': return n.toString(16).toUpperCase();
			}
			return '';
		});
	};
})(/%%|%(?:(\d+)[$#])?([+-])?('.|0| )?(\d*)(?:\.(\d+))?([bcdefgosuxX])/g);

/**
 * Initialize Poodle DOM
 */

(()=>{

const version = 3.0,
lc = v => v.toLowerCase();

var doc = document,
max = Math.max,
dateType = /(date(?:time(?:-local)?)?|month|time|week)/,
nav = navigator,
ua  = {},
win = window,
DCL = 'DOMContentLoaded',
DFCL = 'DOMFrameContentLoaded',
cssPrefix = '',
strContains_re = [],
dragNode,
tt=[1,1],
dom_events = {
	// Poodle
	afterreset:[],
	/**
	 * http://en.wikipedia.org/wiki/DOM_events
	 * http://www.quirksmode.org/dom/events/
	 * http://www.w3.org/TR/DOM-Level-3-Events/
	 * http://www.w3schools.com/tags/ref_eventattributes.asp
	 * event: [Bubbles, Cancelable]
	 */
	// Mouse
	click:     tt,
	dblclick:  tt,
	mousedown: tt,
	mouseenter:[0,1],
	mouseleave:[0,1],
	mouseup:   tt,
	mouseover: tt,
	mousemove: [1],
	mouseout:  tt,
	mousewheel:tt,
	// Mouse HTML 5
	contextmenu:tt,
	drag:       tt,
	dragend:    tt,
	dragenter:  tt,
	dragleave:  tt,
	dragover:   tt,
	dragstart:  tt,
	drop:       tt,
	selectstart:tt, /** css -moz-user-select:none */
	wheel:      tt,
	// Keyboard
	keydown:   tt,
	keypress:  tt,
	keyup:     tt,
	// Text Events
	textInput: tt,
	// HTML frame/object
	load:      [],
	unload:    [],
	abort:     [1],
	error:     [1],
	resize:    [1],
	scroll:    [1],
	beforeunload:[1],
	// HTML form
	select:    [1],
	change:    [1],
	submit:    tt,
	reset:     [1],
	formchange:[],
	forminput: [], /** deprecated, use 'input' event */
	input:     [],
	invalid:   [],
	// User interface
	DOMActivate: tt, /** DOMActivate, Gecko oncommand is similar */
	focus:     [],
	focusin:   [1],
	focusout:  [1],
	blur:      [],
	// Mutation
	DOMSubtreeModified:          [1],
	DOMNodeInserted:             [1],
	DOMNodeRemoved:              [1],
	DOMNodeRemovedFromDocument:  [],
	DOMNodeInsertedIntoDocument: [],
	DOMAttrModified:             [1],
	DOMCharacterDataModified:    [1],
	// Firefox, Opera 9+ & Safari 3.1+
	DOMContentLoaded:      [],
	DOMFrameContentLoaded: [],
	// Mutation Name
	DOMAttributeNameChanged: [],
	DOMElementNameChanged:   [],
	/** OLD whatwg Web Forms, but very usefull for our custom HTML5Form controls */
	DOMControlValueChanged:  [1], // whatwg.org/specs/web-forms/current-work/#the-domcontrolvaluechanged
	/** HTML 5 */
	hashchange: tt,
	message:    [0,1],
	orientationchange: tt, // screen rotated, screen.orientation
	// Touch events http://backtothecode.blogspot.nl/2009/10/javascript-touch-and-gesture-events.html
	touchstart:  tt, // like mouseDown
	touchmove:   tt, // like mouseMove
	touchend:    tt, // like mouseUp.
	touchcancel: tt,
	touchleave:  tt,
	//touchenter:  tt, ?????
	// Gesture events
	gesturestart:  tt,
	gesturechange: tt,
	gestureend:    tt,
	// media
	canplay:        tt,
	canplaythrough: tt,
	durationchange: tt,
	emptied:        tt,
	ended:          tt,
	loadeddata:     tt,
	loadedmetadata: tt,
	loadstart:      tt,
	pause:          tt,
	play:           tt,
	playing:        tt,
	progress:       tt,
	ratechange:     tt,
	readystatechange: tt,
	seeked:       tt,
	seeking:      tt,
	stalled:      tt,
	suspend:      tt,
	timeupdate:   tt,
	volumechange: tt,
	waiting:      tt
};

/**
 * Browser Detection
 * Reference: howtocreate.co.uk/tutorials/jsexamples/sniffer.html
 */

if (win.opera) { ua.opera=win.opera.version?floatval(win.opera.version()):true; cssPrefix = '-o-'; }
else {
	var a = nav.userAgent, m = a.match(/(KHTML|WebKit|Gecko)\/([\d.]+)/i);
	if (m) {
		ua[lc(m[1])] = floatval(m[2]);
		cssPrefix = '-' + (ua.gecko ? 'moz' : lc(m[1])) + '-';
	}
}
ua[lc((nav.platform.match(/Mac|Win|Linux|BSD|SunOS/i) || ['other'])[0])] = true;

/**
 * Event Handling
 */

function startDrag(n)
{
	if (!dragNode && n && 1 === n.nodeType) {
		dragNode = n;
		setTimeout(() => n.addClass('dragging'), 1);
	}
}
function endDrag()
{
	if (dragNode) {
		dragNode.removeClass('dragging');
		dragNode = null;
	}
}

function FastClick(el, fn) {
	this.element = el;
	this.handler = fn;
	el.on('touchstart', this);
	// touchstart not always triggered on tap,
	// for example on a Vodafone Smart 3 you have to tap 1 to 5 times
	// So instead, we trigger click
	el.addEventListener('click', this, false);
}
FastClick.prototype = {
	handleEvent: function(e) {
		switch (e.type)
		{
		case 'touchstart':
			e.stopPropagation();
			FastClick.touch = true;
			this.element.on('touchmove touchend touchcancel touchleave', this);
			this.startX = e.touches[0].clientX;
			this.startY = e.touches[0].clientY;
			break;

		case 'touchmove':
			if (Math.abs(e.touches[0].clientX - this.startX) > 10 ||
				Math.abs(e.touches[0].clientY - this.startY) > 10)
			{
				this.reset();
			}
			break;

		case 'touchcancel':
		case 'touchleave':
			this.reset();
			break;

		case 'click':
			if (!FastClick.touch) { this.handler.call(this.element, e); }
			FastClick.touch = false;
			break;

		case 'touchend':
			e.stopPropagation();
			this.reset();
			this.handler.call(this.element, e);
			break;
		}
	},

	reset: function() {
		this.element.off('touchmove touchend touchcancel touchleave', this);
		//setTimeout(function(){FastClick.touch = false;}, 500);
	}
};

function bindEvent(type, fn, capture)
{
	if (this.addEventListener) {
		var types = type.split(/\s+/), i=0;
		while (type = types[i++]) {
			var t = lc(type);
			if (lc(DCL) == t) { this.$W().Poodle.onDOMReady(fn); }
			else {
				if ('beforesubmit' == t) { this.addEventBeforeSubmit(fn); return this; }
				if ('submit' == t) { this.addEventBeforeSubmit(); }
				/**
				 * Extend HTML5 DnD with feature to know which Node is being dragged
				 */
				if ('dragstart' == t) {
					var n=this;
					if (!n._bm_ds && 1 === n.nodeType) {
						n._bm_ds = 1;
						n.on(t, ()=>startDrag(n));
						n.on('dragend', endDrag);
					}
				}
				// 'ontouchstart' in document.documentElement
				if ('click' == t && 'ontouchstart' in this.$D().documentElement) {
					// Prevent 300ms delay on touch devices
					new FastClick(this, fn);
/*
				} else if (0 === t.indexOf('domnode'))) {
					MutationObserver
*/
				} else {
					this.addEventListener(type, fn, !!capture);
//					this.addEventListener(type, fn, {passive: true}); // touch
				}
				if (!dom_events[type]) { console.log("Custom Event type: "+type); }
			}
		}
	}
	return this;
}

function unbindEvent(type, fn, capture)
{
	if (this.removeEventListener) {
		var types = type.split(/\s+/), i=0;
		while (type = types[i++]) {
			this.removeEventListener(type, fn, !!capture);
		}
	}
	return this;
}

function simulateEvent(type, props, detail, bubbles, cancelable)
{
	if (this.dispatchEvent) {
		var params = {
			bubbles:    dom_events[type] ? dom_events[type][0] : (3 > arguments.length || bubbles),
			cancelable: dom_events[type] ? dom_events[type][1] : (4 > arguments.length || cancelable),
			detail: detail
		};
		this.dispatchEvent(extendDOM(new CustomEvent(type, params), props));
	}
	return this;
}

/**
 * Helper functions
 */

function hasOwnProp(obj, prop)
{
	return Object.prototype.hasOwnProperty.call(obj, prop);
}

function extendDOM(obj, elements)
{
	if (obj && is_object(elements)) {
		var k, o, c;
		for (k in elements) try {
			if (!(k in obj || hasOwnProp(obj, k) || is_numeric(k))) {
				o = elements[k];
				if (o && is_object(o) && ('value' in o || 'get' in o || 'set' in o)) {
					c = obj.constructor;
					console.info('defineProperty: '+ (c ? c.name + '.' : '') + k);
					Object.defineProperty(obj, k, o);
				} else {
					obj[k] = o;
				}
			}
		} catch (e) {
			console.error("defineProperty "+o+"."+k+" ("+(typeof k)+"): "+e.message);
		}
	}
	return obj;
}

/** Object extending. extend() is used by Selection object */
function extendNode(target, obj)
{
	var o=target, k, v;
	if (obj) {
		if (!is_object(obj)) { obj = new obj; }
		// copy properties
		for (k in obj) {
			v=obj[k];
			// Skip prototype additions
			if (v != Object.prototype[k]) {
				try { switch (k) {
					case 'class':
					case 'className':  o.setClass(v); break;
					case 'for':
					case 'htmlFor':    o.attr('for', v); break;
					case 'cssText':    o.attr('style', v); break;
					case 'innerHTML':  o.html(v); break;
					case 'textContent':o.txt(v); break;
					case 'style':      extendObj(o.style, v); break;
					default:
						if (0 === k.indexOf('on') && dom_events[k.substr(2)] && is_function(v) && o.on) {
							o.on(k.substr(2), v);
						} else {
							// !hasOwnProp(obj, k)
							if (is_scalar(v)) o.attr(k,v);
							o[k] = v;
						}
				} } catch (e) {
					// read-only
				}
			}
		}
	}
	return o;
}

function extendObj(target, obj, ignoreProto)
{
	if (obj) {
		if (!target) target = {};
		if (!is_object(obj)) { obj = new obj; }
		var k, v;
		for (k in obj) {
			v=obj[k];
			// Skip prototype additions?
			if (ignoreProto || v !== Object.prototype[k]) {
				try {
					target[k] = is_object(v) ? extendObj(target[k], v, ignoreProto) : v;
				} catch (e) {
					// read-only
				}
			}
		}
	}
	return target;
}

var CSS = [];
function createCSS(rule, media, newStyle)
{
	var m = is_string(media) ? media : 'screen';
	if (newStyle || !CSS[m]) { CSS[m] = doc.$H().$A('style',{type:'text/css',media:m}).sheet; }
	if (CSS[m]) { CSS[m].insertRule(rule, CSS[m].cssRules.length); }
}

function cssrule(r)
{
	return r=='float'?'cssFloat':r.replace(/-(\w)/g, (d,m) => m.toUpperCase());
}

/**
 * W3C DOM
 */

function $B()    { return this.$D().$B(); }
function $T(name){ return this.getElementsByTagName(name); }

/** Start Selectors API
 * http://www.w3.org/TR/selectors-api/
 * http://www.w3.org/TR/CSS2/selector.html
 * http://www.w3.org/TR/css3-selectors/
 * Custom:
 *      [class!=made_up] is equal to :not([class=made_up])
 *      :contains(Selectors)
 */
const re_css_not = /([a-z])(\[[a-z_:][-a-z0-9_:.]+)!(=('[^']*'|"[^"]*"|[^"'[\]]+)\])/i,
	re_css_contains = /^(.+?):contains\(["']?(.*)["']?\)(.*)$/;
function $Q(expr,one)
{
	try {
		return (one ? this.querySelector(expr) : this.querySelectorAll(expr));
		// Also convert NodeList to Array
//		return (one ? this.querySelector(expr) : Array.from(this.querySelectorAll(expr)));
	} catch(e) {
		var not = expr.includes('!=');
		expr = expr.replace(re_css_not, '$1:not($2$3)');
		if (expr.includes(':contains')) {
			expr = expr.split(',');
			var i = 0, nodes=[], l = expr.length,
			search = (parent, expression) => {
				var m = re_css_contains.exec(expression.trim());
				if (!m) {
					nodes = nodes.concat(Array.from(parent.querySelectorAll(expression)));
				}

				if (' ' === m[1].slice(-1)) {
					m[1] += '*';
				}

				parent.querySelectorAll(m[1]).forEach(n => {
					if (n.textContent.includes(m[2])) {
						if (m[3]) {
							search(n, m[3]);
						} else {
							nodes.push(n);
						}
					}
				});
			};

			for (; i<l; ++i) {
				search(this, expr[i]);
				if (one && nodes[0]) return nodes[0];
			}
			return one ? null
				: (2 > l
					? nodes
					: nodes.filter((node, index) => index == nodes.indexOf(node)));
		}
		if (not) {
			return (one ? this.querySelector(expr) : this.querySelectorAll(expr));
		}
		throw e;
	}
}
/** End Selectors API */

/** https://developer.mozilla.org/en/XPath, could define getElementsByXPath() */
function $X(expression, altfn)
{
	if (!this.$D().evaluate) { return is_function(altfn)?altfn.call(this):false; }
	var n, result = [],
		q = this.$D().evaluate(expression, this, null, XPathResult.ORDERED_NODE_ITERATOR_TYPE, null);
	while (n = q.iterateNext()) result.push(n);
	return result;
}

function contentDoc() { try { return this.contentDocument || this.contentWindow.document; }catch(e){} }
function contentWin() { try { return this.contentWindow || DOM.Document.$W.call(this.$Doc()); }catch(e){} }

function getInputMinMax(o, p)
{
	var v = o.attr(p), t = o.attr('type');
	if ('number'===t) { return floatval(v, ('min'==p)?-Infinity:Infinity); }
	if ('range' ===t) { return floatval(v, ('min'==p)?0:100); }
	if (t.match(dateType)) {
		var f = o.getDateFormat();
		return (v && f) ? v.toDate(f).format(f) : '';
	}
	return null;
}

function setInputMinMax(o, p, v)
{
	if ('' !== v) {
		var t = o.attr('type');
		if ('number'===t) { v = floatval(v, ('min'==p)?-Infinity:Infinity); }
		if ('range' ===t) { v = floatval(v, ('min'==p)?0:100); }
		if (t.match(dateType)) {
			var f = o.getDateFormat();
			v = f ? v.toDate(f) : null;
			v = v ? v.format(f) : v;
		}
	}
	o.attr(p, v);
}

function arrCallFunctions(a, e, o)
{
	a.forEach(f => {
		try {
			if (is_function(f)) { f.call(o, e); }
		} catch (er) {
			console.error(er);
			console.error((e ? '\n'+e.target.nodeName+'.on'+e.type+'=' : " in ")+f);
		}
	});
}

// HTMLDialogElement alternative
function showDialog(n, m)
{
	if ('dialog' == lc(n.nodeName)) {
		var d = Poodle.$C('div',{'class':'dialog'+(m?' modal':'')});
		n.replaceWith(d);
		d.appendChild(n);
		n.attr('open','').open = true;
		if (m) {
			n.$W().onkeydown = function(e) {
				if (27 === e.keyCode) {
					n.close();
				}
			};
		}
		return n;
	}
	n.hidden = false;
	return n.attr('hidden', null)[m?'addClass':'removeClass']('modal');
}

function L10N()
{
	this.merge = a => {
		Object.getOwnPropertyNames(a).forEach(p => {
			if (p == 'merge' || p == 'get' || p == 'nget')
				throw "Not allowed to set "+p;
			Poodle.L10N[p] = a[p];
		});
	};
	this.get = function(txt) {
		if (txt) {
			if (this[txt]) {
				return this[txt];
			}
			console.warn('Translation missing for: '+txt);
		}
		return txt;
	};
	this.nget = function(txt, n) {
		txt = this.get(txt);
		if (!is_string(txt)) {
			txt = txt.msgs[txt.plural ? txt.plural(n) : (1 == n ? 0 : 1)];
		}
		return sprintf(txt, n);
	};
}

/**
 * Ajax
 * http://www.w3.org/TR/XMLHttpRequest/
 */
var XHR_requests = 0;

// Serialize an object/array into a query string
function toQueryString(o, prefix)
{
	return is_string(o) ? o : (new URLSearchParams(toFormData(o, prefix))).toString();
}

function toFormData(o, prefix, fd)
{
	if (is_string(o) || o instanceof FormData) { return o; }
	fd = fd || new FormData();
	function add(v, k) {
		if (is_object(v)) {
			toFormData(v, k, fd);
		} else {
			fd.append(k, v);
		}
	}
	if (prefix && is_array(o)) {
		o.forEach((v,i) => add(v, prefix+"["+i+"]"));
	} else if (is_object(o)) {
		for (var p in o) {
			if (hasOwnProp(o, p)) {
				add(o[p], prefix ? prefix+"["+p+"]" : p);
			}
		}
	}
	return fd;
}

function XHR()
{
	/** Privileged */
	this.async = true;
	this.onabort = null;
	this.onerror = null;
	this.oncomplete = null;
	this.onprogress = null;
	this.onresponseline = null;
	this.afteropen = null;
	this.form = null;

	this.sendForm = (form, btn) => {
		let doc = form.$D(),
		    uri = form.action || doc.location.href,
		 method = form.method.toUpperCase(),
		enctype = form.enctype,
		   data = new FormData(form);
		btn = btn || doc.activeElement;
		if (btn && btn.form === form && -1 < ['submit','button','image'].indexOf(btn.type)) {
			if (btn.name) {
				data.append(btn.name, btn.value);
			}
			uri = btn.attr("formaction") || uri;
			method = (btn.attr("formmethod") || method).toUpperCase();
			enctype = (btn.attr("formenctype") || enctype);
		}
		this.form = form;
		if ('POST' === method || 'PUT' === method) {
			if ('multipart/form-data' !== enctype) {
				data = (new URLSearchParams(data)).toString();
			}
		} else {
			uri = uri.replace(/\\?.*/, '') + '?' + (new URLSearchParams(data)).toString();
			data = null;
		}
		return request(method, uri, data);
	};

	this.get = (uri, query) => {
		if (!uri) uri = doc.location.href;
		if (query) uri += (uri.includes('?')?'&':'?') + toQueryString(query);
		return request('GET', uri);
	};

	this.post = (uri, data) => request('POST', uri, toFormData(data));

	this.put = (uri, data) => request('PUT', uri, data);

	this['delete'] = (uri, query) => {
		if (!uri) uri = doc.location.href;
		if (query) uri += (uri.includes('?')?'&':'?') + toQueryString(query);
		return request('DELETE', uri);
	};

	this.abort = function() {
		var r = this.xhr;
		if (r && r.readyState && r.abort) {
			r.abort();
		}
	};

	this.fromJSON = function() {
		return ("json"==this.type ? JSON.parse(this.xhr.responseText) : null);
	};

	var PXHR = this,
		xhr = new XMLHttpRequest(),
		bytesUploaded,
		bytesTotal,
		updateTime,
		elapsedTime,
		startTime,

		pollTimer,
		prevDataLength,
		lastPos;

	/** Public */
	/**
	 * XHR level 2
	 * http://www.w3.org/TR/XMLHttpRequest2/
	 */
	this.on = function(type,fn){
		if ("progress" === type || "abort" === type || "error" === type || "complete" === type || "responseline" === type) {
			this["on"+type] = fn;
		} else {
			bindEvent.call(xhr, type, fn);
		}
		return this;
	};
	this.off = function(type,fn){unbindEvent.call(xhr, type, fn); return this;};
//	this.on("load",  uploadComplete);
//	this.on("error", uploadFailed);
//	this.on("abort", uploadCanceled);
	this.xhr = xhr;
	this.getTransferStats = function()
	{
		var bps = bytesUploaded * 1000 / elapsedTime,
		bytesRemaining = bytesTotal - bytesUploaded;
		return {
			bps:              bps,
			speed:            bps.bytesToSpeed(0),
			remainingBytes:   bytesRemaining,
			remainingSize:    bytesRemaining.bytesToSize(),
			remainingTime:    max(0,Math.floor(bytesRemaining/bps - (new Date().getTime()-updateTime)/1000)).secondsToTime(),
			transferredBytes: bytesUploaded,
			transferredSize:  bytesUploaded.bytesToSize(0),
			percentComplete:  Math.min(100,bytesUploaded * 100 / bytesTotal)
		};
	};
	if (xhr.upload) {
		xhr.upload.addEventListener("progress", function(e){
			if (e.lengthComputable) {
				bytesUploaded = intval(e.loaded);
				bytesTotal    = intval(e.total);
				updateTime    = new Date().getTime();
				elapsedTime   = updateTime - startTime;
				onProgress(PXHR);
			}
		}, false);
	}

	/** Private */
	function stopTimer()
	{
		if (pollTimer) {
			clearInterval(pollTimer);
			pollTimer = null;
		}
	}

	function decPXHR() { if (--XHR_requests<1) document.$B().removeClass('xhr-loading'); }

	function doFinish(obj, fn)
	{
		stopTimer();
		if (is_function(fn)) { try { fn(obj); }catch(e){} }
		decPXHR();
	}

	function onProgress(PXHR)
	{
		if (is_function(PXHR.onprogress)) try {
			PXHR.onprogress.call(PXHR, PXHR.getTransferStats());
		} catch(e) {}
	}

	function onComplete()
	{
		if (this.form) { this.form.removeClass('xhr'); }
		this.type = this.xhr.getResponseHeader('content-type').replace(/^.*\/([a-z]+).*$/, '$1');
		bytesUploaded = bytesTotal;
		onProgress(this);
		doFinish(this, this.oncomplete);
	}

	function handleStream()
	{
		var xhr = PXHR.xhr;
		if (xhr.readyState != 4 && xhr.readyState != 3) {
			return;
		}
		if (4 == xhr.readyState) {
			stopTimer();
		}
		if (xhr.status != 200) {
			return;
		}
		// In konqueror xhr.responseText is sometimes null here...
		if (xhr.responseText === null) {
			return;
		}
		while (prevDataLength != xhr.responseText.length) {
			prevDataLength = xhr.responseText.length;
			var response = xhr.responseText.substring(lastPos),
				lines = response.split('\n'),
				i = 0;
			lastPos += response.lastIndexOf('\n') + 1;
			if (xhr.readyState == 3 && response[response.length-1] != '\n') {
				lines.pop();
			}
			for (; i < lines.length; ++i) {
				if (lines[i].length) {
					PXHR.onresponseline.call(PXHR, lines[i]);
				}
			}
		}
		if (4 == xhr.readyState) {
			stopTimer();
		}
	}

	function request(method, uri, data)
	{
		if (!PXHR.xhr) { return false; }
		PXHR.abort();
		if (PXHR.form) { PXHR.form.addClass('xhr'); }
		if (++XHR_requests<2) { document.$B().addClass('xhr-loading'); }

		bytesUploaded = bytesTotal = elapsedTime = 0;
		startTime = updateTime = new Date().getTime();

		var r = PXHR.xhr;
		r.open(method, uri || doc.location.href, !!PXHR.async);
		if (PXHR.async) {
			r.onload = onComplete.bind(PXHR);
		}
		r.onabort = () => doFinish(PXHR, PXHR.onabort);
		r.onerror = () => doFinish(PXHR, PXHR.onerror);
		if (is_function(PXHR.afteropen)) try {
			/**
			 * Old spec says withCredentials can only be set after "open"
			 * Android browser requires this
			r.withCredentials = true;
			 */
			PXHR.afteropen.call(PXHR);
		} catch(e) {}
		r.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		// http://www.iana.org/assignments/media-types/
		r.setRequestHeader('Accept', 'application/xml,text/xml,text/html,application/xhtml+xml,application/javascript,application/json,text/plain,*/*');
		if (data && is_string(data)) {
			r.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
		}
		if (is_function(PXHR.onresponseline)) {
//			PXHR.xhr.onloadstart = function(){
			prevDataLength = lastPos = 0;
			pollTimer = setInterval(handleStream, 100);
		}
		r.send(data?data:null);
		if (!PXHR.async) { onComplete.call(PXHR); }
		return true;
	}
}

Date.longDays = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
Date.shortDays = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
Date.longMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
Date.shortMonths = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

var scalar = {
	toFloat: function() { return parseFloat(this); },
	toInt: function(radix) { return parseInt(this, radix || 10); },
	toJSON: function() { return this.valueOf(); }
},
pw_re = [/[^0-9A-Za-z]+/g, /[0-9]+/g, /[A-Z]+/g, /[a-z]+/g];

var DOM = {

	Array: {
		item: function(i) { return this[i] || null; }
	},

	Boolean: {
		toInt: function() { return this.valueOf() ? 1 : 0; }
	},

	Date: {
		/** Poodle */
		getDayOfYear:    function() { return Math.floor((Date.UTC(this.getFullYear(),this.getMonth(),this.getDate()) - Date.UTC(this.getFullYear(),0,1)) / 86400000); }
		,getDaysInMonth: function() { return 32 - new Date(this.getFullYear(), this.getMonth(), 32).getDate(); }
		,getISODay:      function() { var d = this.getDay(); return d>0 ? d : 7; }
		,getISOYear:     function() { return new Date(this.getFullYear(), this.getMonth(), this.getDate() - ((this.getDay() + 6) % 7) + 3).getFullYear(); }
		,getWeek: function() {
			var d = new Date(this.getFullYear(),0,1),
				wd = d.getISODay(),
				w = Math.ceil((this.getDayOfYear()+wd) / 7);
			/* ISO 8601 states that week 1 is the week with january 4th in it */
			if (4 < wd) { --w; }
			return (1 > w ? (new Date(this.getFullYear()-1,11,31)).getWeek() /* previous year, last week */ : (52 < w && 4 > this.getISODay() ? 1 /* next year, first week */ : w) );
		}
		,isDST: function() {
			var y=this.getFullYear();
			return this.getTimezoneOffset() != max(new Date(y, 0, 1).getTimezoneOffset(), new Date(y, 6, 1).getTimezoneOffset());
		}
		,isLeapYear: function() { var y = this.getFullYear(); return (y%400===0||(y%4===0&&y%100!==0)); }
		,format: function (str, UTC) {
			UTC = UTC||str.match(/\\Z$/);
			var x = this, d = {
				D:(UTC?x.getUTCDay():x.getDay()),
				Y:(UTC?x.getUTCFullYear():x.getFullYear()),
				m:(UTC?x.getUTCMonth():x.getMonth()),
				d:(UTC?x.getUTCDate():x.getDate()),
				H:(UTC?x.getUTCHours():x.getHours()),
				Z:(UTC?0:-x.getTimezoneOffset())
			};
			return !str
			? this.toString()
			: str.replace(/\\?[a-zA-Z]/g,
				function (m) {
					if (m[0] === '\\') { return m[1]; }
					switch (m) {
					// Day
					case 'd': return d.d.pad(2);
					case 'D': return Date.shortDays[d.D];
					case 'j': return d.d;
					case 'l': return Date.longDays[d.D];
					case 'N': return x.getISODay();
					case 'S': return (d.d % 10 == 1 && d.d != 11 ? "st" : (d.d % 10 == 2 && d.d != 12 ? "nd" : (d.d % 10 == 3 && d.d != 13 ? "rd" : "th")));
					case 'w': return d.D;
					case 'z': return x.getDayOfYear();
					// Week
					case 'W': return x.getWeek().pad(2);
					// Month
					case 'F': return Date.longMonths[d.m];
					case 'm': return (d.m + 1).pad(2);
					case 'M': return Date.shortMonths[d.m];
					case 'n': return d.m + 1;
					case 't': return x.getDaysInMonth();
					// Year
					case 'L': return (((d.Y%4===0)&&(d.Y%100 !== 0)) || (d.Y%400===0)) ? '1' : '0';
					case 'o': return x.getISOYear();
					case 'Y': return d.Y;
					case 'y': return ('' + d.Y).substr(2);
					// Time
					case 'a': return d.H < 12 ? "am" : "pm";
					case 'A': return d.H < 12 ? "AM" : "PM";
//					case 'B': return ''; // Swatch Internet time, Not Yet Supported
					case 'g': return d.H % 12 || 12;
					case 'G': return d.H;
					case 'h': return (d.H % 12 || 12).pad(2);
					case 'H': return d.H.pad(2);
					case 'i': return (UTC?x.getUTCMinutes():x.getMinutes()).pad(2);
					case 's': return (UTC?x.getUTCSeconds():x.getSeconds()).pad(2);
					case 'u': return (UTC?x.getUTCMilliseconds():x.getMilliseconds()).pad(3);
					// Timezone
//					case 'e': return ''; // Timezone identifier, Not Yet Supported
					case 'I': return UTC ? 0 : x.isDST() ? 1 : 0;
					case 'O': return UTC ? 'Z' : (d.Z > 0 ? '+' : '-') + Math.abs(d.Z / 60).pad(2) + '00';
					case 'P': return UTC ? 'Z' : (d.Z > 0 ? '+' : '-') + Math.abs(d.Z / 60).pad(2) + ':' + Math.abs(d.Z % 60).pad(2);
					case 'T': return UTC ? 'UTC' : new Date(d.Y, 0, 1).toTimeString().replace(/^.+ \(?([^)]+)\)?$/, '$1');
					case 'Z': return d.Z * 60;
					// Full Date/Time
					case 'c': return x.format(DATE_ISO8601);
					case 'r': return x.format(DATE_RFC2822);
					case 'U': return x.getTime() / 1000;
					}
					return m;
				}
			);
		}
	},

	Number: {
		pad: function(l) { return (''+this).pad(l,'0',0); }
		,bytesToSize:  function(precision){return this.bytesToHuman(precision,['B', 'KiB', 'MiB', 'GiB', 'TiB']);}
		,bytesToSpeed: function(precision){return this.bytesToHuman(precision,['Bps', 'KBps', 'MBps', 'GBps', 'TBps']);}
		,bytesToHuman: function(precision, sizes)
		{
			if (!is_number(precision)) { precision = 2; }
			var i = intval(Math.floor(Math.log(this) / Math.log(1024)));
			if (1>i) { precision = 0; }
			return (this / Math.pow(1024, i)).toFixed(precision) + ' ' + sizes[i];
		}
		,secondsToTime: function(){
			var h = Math.floor(this / 3600),
				m = Math.floor(this % 3600 / 60),
				s = Math.floor(this % 3600 % 60);
			return ((h > 0 ? h + ":" : "") + (m > 0 ? (h > 0 && m < 10 ? "0" : "") + m + ":" : "0:") + (s < 10 ? "0" : "") + s);
		}
	},

	String: {
		addQueryParam: function(n, v) { return (this==''?'':this+'&')+n+'='+encodeURIComponent(v); },
		pad: function(l, s, t) {
			function fillString(s, n) {
				var r = '';
				while (n>0) {
					if (n & 1) r += s;
					n >>= 1;
					if (n) s += s;
				}
				return r;
			}
			s = s || ' ';
			return (l -= this.length) < 1 ? this : (s = fillString(s, Math.ceil(l/s.length))).substr(0, t = !t ? l : t == 1 ? 0 : Math.ceil(l / 2)) + this + s.substr(0, l - t);
		},
		toDate: function(format)
		{
			var i = -1,
				d = {Y:1970, m:1, d:1, H:0, i:0, s:0, u:0},
				f = format.match(/([YmdHis])/g),
				m,
				s = this;
			if (m = s.match(RegExp('('+Date.longMonths.join('|')+')')))  { d.m = Date.longMonths.indexOf(m[1])+1;  s = s.replace(m[1],''); } else
			if (m = s.match(RegExp('('+Date.shortMonths.join('|')+')'))) { d.m = Date.shortMonths.indexOf(m[1])+1; s = s.replace(m[1],''); }
			if (m = s.match(RegExp('('+Date.longDays.join('|')+')')))    { s = s.replace(m[1],''); } else
			if (m = s.match(RegExp('('+Date.shortDays.join('|')+')')))   { s = s.replace(m[1],''); }
			if (m = s.match(/\d{4}/)) { d.Y = m[0]; }
			if (d.Y && (m = s.match(/-W(0[1-9]|[1-4][0-9]|5[0-3])/))) {
				var fd=0, w;
				do { w = new Date(d.Y,0,1+(fd++)); } while (w.getWeek() != 1);
				w = new Date(w.getTime()+(1000*60*60*24*7*(m[1]-1)));
				d.m = w.getMonth()+1;
				d.d = w.getDate();
			} else {
				m = s.trim().match(RegExp(format.trim()
					.replace(/[^YmdH]+/g,'.')
					.replace('d','(3[012]|[12][0-9]|0[0-9])?')
					.replace('m','(1[0-3]|0[0-9])?')
					.replace('Y','(\\d{4})?')
					.replace(/H.*$/,'(?:([01][0-9]|2[0-4]|-1):([0-5][0-9]|-1)(?::([0-5][0-9]|60|-1))?)?')
	//				.replace(/[is]/g,'([0-5]?[0-9])?')
					.replace(/\./g,'[^\\d]')));
				if (f && m) {
					while (f[++i]) { if (m[i+1]) d[f[i]] = intval(m[i+1]); }
					if (0>d.s) {--d.i;d.s=59;}
					if (0>d.i) {--d.H;d.i=59;}
					if (0>d.H) {--d.d;d.H=23;}
					if (1>d.m) {--d.Y;d.m=12;}
				}
			}
			d = (f && m) ? new Date(d.Y, max(1,d.m)-1, d.d, d.H, d.i, d.s, d.u) : null;
			return (d && s.match(/Z$/)) ? new Date(d.getTime()-(d.getTimezoneOffset()*60000)) : d;
		},
		ucfirst: function(){
			var s = this, c = s[0].toUpperCase();
			return c + s.substr(1);
		},
		countSubString: function(s){
			var n = 0, p = this.indexOf(s), l = s.length;
			while (p > -1) {
				++n;
				p = this.indexOf(s, p + l);
			}
			return n;
		}
	},

	/** https://www.w3.org/TR/geometry-1/#dom-domrect */
	DOMRect: {
		contains:  function(p){return this.containsX(p.x) && this.containsY(p.y);},
		containsX: function(x){return x>=this.x && x<this.right;},
		containsY: function(y){return y>=this.y && y<this.bottom;}
		/** Edge lacks x & y values */
		,x: { get:function(){return this.left;} }
		,y: { get:function(){return this.top;} }
	},

	DOMPoint: function(x,y,z,w) {
		this.x = x;
		this.y = y;
		this.z = z;
		this.w = w;
	},

	Node: {
		lowerName: function(){return lc(this.nodeName);},
		parent: function(){return this.parentNode;},
		next:   function(){return this.nextSibling;},
		prev:   function(){return this.previousSibling;},
		first:  function(){return this.firstChild;},
		last:   function(){return this.lastChild;},

		placeAfter:  function(node){return node.parent().insertBefore(this, node.nextSibling);},
		placeBefore: function(node){return node.parent().insertBefore(this, node);},
		getParentByTagName: function(tag, or_this)
		{
			tag = lc(tag);
			var n = or_this ? this : this.parentNode;
			while (n) {
				if (lc(n.nodeName) == tag) return n;
				n = n.parentNode;
			}
			return null;
		}
	},

	HTMLCollection: {
		forEach: Array.prototype.forEach
	},

	/**
	 * HTML5 abandoned the HTMLDocument Interface.
	 * Instead, it extends the Document interface.
	 */
	Document: {
		/** Poodle */
		$:  function(id){return this.getElementById(id);},
		$B: function() { return this.body||this.documentElement; }, // Quirks (HTMLBodyElement) || Standard (HTMLHtmlElement)
		$C: function(tagname, a)
		{
			// Build DOM tree from array
			if (is_array(tagname)) {
				var A=tagname, l=A.length, node=null, r=false, i;
				if (l>0) {
					if (A[1]) {
						r = A[1].ref;
						delete A[1].ref;
					}
					node = this.$C(A[0], A[1]);
					if (a && r) a[r] = node;
					if (!a) a = node;
					for (i=2; i<l; ++i) {
						if (A[i]) {
							if (is_array(A[i][0])) {
								for (var ii=0; ii<A[i].length; ++ii) {
									node.$A(is_scalar(A[i][ii]) ? this.createTextNode(A[i][ii]) : this.$C(A[i][ii], a));
								}
							} else {
								node.$A(is_scalar(A[i]) ? this.createTextNode(A[i]) : this.$C(A[i], a));
							}
						}
					}
				}
				return node;
			}
			// Create element
			var ns = this.$B().namespaceURI, n=lc(tagname), o = ns ? this.createElementNS(ns, n) : this.createElement(n);
			if ('iframe' === n || 'object' === n) {
				// Cross browser DOMFrameContentLoaded
				(()=>{
					var DOMListeners = [], DOMLoaded;
					function DOMReady(e)
					{
						if (!e.target) { e.target=this; }
						if (!DOMLoaded && o===e.target) {
							DOMLoaded = true;
							doc.off(DFCL, DOMReady);
							if ('object' != n) { e.target.off('load', DOMReady); }
							arrCallFunctions(DOMListeners, e, e.target);
						}
					}
					doc.on(DFCL, DOMReady);
					o.onDOMReady = function(fn) { DOMLoaded ? fn() : DOMListeners.push(fn); };
					o.on('load', DOMReady);
				})();
			}
			return extendNode(o, a);
		},
		$D: function(){return this;},
		$H: function() { return this.head||this.$T('head')[0]; },
		$Q: $Q,
		$T: $T,
		$W: function(){return this.defaultView;},
		$X: $X,
//		getElementsByName: function(n),
		getScrollPoint: function()
		{
			var w = this.$W(), b = this.isStrict() ? this.documentElement : this.body;
//			if (is_number(w.pageYOffset)) { return new DOMPoint(w.pageXOffset - b.clientLeft, w.pageYOffset - b.clientTop); }
			if (is_number(w.pageYOffset)) { return new DOMPoint(w.pageXOffset, w.pageYOffset); }
			return b ? b.getScrollPoint() : new DOMPoint(0, 0);
		},
		isStrict:function(){return('BackCompat'!=this.compatMode);},
		/** Event Handling */
		on:     bindEvent,
		off:    unbindEvent,
		trigger:simulateEvent
	},

	Element: {
		toggleAttribute: function(name){this.attr(name, this.hasAttribute(name)?null:'');return this;},
		/** Poodle */
		nextElement: function(){return this.nextElementSibling;},
		prevElement: function(){return this.previousElementSibling;},

		$D: function(){return this.ownerDocument;},
		$T: $T,
		$W: function(){return this.$D().$W();},
		$X: $X,
		attr: function(k,v)
		{
			if (!defined(v)) { return this.getAttribute(k); }
			null!==v ? this.setAttribute(k, v) : this.removeAttribute(k);
			return this;
		},
		data: function(k,v){return this.attr('data-'+k, v);}, /* this.dataset.k*/
		hasFocus: function(){return this==document.activeElement;}
	},

	Event: {
		stop: function(p)
		{
			this.preventDefault();
			if (!p) this.stopPropagation();
		},
		// custom Poodle methods
		scrollStep: function(){return (this.deltaY||this.detail||(this.wheelDelta/-40))/-3;},
		// Extend HTML5 DnD with feature to know which Node is being dragged
		getDraggingNode: function(){return dragNode||(this.dataTransfer?this.dataTransfer.mozSourceNode:null);},
		getDragData: function(format) {
			var dt = this.dataTransfer;
			if (dt) {
				// According to the spec for the drag, dragenter, dragleave, dragover
				// and dragend events the drag data store is protected and not accessible.
				// Could check for: dt.types.indexOf('text/plain')
				return dt.getData(format) || dt.getData('Text');
			}
		},
		// Chrome setData() failing: http://code.google.com/p/chromium/issues/detail?id=50009
		setDragData: function(format, value) {
			var dt = this.dataTransfer;
			if (dt) {
				if ('Text' != format) dt.setData('Text', format+": "+value);
				try { dt.setData(format, value); } catch(e) {}
			}
			return this;
		},
		setDragEffect: function(v) { this.dataTransfer.effectAllowed = v; return this; }
	},

	FormData: {
		addParam: function(n, v) {
			this.append(n, v);
			return this;
		}
	},

	HTMLElement: {
		$A: function(t,a)
		{
			if (is_string(t) || is_array(t)) { t = this.$D().$C(t); }
			return this.appendChild(extendNode(t, a));
		},
		$B:$B,
		$Q:$Q,

		show: function(){return showDialog(this);},
		showModal: function(){return showDialog(this,1);},
		close: function(v){
			// HTMLDialogElement?
			if ('dialog' == lc(this.nodeName)) {
				this.returnValue = v;
				this.attr('open',null).parent().replaceWith(this);
				this.open = false;
				this.$W().onkeydown = null;
				return this.trigger('close');
			} else {
				this.hidden = true;
				return this.attr('hidden', '');
			}
		},
		hide: function(){return this.close();},

		getBoundingPageRect: function()
		{
			var br = this.getBoundingClientRect(), s=this.$D().getScrollPoint();
			return new DOMRect(br.left+s.x, br.top+s.y, br.width, br.height);
		},
		getBoundingPageX: function() { return this.getBoundingPageRect().x; },
		getBoundingPageY: function() { return this.getBoundingPageRect().y; },
		getScrollPoint: function()
		{
			return new DOMPoint(max(0, this.scrollLeft), max(0, this.scrollTop));
		},
		getMaxScrollPoint: function()
		{
			return new DOMPoint(
				max(0, this.scrollWidth - this.clientWidth),  // scrollLeftMax
				max(0, this.scrollHeight - this.clientHeight) // scrollTopMax
			);
		},

		// http://www.quirksmode.org/dom/w3c_css.html
		css: function(rule, force)
		{
			var n=this, v=null;
			try {
				if (!force) { v = n.getCSSPropValue(rule); }
				if (!v) { v = n.$W().getComputedStyle(n, null).getPropertyValue(rule); }
			} catch (e) {}
			return v;
		},

		getCSSPropValue: function(n)
		{
			try {
				var s=this.style;
				return s.getPropertyValue(n) || s.getPropertyValue(cssPrefix+n);
			} catch (e) {}
		},

		setCSSProperty: function(n,v)
		{
			var s=this.style;
			try{
				if (!is_string(v) || !v.length) {
					s.removeProperty(cssPrefix+n);
					s.removeProperty(n);
				} else {
					s.setProperty(cssPrefix+n, v, null);
					s.setProperty(n, v, null);
				}
			}catch(e){console.error(e);}
			try{
				s[cssrule(cssPrefix+n)] = s[cssrule(n)] = v;
			}catch(e){console.error(e);}
			return this;
		},

		setCSS:function(css){
			for (var p in css) { this.setCSSProperty(p, css[p]); }
			return this;
		},

		addClass: function(name) { this.classList.add(name); return this; },
		getClass: function()  { return this.attr('class') || this.className; },
		setClass: function(v) { return this.attr('class', v ? v.trim() : v); },
		hasClass: function(name) { return this.classList.contains(name); },
		replaceClass: function(from, to) { return this.setClass((this.getClass()||'').replace(from, to)); },
		removeClass: function(name) { this.classList.remove(name); return this; },
		toggleClass: function(name) { this.classList.toggle(name); return this; },

		getNextByNodeName: function(name)
		{
			var n = this;
			name = name||n.nodeName;
			while (n = n.next()) { if (n.nodeName == name) return n; }
		},
		getPrevByNodeName: function(name)
		{
			var n = this;
			name = name||n.nodeName;
			while (n = n.prev()) { if (n.nodeName == name) return n; }
		},

		getHeight: function(def)
		{
			/** .client* is without border, offset* with border */
			return max(this.clientHeight, (def?def:0)) - intval(this.css('padding-top')) - intval(this.css('padding-bottom'));
		},

		getMousePos:function(e)
		{
			var br = this.getBoundingClientRect();
			return new DOMPoint(intval(e.clientX-br.x), intval(e.clientY-br.y));
		},

		getWidth: function(def)
		{
			return max(this.clientWidth, (def?def:0)) - intval(this.css('padding-left')) - intval(this.css('padding-right'));
		},

		hasFixedPosition: function()
		{
			var n = this, f = false;
			while (n && !f) {
				f |= ('fixed' == n.css('position'));
				n = n.offsetParent;
			}
			return f;
		},

		appendHTML: function(txt) { return this.html(this.innerHTML + txt); },

		html: function(v) {
			if (defined(v)) {
				try {
					this.innerHTML = v;
					return this;
				} catch (e) {
					console.error(e);
					try {
						let doc = new DOMParser().parseFromString(v, 'text/xml').documentElement;
						if (lc(doc.nodeName) == 'parsererror') {
							console.debug(doc.firstChild.data);
						} else {
							this.$A(this.$D().importNode(doc, true));
							return this;
						}
					} catch (e) {
						console.error(e);
					}
					return false;
				}
			}
			return this.innerHTML;
		},
		// 'text' property is used by A. Use '<![CDATA['+v+']]>' ??
		txt:  function(v) { return defined(v) ? this.html(htmlspecialchars(v)) : this.textContent; },

		/** Event Handling */
		on:     bindEvent,
		off:    unbindEvent,
		trigger:simulateEvent
	},

	HTMLFormElement: {
		// Events.addBeforeSubmit
		addEventBeforeSubmit: function(fn)
		{
			var f = this;
			if (!f.beforesubmit) {
				f.beforesubmit = [];
				f.addEventListener('submit', function(e){arrCallFunctions(f.beforesubmit, e, this);}, false);
			}
			if (fn) f.beforesubmit.push(fn);
			return true;
		}
		/** returns true when one ore more form fields are changed */
		,hasChanges: function(){
			var n, i = 0;
			while (n = this.elements[i++]) {
				switch (lc(n.nodeName))
				{
				case "input":
					if ("submit" == n.type || "reset" == n.type || "button" == n.type) {
						continue;
					}
					if ("checkbox" == n.type || "radio" == n.type) {
						if (n.defaultChecked != n.checked) { return true; }
						break;
					}
				case "textarea":
					if (n.defaultValue != n.value) { return true; }
					break;
				case "select":
					var o = n.options, oi = o.length;
					while (--oi) {
						if (o[oi].defaultSelected != o[oi].selected) {
							return true;
						}
					}
				}
			}
			return false;
		}
		,setChangesAsDefault: function(){
			var n, i = 0;
			while (n = this.elements[i++]) {
				switch (lc(n.nodeName))
				{
				case "input":
					if ("submit" == n.type || "reset" == n.type || "button" == n.type) {
						continue;
					}
					if ("checkbox" == n.type || "radio" == n.type) {
						n.defaultChecked = n.checked;
						break;
					}
				case "textarea":
					n.defaultValue = n.value;
					break;
				case "select":
					var o = n.options, oi = o.length;
					while (--oi) {
						o[oi].defaultSelected = o[oi].selected;
					}
				}
			}
		}
		/** Mark all checkboxes with the given name as checked */
		,checkAll: function(name, uncheck) {
			this.$Q('input[type=checkbox][name*="'+name+'"]').forEach(o => {o.checked = !uncheck;});
		}
		,uncheckAll: function(name) { this.checkAll(name, 1); }
	},

	HTMLInputElement: {
		// http://dev.w3.org/html5/spec/the-input-element.html
		valueAsDate:{
			// Throws an InvalidStateError exception if the control isn't date- or time-based.
			get:function(){return this.getValueAsDate();},
			set:function(date){this.setValueAsDate(date);}
		}
		// Poodle
		,getDateFormat:function(){
			var t = lc(this.attr("type")+" "+this.getClass()).match(dateType),
			f = {
				date:'Y-m-d',
				datetime:'Y-m-d\\TH:i:s\\Z',
				'datetime-local':'Y-m-d\\TH:i:s', // DATE_RFC3339
				month:'Y-m',
				time:'H:i',
				week:'Y-\\WW'
			};
			if (t && 'time' === t[1]) {
				var s = this.getStep();
				if (s < 60) { f.time += ":s"; }
				if (s <  1) { f.time += ".u"; }
			}
			return t ? f[t[1]] : false;
		}
		,getValueAsDate:function(){
			var f = this.getDateFormat(), v = this.value;
			return (v && f) ? this.value.toDate(f) : null;
		}
		,setValueAsDate:function(d){
			var f = this.getDateFormat();
			if (f && (!d || (d >= this.getMinDate() && d <= this.getMaxDate()))) {
				this.value = d ? d.format(f) : null;
			}
		}
		,getMinDate:function(){
			var f = this.getDateFormat(), d = this.getMin();
			return (d && f) ? d.toDate(f) : new Date(0,0,1,0,0,0,0);
		}
		,getMaxDate:function(){
			var f = this.getDateFormat(), d = this.getMax();
			return (d && f) ? d.toDate(f) : new Date(2100,0,1,0,0,0,0);
		}
		,getMin:function(){return getInputMinMax(this, 'min');}
		,getMax:function(){return getInputMinMax(this, 'max');}
		,setMin:function(v){setInputMinMax(this, 'min', v);}
		,setMax:function(v){setInputMinMax(this, 'max', v);}
		,getPassphraseStrength:function(){
			var v = this.value, m,
				i = v.length,
				s = i?1:0,
				c = 0,
				ii = 0;
			while (i--) {
				if (v[i] != v[i+1]) {
					++s;
				} else {
					s -= 0.5;
				}
			}
			for (i = 0; i < 4; ++i) {
				if (m = v.match(pw_re[i])) {
					++c;
					for (; ii < m.length; ++ii) {
						if (5 > m[ii].length) {
							++s;
						}
					}
				}
			}
			s = (s / 3 * c);
			return Math.max(0, Math.min(100, s * 5));
		}
		,getStep:function(){
			if (this.hasClass('time') || this.hasClass('datetime') || this.hasClass('datetime-local') || this.attr('type').includes('time')) {
				return this.getStepSeconds();
			}
			var s = this.attr('step'), v = parseFloat(s);
			return (s&&'any'===lc(s)) ? 0 : (isNaN(v) || 0>=v) ? 1 : v;
		}
		,getStepSeconds:function(){
			// Time: default step is 60 seconds.
			var s = this.attr('step'), v = parseFloat(s);
			v = (s && 'any'===lc(s)) ? 0.001 : (isNaN(v) || 0>=v) ? 60 : v;
			if (v < 1) {
				// 0.001,0.002,0.004,0.005,0.008,0.01,0.02,0.025,0.04,0.05,0.1,0.125,0.2,0.25,0.5
				if (!v || 0 !== (1000 % intval(v*1000))) { v = 0.001; }
			} else {
				v = intval(v);
				// 1,2,3,4,5,6,10,12,15,20,30
				if (v > 1 && v < 60 && 0 !== (60 % v)) { v = 1; }
				// 120,180,240,300,360,600,720,900,1200,1800
				if (v > 60 && v < 3600 && 0 !== (3600 % v)) { v = 60; }
				// 7200,10800,14400,21600,28800,43200
				if (v > 3600 && v < 86400 && 0 !== (86400 % v)) { v = 3600; }
			}
			return v;
		}
		,initValue:function(v){
			if ("checkbox"==this.type || "radio"==this.type) {
				this.checked = this.defaultChecked = !!v;
			} else {
				this.value = this.defaultValue = v;
			}
			this.trigger("change");
		}
/*
		readonly attribute boolean willValidate;
		readonly attribute ValidityState validity;
		readonly attribute DOMString validationMessage;
		void setCustomValidity(in DOMString error);
*/
	},

	HTMLSelectElement: {
		currentOption: function() {
			var i = this.selectedIndex, o = this.options;
			return (0 > i || i > o.length-1) ? new Option("","") : o[i];
		},
		setSelectedByValue: function(v, d) {
			var o = this.options, i=0, r = false;
			for (;i<o.length;++i) {
				if (v == o[i].value) {
					r = true;
//					r = o[i].selected = true;
//					o[i].attr("selected", "");
					if (d) {
						o[i].defaultSelected = true;
					}
					this.selectedIndex = i;
					this.trigger('change');
					if (!d) {
						break;
					}
				} else if (d) {
					o[i].defaultSelected = false;
//					o[i].selected = false;
//					o[i].attr("selected", null);
				}
			}
			return r;
		}
	},

	HTMLIFrameElement: { $Doc: contentDoc, $Win: contentWin },
	HTMLObjectElement: { $Doc: contentDoc, $Win: contentWin },

	Window: {
		$D:function(){return this.document;},
		getHash:function(){return this.location.href.replace(/^[^#]+#?/, '');},
		setHash:function(id){
			var n=this.$Q('#'+id,1);
			if (n) n.id='';
			this.location.hash='#'+id;
			if (n) n.id=id;
		},
		PoodleL10N: L10N,
		PoodleXHR: XHR,
		_: function(txt) { return Poodle.L10N.get(txt); },
		/** Event Handling */
		on:     bindEvent,
		off:    unbindEvent,
		trigger:simulateEvent
	}
};

extendObj(DOM.Boolean, scalar);
extendObj(DOM.Number, scalar);
extendObj(DOM.String, scalar);

win.PoodleDOM = function(w)
{
	this.L10N = new L10N;

	let k, doc = w.document, P = this, DCVC='DOMControlValueChanged', grouped = console.groupEnd;
	if (grouped) {
		console.groupCollapsed("PoodleDOM");
	}

	/**
	 * short notations
	 */
	this.$=function(id) {return doc.$(id);};
	P.$B = function()   {return doc.$B();};
	P.$C = function(t,a){return doc.$C(t,a);};
	P.$D = function()   {return doc;};
	P.$H = function()   {return doc.$H();};
	P.$Q = function(s,n){return doc.$Q(s,n);};
	P.$T = function(s)  {return doc.$T(s);};
	P.$W = function()   {return w;};

	/**
	 * DOM Prototype
	 */
	P.DOM = DOM;
	P.extend = extendObj;
	P.extendDOM = extendDOM;
	P.extendNode = extendNode;

	for (k in DOM) {
		if (is_function(DOM[k])) {
			if (!is_function(w[k])) w[k] = DOM[k];
		} else if (w[k] && w[k].prototype) {
			extendDOM(w[k].prototype, DOM[k]);
		}
	}

	function watchProp(o, name)
	{
		var opd = Object.getOwnPropertyDescriptor(o, name), fn="set"+name.ucfirst();
		if (opd && opd.set) {
			Object.defineProperty(o, name, {set:function(v){
				var ov=this[name];
				opd.set.call(this, v);
				v=this[name];
				if (ov!=v) this.trigger(DCVC,{propertyName:name, newValue:v, prevValue:ov});
			}});
			o[fn] = function(v){this[name] = v;};
			return true;
		}
		console.error("Failed to watch property '"+name+"'");
		o[fn] = function(v){
			var ov=this[name];
			this[name] = v;
			if (ov!=v) { this.trigger(DCVC,{propertyName:name, newValue:v, prevValue:ov}); }
		};
		return false;
	}
	//var el=['HTMLSelectElement','HTMLTextAreaElement','HTMLInputElement'], i=0;
	var o = w.HTMLInputElement.prototype;
	watchProp(o, 'value');
	watchProp(o, 'checked');
	watchProp(w.HTMLTextAreaElement.prototype, 'value');

	/**
	 * WebKit DOM Window/Document workaround
	 */
	if (!w.on) { extendDOM(w, DOM.Window); }
	if (!doc.on) { extendDOM(doc, DOM.Document); }

	// simulate_hashchange does all the work of triggering the window.onhashchange
	// event for browsers that don't natively support it, including creating a
	// polling loop to watch for hash changes to enable back and forward.
	if (!('onhashchange' in win)) {
		// Remember the initial hash so it doesn't get triggered immediately.
		var lh = w.getHash();
		// This polling loop checks every 100 milliseconds to see if
		// location.hash has changed, and triggers the 'hashchange' event on
		// window when necessary.
		setInterval(function(){
			var h = w.getHash();
			if (h !== lh) {
				lh = h;
				w.trigger('hashchange');
			}
		}, 100);
	}

	/**
	 * Poodle
	 */
	P.UA = extendObj({}, ua);
	P.GA_ID = null; // Google Analytics code
	P.createCSS = createCSS;
	P.scrollStep = function(e){return (e.detail||(e.wheelDelta/-40))/-3;};
	P.getCSSMediaType = function()
	{
		var i=1, id = 'PoodleMediaInspector', o = doc.$(id),
		t = ['all','aural','braille','embossed','handheld','print','projection','screen','speech','tty','tv'];
		if (!o) {
			createCSS('#'+id+'{display:none;width:0px}','all');
			for (;i<11;++i) { createCSS('#'+id+'{width:'+i+'px}',t[i]); }
			o = doc.$B().$A(doc.$C('div', {id:id}));
		}
		return t[o?intval(o.css('width',1)):0];
	};

	P.getCookie = function(name)
	{
		var m = document.cookie.match(new RegExp("(?:^|;\\s*)"+name+"=([^;]*)"));
		return m ? decodeURIComponent(m[1]) : null;
	};
	P.setCookie = function(name, value, maxage, path, domain, secure, httponly, samesite)
	{
		var v = name+'=';
		if (is_scalar(value) && '' != value) {
			v += encodeURIComponent(value);
			maxage = intval(maxage);
			if (maxage) {
				v = + "; expires=" + (new Date(new Date() + (maxage*1000))).toUTCString() + '; Max-Age=' + maxage;
			}
		} else {
			v += 'deleted; expires=Thu, 01 Jan 1970 00:00:01 GMT; Max-Age=0';
		}
		v += '; path='+(path||'/');
		if (is_string(domain) && domain.length) {
			v += '; domain='+domain;
		}
		if (secure)   { v += '; Secure'; }
		if (httponly) { v += '; HttpOnly'; }
		v += '; SameSite=' + (samesite || 'Strict'); // Lax
		document.cookie = v;
	};
	P.delCookie = function(name, path, domain, secure, httponly, samesite)
	{
		this.setCookie(name, '', 0, path, domain, secure, httponly, samesite);
	};

	P.version = function(){return version;};
	P.loadScript = function(file){doc.head.$A('script', {type:'text/javascript',src:file.replace(/&amp;/gi,'&')});};
	P.Debugger = function(){};

	P.strContains = function(src, str)
	{
		if (src) {
			if (str != src && !defined(strContains_re[str])) strContains_re[str] = new RegExp("(^|\\s)"+str+"(\\s|$)");
			return !!(str == src || src.match(strContains_re[str]));
		}
		return false;
	};

	P.dragStart = startDrag;
	P.dragEnd   = endDrag;

	k = doc.location;
	P.HOST = k.protocol+'//'+k.host;
	P.ROOT = '/';
	P.PATH = '/index.php/';
	P.JS_PATH = P.PATH+'javascript/';
	P.CSS_PATH = P.PATH+'css/';
	k = doc.scripts;
	var m, i=0, re = new RegExp('^(?:'+P.HOST+')?(([^:]*/)(?:[a-z]{2}(?:-[a-z]{2})?/)javascript/)[^/]*$');
	for (; i<k.length; ++i) {
		m = re.exec(k[i].src);
		if (m) {
			P.PATH = m[2];
			P.ROOT = m[2].replace(/\/[^/]+\.php\/$/,'/');
			P.JS_PATH = m[1];
			break;
		}
	}
	m = (new RegExp('[\'"]([^\'"]*/css/([^\'"/]+)/)[^\'"]*style')).exec(doc.head.innerHTML);
	if (m) {
		P.CSS_PATH = m[1];
		P.TPL = m[2];
	}

	P.PostMax = 8388608;
	P.PostMaxFiles = 20;
	P.PostMaxFilesize = 2097152;

	P.initHTML = node => {
		// HTML5 details + summary
		(node || doc).$Q('details > summary').forEach(n => {
			var p = n.parentNode;
			if (!defined(p.open)) {
				p.open = p.hasAttribute('open');
				n.on('click',function(){
					this.parentNode.toggleAttribute('open');
				});
			}
		});
/*
		node.on('click', function(e){
			var n = e.target.getParentByTagName('summary', 1);
			if (n) {
				n.parentNode.toggleAttribute('open');
			}
		});
*/
	};

	/**
	 * Cross-browser DOMContentLoaded
	 * Inspired by Dean Edwards' blog: http://dean.edwards.name/weblog/2006/06/again/
	 * Will fire an event when the DOM is loaded (supports: Gecko, Opera9+, WebKit)
	 */
	if (window === w) {
		var DOMListeners = [], DOMLoaded, DOMTimer, callFn = () => {
			if (DOMLoaded) { return; }
			if (grouped) {
				console.groupCollapsed("PoodleDOMLoad");
			}
			DOMLoaded = true;
			if (DOMTimer) {
				clearInterval(DOMTimer);
				DOMTimer = 0;
			}
			console.info("Poodle\t"+version);
//			console.info("CSS \t"+P.getCSSMediaType());
			console.info("XMLNS\t"+(doc.$B().namespaceURI||'none'));

			// HTML5 link preload
			P.$Q('head link[as="style"]').forEach(n => n.rel='stylesheet');
			P.$Q('style[data-btf]').forEach(n => n.after(P.$C('link', {rel:'stylesheet',href:n.data('btf')})));

			P.initHTML();

			var n, cn, q, v;
			P.$Q('input.p-challenge').forEach(n => {
				cn = n.attr('name');
				q = P.getCookie(cn) || 'n';
				v = q.substr(2);
				if ('q' === q[0]) { n.attr('title', v).attr('placeholder', v); }
				if ('h' === q[0]) { n.style.display = 'none'; n.value = v; }
				if ('i' === q[0]) { P.$C('img', {src:v}).placeBefore(n); }
				P.delCookie(cn);
			});

			P.$Q('form[data-p-challenge]').forEach(n => {
				cn = n.data('p-challenge');
				q = P.getCookie(cn) || 'n';
				if ('h' === q[0]) { n.$A('input', {type:'hidden', name:cn, value:q.substr(2)}); }
				n.data('p-challenge', null);
				P.delCookie(cn);
			});

			P.$Q('abbr[title]').forEach(n=>n.on('click', e => {
					if ('click' !== e.type) {
						alert(e.target.txt() + ': '+ e.target.title);
					}
				})
			);

			arrCallFunctions(DOMListeners, null, doc);
			DOMListeners = [];

			if ((n = P.$('cookieconsent')) && P.getCookie('consent')) {
				console.info('Remove cookieconsent');
				n.remove();
			}

			if (P.GA_ID && !nav.doNotTrack) {
				w['GoogleAnalyticsObject']='ga';
				w.ga=w.ga||function(){(w.ga.q=w.ga.q||[]).push(arguments);};
				w.ga.l=1*new Date();
				w.ga('create', P.GA_ID, doc.location.host);
				w.ga('send', 'pageview');
				P.loadScript('//www.google-analytics.com/analytics.js');
			}

			console.log(DCL);
			if (grouped) {
				console.groupEnd();
			}
		};
		doc.addEventListener(DCL, callFn, false);
		P.onDOMReady = fn => { DOMLoaded ? fn() : DOMListeners.push(fn); };
	} else {
		extendDOM(w, window);
	}
	if (grouped) {
		console.groupEnd();
	}
};

/**
 * End Poodle DOM initialization
 */
})();

this.Poodle = new PoodleDOM(this);
