/**
	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	@import "poodle"
*/

var Poodle_Tabs = (()=>{

var loaded=false;

// attach to window onload event
Poodle.onDOMReady(()=>Poodle_Tabs());

return function()
{
	if (arguments[0]) {
		initControl(arguments[0]);
	} else {
		document.$Q('.tabs').forEach(n=>initControl(n));
	}

	function initControl(node)
	{
		if (!defined(node.selectedTab)) {
			var h = node.$W().getHash(), si=-1, i=-1, t;
			while (t = node.children[++i]) {
				if (1==t.nodeType && ((h && h==t.id) || (0>si && t.hasClass('current')))) {
					si = i;
				}
			}
			node.selectedTab = si;
			node.show_tab = function(index) {
				index = Math.min(this.children.length-1, Math.max(0, index));
				this.selectedTab = -1;
				var tab, i=-1, r=false; // firstChild.nextElementSibling
				while (tab = this.children[++i]) {
					if (index === i) {
						r = true;
//						node.$W().setHash(tab.id);
						this.selectedTab = i;
						tab.trigger('show');
						if (tab.hasClass('ajax')) {
							tab.ajax = new PoodleXHR();
							tab.ajax.oncomplete = show_cb;
							tab.ajax.tab = tab;
							if (!tab.ajax.get(tab.firstElementChild.href)) {
								delete tab.ajax;
								tab.ajax = null;
								r = false;
							}
						}
						tab.addClass('current');
					} else {
						tab.removeClass('current');
					}
				}
				return r;
			};
			node.show_tab(node.selectedTab);
			node.on("click",show_tab);

			if (!loaded) {
				loaded = true;
				if (node.hasClass('tabs-scroll')) {
					window.on('wheel', scroll_tab);
//					document.on('wheel', function(e){console.dir(e)});
				}
			}
		}
	}

	/* mouse activities */
	function show_tab(e)
	{
		var tab = e.target, i=0, s;
		if (this!=tab) {
			while (tab && tab.parent && !tab.parent().hasClass('tabs')) { tab = tab.parent(); }
			if (tab && !tab.hasClass('current')) {
				s = tab;
				while (s = s.prevElement()) { ++i; }
				if (tab.parent().show_tab(i)) { e.stop(1); }
			}
		}
	}

	function scroll_tab(e)
	{
		var n = e.target, h=20, t;
		while (n && n.parent && n.hasClass && !n.hasClass('tabs')) { n = n.parent(); }
		if (n && n.getMousePos && n.hasClass('tabs-scroll')) {
			//if (t = n.$Q('a.tab',1)) { h = t.offsetHeight+t.offsetTop; }
			if (t = n.$Q('> *',1)) { h = t.offsetHeight+t.offsetTop; }
			if (h > n.getMousePos(e).y) {
				n.show_tab(n.selectedTab - Poodle.scrollStep(e));
				e.preventDefault();
				e.stopPropagation();
			}
		}
	}

	function show_cb(ajax)
	{
		var tab = ajax.tab, n;
		if (ajax.request.status == 200) {
			n = tab.$Q('> *:nth-child(2)',1);
			if (n && n.html(ajax.request.responseText)) tab.removeClass('ajax');
		} else {
			Poodle.msg.error(ajax.request.responseText);
		}
		delete tab.ajax;
		tab.ajax = null;
	}

};

})();
