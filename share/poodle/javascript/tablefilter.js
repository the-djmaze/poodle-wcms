/**
	@import "poodle"

	<input class="tablefilter" data-table="table-id" type="search">
*/

var PoodleTableFilter = (K=>{

	K.onDOMReady(()=>{
		Poodle.$Q('input.tablefilter').forEach(n=>{PoodleTableFilter.init(n);});
	});

	return {
		onInput : function()
		{
			var v = this.value.toLowerCase(), h;
			if (v.length) {
				this.table.querySelectorAll('tbody tr').forEach(n => {
					h = !n.textContent.toLowerCase().includes(v);
					if (!h && this.filter_skip.length) {
						h = true;
						n.cells.forEach(c => {
							if (!this.filter_skip.includes(c.cellIndex)
							 && c.textContent.toLowerCase().includes(v)) {
								h = false;
							}
						});
					}
					n.hidden = h;
				});
			} else {
				this.table.querySelectorAll('tbody tr').forEach(n => {
					n.hidden = false;
				});
			}
		},

		init : function(n)
		{
			var id = n.attr('data-table'), t;
			if (id) {
				t = K.$Q('table#'+id, 1);
				if (t) {
					n.filter_skip = [];
					t.querySelectorAll('thead th[data-filter-no]').forEach(c => {
						n.filter_skip.push(c.cellIndex);
					});
					n.table = t;
					n.on('input', this.onInput);
				}
			}
		}
	};

})(Poodle);
