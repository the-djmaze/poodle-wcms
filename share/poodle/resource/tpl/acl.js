/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	@import "poodle"
*/

(K => {

function set_activity(node)
{
	var i=0, f=node.fields, l=f.length, k = 'disabled';
	if (node.checked) {
		for (;i<l;++i) f[i].attr(k, null);
		k = 'enabled';
	} else {
		for (;i<l;++i) f[i].attr(k, k);
	}
	node.tr.className = k;
}

K.onDOMReady(()=>{
	var i=0, rows, l, node = K.$Q('#acl_groups tbody',1), nodes;
	if (node) {
		rows = node.rows;
		l = rows.length;
		for (;i<l;++i) {
			nodes = Array.from(rows[i].$T('input'));
			(node = nodes.shift()).fields = nodes;
			node.tr = rows[i];
			node.on('change', e => set_activity(e.target));
			set_activity(node);
		}
	}
});

})(Poodle);
