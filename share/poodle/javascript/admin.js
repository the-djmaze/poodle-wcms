/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	@import "poodle"
*/

Poodle.onDOMReady(()=>{

	function diffHref(href)
	{
		var lh = l.href, i = 0, m = Math.max(lh.length, href.length);
		for (; i<m; i++) {
			if (lh[i] !== href[i] || '#' === href[i])
				break;
		}
		return i;
	}

	// section-panel
	var first, current, current_a, l = document.location;
	Poodle.$Q('#leftside-body a[href]').forEach(n => {
		n.on('click',function(e){
			var ah = this.href, i = diffHref(ah);
			ah = ah.substr(i);
			if ('#' === ah[0]) {
				/*if ('!' === ah[1]) {
					// do xhr?
				}*/
				var d = Poodle.$Q(ah,1);
				if (d && this !== current_a) {
					if (current) {
						if (current.removeClass) current.removeClass('current');
						current_a.removeClass('current');
					}
					current = d.addClass('current');
					current_a = this.addClass('current');
				}
				e.stop();
			}
			else if (this.href === l.href) {
				current = true;
				current_a = this.addClass('current');
				e.stop();
			}
		});

		if (!current) {
			if (l.href === n.href) {
				n.trigger('click');
			} else if (!first && '#' === n.href.substr(diffHref(n.href))[0]) {
				first = n;
			}
		 }
	});
	if (!current && first) {
		first.trigger('click');
	}

/*
	window.on('beforeunload', e => {
		var f = Poodle.$Q('form.ask-unload'), i=0;
		for (;i<f.length;i++) {
			if (f[i].hasChanges()) {
				return e.stop();
			}
		}
	});
*/

});
