/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	@import "poodle"

	Note: when using zoom, the area rectangular can be marginally off with
	      1-2 pixels due to number rounding. This is acceptable as for real
	      sizing you should use GiMP, Photoshop or any other image editor.
*/

var Poodle_AreaSelector = (()=>{

var mousepos,
	max = Math.max,
	min = Math.min,
	round = Math.round;

return function(node)
{
	var box, area, area_r, aspectRatio, outside, zoom=1,
	org_h, org_w,
	max_h, max_w,
	node_w, node_h;

	node.areaselector = this;

	this.getHeight = () => node_h;

	this.getWidth  = () => node_w;

	this.setHeight = function(h) { this.setSize(node_w, h); };

	this.setWidth  = function(w) { this.setSize(w, node_h); };

	this.setSize = function(w, h)
	{
		node_h = max(1,h);
		node_w = max(1,w);
		node.setCSS({height:node_h+'px', width:node_w+'px'});
		org_h = max_h = node.clientHeight;
		org_w = max_w = node.clientWidth;
		this.setZoom(zoom * 100);
		node.trigger('change');
	};

	this.setAspectRatio = function(x,y)
	{
		area_r = null;
		x = intval(x);
		y = intval(y);
		if (0<x && 0<y) {
			// We could calculate the greatest common divisor but not needed
			aspectRatio = {x:x, y:y};
			this.showArea();
		} else {
			aspectRatio = null;
			this.hideArea();
		}
	};

	// Percentage
	this.setZoom = function(v)
	{
		zoom = max(10, intval(v)) / 100;
		max_h = round(org_h * zoom);
		max_w = round(org_w * zoom);
		var css = {height:max_h+'px', width: max_w+'px'};
		node.setCSS(css);
		if (area_r) {
			this.showArea();
		}
		if (area.img) {
			area.img.setCSS(css);
		}
	};

	this.getSelection = () =>
	{
		return area_r;
	};

	// Selection area
	this.showArea = () =>
	{
		if (box) {
			if (!area_r) {
				var l = 0, t = 0, r = org_w, b = org_h;
				if (aspectRatio) {
					var arw = org_w/aspectRatio.x, arh = org_h/aspectRatio.y;
					if (arw > arh) {
						r = round(arh*aspectRatio.x);
						l = Math.floor((org_w-r)/2);
					} else {
						b = round(arw*aspectRatio.y);
						t = Math.floor((org_h-b)/2);
					}
				}
				area_r = new DOMRect(l, t, r, b);
			}
			var ol = node.offsetLeft-1,
				ot = node.offsetTop-1,
				x = round(area_r.x * zoom),
				y = round(area_r.y * zoom),
				h = round(area_r.height * zoom),
				w = round(area_r.width * zoom),
				bl = area.blocks;
			area.setCSS({
				'left':  (ol + x)+'px',
				'top':   (ot + y)+'px',
				'height':max( 0,h)+'px',
				'width' :max( 0,w)+'px'
			});
			if (area.img) {
				area.img.setCSS({
					'left': (-max(ol,x)+node.offsetLeft-1)+'px',
					'top':  (-max(ot,y)+node.offsetTop-1)+'px',
					'position': 'absolute'
				});
			}
			bl.t.setCSS({left:round((w-8)/2)+'px'});
			bl.r.setCSS({top:round((h-8)/2)+'px'});
			bl.b.setCSS({left:round((w-8)/2)+'px'});
			bl.l.setCSS({top:round((h-8)/2)+'px'});
			outside.show();
			area.show();
		}
	};

	this.hideArea = () =>
	{
		if (!outside.hasAttribute('hidden')) {
			node.trigger('resize');
			outside.hide();
			area.hide();
		}
	};

	function __construct()
	{
		box = Poodle.$C('div',{'class':'poodle-areaselector'});
		node.replaceWith(box);

		box.$A(node);
		node.setCSS({height:node_h+'px',width:node_w+'px'});
		org_h = max_h = node.clientHeight;
		org_w = max_w = node.clientWidth;

		outside = box.$A('div',{'class':'outside'}).hide();

		area = box.$A('div',{'class':'selection'})
			.hide()
			.on('mousedown',areaMouseDown);
		area.blocks = {
			tl: area.$A('div',{'class':'block tl'}),
			 t: area.$A('div',{'class':'block t'}),
			tr: area.$A('div',{'class':'block tr'}),
			 r: area.$A('div',{'class':'block r'}),
			br: area.$A('div',{'class':'block br'}),
			 b: area.$A('div',{'class':'block b'}),
			bl: area.$A('div',{'class':'block bl'}),
			 l: area.$A('div',{'class':'block l'})
		};
		if ('img'===node.lowerName()) {
			area.img = area.$A('img',{src:node.src});
			node.on('load',function(){
				area.img.src=node.src;
				node.areaselector.setSize(this.naturalWidth||this.width, this.naturalHeight||this.height);
			});
		}

		box.on('mousedown',boxMouseDown)
		   .on('mouseup',boxMouseUp)
		   .on('mousemove',boxMouseMove);

		document
			.on('mouseup',boxMouseUp)
			.on('mousemove',function(e){if(mousepos){boxMouseMove.call(box,e);}});
	}
	__construct();

	function areaMouseDown(e)
	{
		mousepos = this.getMousePos(e);
		e.stop();
	}

	function boxMouseDown(e)
	{
		area_r = null;
		mousepos = this.getMousePos(e);
		e.stop();
	}

	function boxMouseUp()
	{
		if (!area_r) { node.areaselector.hideArea(); }
		if (mousepos) {
			mousepos = null;
			node.trigger('change');
		}
	}

	/** Resizes the area */
	function boxMouseMove(e)
	{
		if (mousepos) {
			var mpos, sarea,
			 min_x = node.offsetLeft-1,
			 min_y = node.offsetTop-1,
			  mode = box.css('cursor').replace('-resize','');
			if (area_r) {
				sarea = new DOMRect(
					round(area_r.x * zoom),
					round(area_r.y * zoom),
					round(area_r.width * zoom),
					round(area_r.height * zoom)
				);
			} else {
				sarea = new DOMRect(mousepos.x, mousepos.y, 0, 0);
			}
			if ('move' == mode) {
				mpos = node.getMousePos(e);
				sarea.x = min(max(min_x, mpos.x-mousepos.x), max_w - sarea.width + min_x);
				sarea.y = min(max(min_y, mpos.y-mousepos.y), max_h - sarea.height + min_y);
			} else {
				mpos = this.getMousePos(e);
				mpos.x = max(min_x, mpos.x);
				mpos.y = max(min_y, mpos.y);
				if ('crosshair' == mode) {
					mode = ((mpos.y > mousepos.y) ? 's' : 'n')
						+ ((mpos.x > mousepos.x) ? 'e' : 'w');
				}
				if ('n' == mode[0]) {
					mpos.y = round(round(mpos.y / zoom) * zoom);
					sarea.height += sarea.y - mpos.y;
					sarea.y = mpos.y;
				} else if ('s' == mode[0]) {
					sarea.height = mpos.y - sarea.y;
				}
				if ('e' == mode.slice(-1)) {
					sarea.width = mpos.x - sarea.x;
				} else if ('w' == mode.slice(-1)) {
					mpos.x = round(round(mpos.x / zoom) * zoom);
					sarea.width += sarea.x - mpos.x;
					sarea.x = mpos.x;
				}
				sarea.width  = min(max_w-sarea.x+min_x, sarea.width);
				sarea.height = min(max_h-sarea.y+min_y, sarea.height);

				if (aspectRatio) {
					var ah = sarea.height,
						aw = sarea.width,
						arw = aw / aspectRatio.x,
						arh = ah / aspectRatio.y;
					if ('e'==mode || 'w'==mode || (arw > arh && 2 == mode.length)) {
						sarea.height = round(arw*aspectRatio.y);
						if ('n'==mode[0]) {
							sarea.y -= sarea.height-ah;
						}
						// Fix boundaries
						if (min_y > sarea.y || max_h < sarea.height + sarea.y) {
							if (min_y > sarea.y) {
								sarea.height += (min_y - sarea.y);
								sarea.y = min_y;
							} else {
								sarea.height = max_h - sarea.y;
							}
							sarea.width = round(sarea.height / aspectRatio.y * aspectRatio.x);
							if ('w' == mode.slice(-1)) {
								sarea.x -= sarea.width-aw;
							}
						}
					} else
					if ('n'==mode || 's'==mode || arw < arh) {
						sarea.width = round(arh*aspectRatio.x);
						if ('w' == mode.slice(-1)) {
							sarea.x -= sarea.width-aw;
						}
						// Fix boundaries
						if (min_x > sarea.x || max_w < sarea.width + sarea.x) {
							if (min_x > sarea.x) {
								sarea.width += (min_x - sarea.x);
								sarea.x = min_x;
							} else {
								sarea.width = max_w - sarea.x;
							}
							sarea.height = round(sarea.width / aspectRatio.x * aspectRatio.y);
							if ('n'==mode[0]) {
								sarea.y -= sarea.height-ah;
							}
						}
					}
				}
			}
			area_r = new DOMRect(
				round(sarea.x / zoom),
				round(sarea.y / zoom),
				round(sarea.width / zoom),
				round(sarea.height / zoom)
			);
			node.areaselector.showArea();
			node.trigger('resize');
			e.stop();
		} else {
			var l=intval(this.css('left')), t=intval(this.css('top')),
				r = new DOMRect(l, t, area.clientWidth, area.clientHeight),
				p = area.getMousePos(e),
				cursor = '';
			if (r.contains(p)) {
				if (p.y <= 10) {
					cursor = 'n';
				} else if (p.y >= r.height - 10) {
					cursor = 's';
				}
				if (p.x <= 10) {
					cursor += 'w';
				} else if (p.x >= r.width - 10) {
					cursor += 'e';
				}
				cursor = cursor ? cursor+'-resize' : 'move';
			}
			box.setCSS({cursor:cursor?cursor:'crosshair'});
		}
	}

};

})();
