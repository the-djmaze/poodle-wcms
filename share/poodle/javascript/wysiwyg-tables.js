/*	Poodle WCMS, Copyright (c) MH X Solutions since 2008. All rights reserved.

	@import "poodle"
	@import "poodle/wysiwyg"
*/
if (window.PoodleWYSIWYG) {
(()=>{

var K=Poodle, W=PoodleWYSIWYG, p=W.prototype, tb=W.toolbarButtons;

// MOO.WYSIWYG.choose_widget
p.openTable = function(e,btn)
{

	var w_id='wysiwyg_table_window', pop = K.$(w_id);
	if (!pop)
	{
		pop = K.$B().$A([
		'div',{id:w_id, 'class':'windowbg', hidden:''},
			['div',{'class':'vertical'},
				['div',{'class':'window',style:{width:'400px'}},
					['div', {'class':'header'},
						btn.title,
						['span',{ref:'t'}],
						['a', {'class':'close', innerHTML:'⨯', title:_('Close'), onclick:function(){pop.hide();}}]
					],
					["form",{ref:"f"},
						['div', {'class':'body'},
							["label",null,
								["span",null,_("Columns")],
								["input",{type:"number",min:1,value:2, ref:"cols"}]
							],
							["label",null,
								["span",null,_("Head rows")],
								["input",{type:"number",min:0,value:1, ref:"thead"}]
							],
							["label",null,
								["span",null,_("Body rows")],
								["input",{type:"number",min:1,value:4, ref:"tbody"}]
							],
							["label",null,
								["span",null,_("Footer rows")],
								["input",{type:"number",min:0,value:0, ref:"tfoot"}]
							],
							["button", {type:"button", onclick:function(){save();}}, _("Apply")],
							" ",
							["button", {type:"reset"}, _("Reset")]
						]
					]
				]
			]
		]);

		K.HTML5Form(pop.f);
	}

	function addRows(r,t)
	{
		var v='', cols='', i=Math.max(1,pop.cols.value);
		for (;i>0; --i) { cols += '<'+t+'></'+t+'>'; }
		for (i=0; i<r; ++i) { v += '\t<tr>'+cols+'</tr>\n'; }
		return v;
	}

	function save()
	{
		pop.hide();
		var w = btn.wysiwyg, v='', c;
		if (w) {

			if ((c = pop.thead.value) > 0) {
				v += '<thead>\n';
				v += addRows(c,'th');
				v += '</thead>\n';
			}

			v += '<tbody>\n';
			v += addRows(Math.max(1,pop.tbody.value),'td');
			v += '</tbody>\n';

			if ((c = pop.tfoot.value) > 0) {
				v += '<tfoot>\n';
				v += addRows(c,'td');
				v += '</tfoot>\n';
			}

			w.execCommand('inserthtml','<table style="width:100%">\n'+v+'</table>');
		}
	}

	pop.show();
};

function getNode(btn,tag)
{
	var n = btn.wysiwyg.getNode();
	return n ? n.getParentByTagName(tag,1) : null;
}

p.addTableRowAbove = function(e,btn)
{
	var n = getNode(btn,'tr');
	if (n) {
		var i=0, tr = K.$C('tr').placeBefore(n);
		for (;i<n.cells.length;++i) { tr.$A('td',{innerHTML:'<br>'}); }
	}
};

p.addTableRowBelow = function(e,btn)
{
	var n = getNode(btn,'tr');
	if (n) {
		var i=0, tr = K.$C('tr').placeAfter(n);
		for (;i<n.cells.length;++i) { tr.$A('td',{innerHTML:'<br>'}); }
	}
};

p.delTableRow = function(e,btn)
{
	var n = getNode(btn,'tr');
	if (n) { n.remove(); }
};

p.addTableCellBefore = function(e,btn)
{
	var n = getNode(btn,'td');
	if (n) { K.$C('td').placeBefore(n); }
	else {
		n = getNode(btn,'th');
		if (n) { K.$C('th').placeBefore(n); }
	}
};

p.addTableCellAfter = function(e,btn)
{
	var n = getNode(btn,'td');
	if (n) { K.$C('td').placeAfter(n); }
	else {
		n = getNode(btn,'th');
		if (n) { K.$C('th').placeAfter(n); }
	}
};

p.delTableCell = function(e,btn)
{
	var n = getNode(btn,'td');
	if (n) { n.remove(); }
};

// command, display name, value, title/description, class, prompt/function, param2
tb.push([null]);
tb.push([null, 'table', PoodleWYSIWYG.VALUE_FUNCTION, 'Insert table', 'insert-table', 'openTable']);
tb.push(["div"]);
tb.push([null, 'tr', PoodleWYSIWYG.VALUE_FUNCTION, 'Insert row above', 'insert-tr-above', 'addTableRowAbove']);
tb.push([null, 'tr', PoodleWYSIWYG.VALUE_FUNCTION, 'Insert row below', 'insert-tr-below', 'addTableRowBelow']);
tb.push([null, 'tr', PoodleWYSIWYG.VALUE_FUNCTION, 'Delete row', 'delete-tr', 'delTableRow']);
tb.push(["div"]);
tb.push([null, 'td', PoodleWYSIWYG.VALUE_FUNCTION, 'Insert cell before', 'insert-td-before', 'addTableCellBefore']);
tb.push([null, 'td', PoodleWYSIWYG.VALUE_FUNCTION, 'Insert cell after', 'insert-td-after', 'addTableCellAfter']);
tb.push([null, 'td', PoodleWYSIWYG.VALUE_FUNCTION, 'Delete cell', 'delete-td', 'delTableCell']);

})();}
