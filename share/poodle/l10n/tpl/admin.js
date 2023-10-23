/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	@import "poodle"
*/

Poodle.onDOMReady(()=>{
	var o = Poodle.$('translate-l10n_id');
	if (o) { o.on('change', e => document.location.href="?l10n_id="+e.target.value); }
});
