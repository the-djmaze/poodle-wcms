/**
 * CSS3:
 *     overflow: auto;
 *     resize: none | both | horizontal | vertical;

	@import "poodle"
 */

var PoodleResizer = (K => {

var offset, current;

K.onDOMReady(() => {
	K.$Q(".resizable, *[data-resizable]").forEach(n => new PoodleResizer(n));
});

return function(o, wo, ho)
{
	if (o.resizer) return o.resizer;
	o.resizer = this;

	wo = wo || o;
	ho = ho || o;
	/* public */
	this.obj = o;
	this.w_obj = wo; // horizontal resize element
	this.h_obj = ho; // vertical resize element

	this.minW = () => intval(wo.css("min-width",1));
	this.minH = () => intval(ho.css("min-height",1));
	this.maxW = () => intval(wo.css("max-width",1));
	this.maxH = () => intval(ho.css("max-height",1));

	this.getCursor = (e, o) =>
	{
		var r=this, c = "", w=5, h=5, p;
		if (o == r.obj)
		{
			var pw = "right";
			if (pw===wo.css("float")) {
				pw = "left";
			}
			h = Math.max(h, intval(wo.css("padding-bottom"))/2);
			w = Math.max(w, intval(wo.css("padding-"+pw))/2);
			p = o.getMousePos(e);
			if (p.x >= 0 && p.x < o.offsetWidth
			 && p.y >= 0 && p.y < o.offsetHeight)
			{
				if ((r.minH()||r.maxH()) && p.y > o.offsetHeight-h) { c = "s"; }
				if (r.minW()||r.maxW()) {
					if ("left"===pw) {
						if (p.x < w) { c += "w"; }
					} else if (p.x > o.offsetWidth-w) {
						c += "e";
					}
				}
			}
		}
		return c;
	};

	// mousecursor
	this.hover = e =>
	{
		if (!offset) {
			var o = this.obj,
			    s = o.$B().style,
			    c = this.getCursor(e, e.currentTarget);
			if (c) { c += "-resize"; }
			if (c != o.cursor || c != s.cursor) {
				o.className = o.className.replace(/[sew]+-resize/,"").trim();
				s.cursor = o.cursor = c;
				if ("" != c) o.addClass(c);
			}
		}
	};

	this.onmousedown = null;
	this.mousedown = e =>
	{
		if (!this.getCursor(e, e.currentTarget)) return;
		current = this;
		var r=this, wo=r.w_obj, ho=r.h_obj
		,m = r.obj.cursor.replace("-resize","")
		,w = wo.getWidth()
		,h = ho.getHeight();
		if ("w"===m||"sw"===m) w = -w;
		offset = new DOMPoint(e.pageX-w, e.pageY-h);
		if (r.onmousedown) r.onmousedown(e);
		e.stop();
	};

	this.onresize = null;
	this.onmouseup = null;
	this.mouse = e =>
	{
		if (offset && this==current) {
			if ('mouseup' == e.type) {
				var d = document;
				K.$B(d).style.cursor = "";
				offset = current = null;
				if (this.onmouseup) this.onmouseup(e);
				e.stop();
			}
			if ('mousemove' == e.type) {
				var r = this,
					s = new DOMPoint(e.pageX-offset.x, e.pageY-offset.y),
					m = r.obj.cursor.replace("-resize",""),
				   mw = intval(wo.css("max-width",1)),
				   mh = intval(ho.css("max-height",1));
				if ("w"===m||"sw"===m) { s.x = -s.x; }
				if (r.onresize) r.onresize(e, s, m);
				s.x = Math.max(r.minW(), s.x);
				s.y = Math.max(r.minH(), s.y);
				if (mw) s.x = Math.min(mw, s.x);
				if (mh) s.y = Math.min(mh, s.y);
				// set the new size
				switch (m)
				{
				case "se":
				case "sw":
				case "e":
				case "w": r.w_obj.style.width  = s.x+"px"; if("s"!=m[0]) break;
				case "s": r.h_obj.style.height = s.y+"px";
				}
				e.stop();
			}
		}
	};

	o.on("mouseout",  this.hover)
	 .on("mousemove", this.hover)
	 .on("mousedown", this.mousedown);
	document.on("mousemove mouseup", this.mouse);
};

})(Poodle);
