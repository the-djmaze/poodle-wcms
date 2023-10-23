/*	Poodle WCMS, Copyright (c) MH X Solutions since 2008. All rights reserved.

	@import "poodle"
*/

Poodle.onDOMReady(()=>{
	var btn = Poodle.$Q('button.remove',1);
	if (btn) {
		btn.on('click',function(e){if(!confirm(this.attr('title'))){e.stop();}});
	}
});
