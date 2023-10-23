/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	@import "poodle"
*/

(K=>{

function S()
{
	var i=0, fs=arguments, s;
	if (!fs.length) fs=K.$T('select');
	while (s = fs[i++]) {
		if (!s.textbox && "select-one" === s.type)
		{

			/** Type 1 */

			var d = K.$C('span', {'class':'selectbox'}),
			    o = s.currentOption();
			s.textbox = d.$A('span',{textContent:o?o.txt():''});
			s.div = d;
			s.on('change',function(){
				var o = this.currentOption();
				this.textbox.txt(o?o.txt():'');
			})
			.on('keyup',function(){this.trigger('change')})
			.on('keydown',function(){this.trigger('change')})
			.on('focus',function(){this.div.addClass('focus')})
			.on('blur',function(){this.div.removeClass('focus')})
			.replaceWith(d);
			d.$A(s);


			/** Type 2 */
/*
			var span = K.$C('span', {'class':'selectbox2', sel:s}),
			   d2 = span.$A('div'),
			 opts = s.options, oi, a;
			for (oi=0; oi<opts.length; ++oi) {
				a = d2.$A('a', {href:'#', textContent:opts[oi].text});
				if (oi === s.selectedIndex) {
					a.addClass('selected');
				}
				a.selIndex = opts[oi].index;
			}
			span.placeAfter(d);
			span.on('click',function(e){
				this.toggleClass('expand');
				var n = e.target, p = this.$Q('.selected',1), s = this.sel;
				if (this.hasClass('expand')) {
					if (p || (p = this.$Q('a',1))) {
						n = p.next()||p.prev();
						if (n) n.focus();
						setTimeout(function(){p.focus()}, 1);
					}
				} else if ('a' === n.lowerName()) {
					if (p) p.removeClass('selected');
					n.addClass('selected');
					s.selectedIndex = n.selIndex;
					s.trigger('change');
				}
			});
			window.on('mousedown',function(e){
				if (span.hasClass('expand') && !span.contains(e.target)) {
					span.trigger('click');
				}
			});
			span.on('keydown',function(e){
				var cc = e.which||e.keyCode, n = e.target; // e.charCode
				if (37<=cc && 40>=cc) {
					if (!span.hasClass('expand')) {
						span.trigger('click');
					} else {
						if (37==cc || 38==cc) {
							n = n.prev();
						} else {
							n = n.next();
						}
						if (n) n.focus();
					}
					e.stop();
				}
				if (27==cc && span.hasClass('expand')) {
					span.trigger('click');
					e.stop();
				}
			});
*/
		}
	}
}

K.onDOMReady(()=>S());
K.FormSelect = S;

})(Poodle);
