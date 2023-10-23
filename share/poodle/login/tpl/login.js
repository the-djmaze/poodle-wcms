/*
	@import "poodle"
*/

Poodle.onDOMReady(()=>{
	var P = Poodle, s = P.$('auth-provider');
	if (s) {
		s.on('change', e => {
			var i = 0,
			nodes = P.$Q('#auth-providers > div'),
			  o = P.$('auth-provider-'+e.target.value);
			for (; i<nodes.length; ++i) {
				nodes[i].hide();
			}
			if (o) {
				o.show();
			}
		}).trigger('change');
	}
});
