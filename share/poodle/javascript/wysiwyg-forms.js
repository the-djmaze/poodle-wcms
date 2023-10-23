/*	Poodle WCMS, Copyright (c) MH X Solutions since 2008. All rights reserved.

	@import "poodle"
	@import "poodle/wysiwyg"
*/
if (window.PoodleWYSIWYG) {
(()=>{

var W=PoodleWYSIWYG, p=W.prototype, tb=W.toolbarButtons;

p.addForm = function(e,btn)
{
	var n = btn.wysiwyg.getNode();
	if (!n || !n.getParentByTagName('form',1)) {
		btn.wysiwyg.execCommand('inserthtml','<form><br/></form>');
	}
};

function insertFormHTML(btn, html)
{
	var n = btn.wysiwyg.getNode();
	if (n && n.getParentByTagName('form',1)) {
		btn.wysiwyg.execCommand('inserthtml',html);
	}
}

p.addLabel    = function(e,btn) { insertFormHTML(btn,'<label>label</label>'); };
p.addInput    = function(e,btn) { insertFormHTML(btn,'<input type="text"/>'); };
p.addButton   = function(e,btn) { insertFormHTML(btn,'<button>button</button>'); };
p.addSelect   = function(e,btn) { insertFormHTML(btn,'<select></select>'); };
p.addTextarea = function(e,btn) { insertFormHTML(btn,'<textarea></textarea>'); };
p.addFieldset = function(e,btn) { insertFormHTML(btn,'<fieldset><legend>fieldset</legend><br/></fieldset>'); };

// command, display name, value, title/description, class, prompt/function, param2
tb.push([null]);
tb.push([null, 'form', PoodleWYSIWYG.VALUE_FUNCTION, 'Insert form', 'form', 'addForm']);
tb.push([null, 'label', PoodleWYSIWYG.VALUE_FUNCTION, 'Insert form label', 'label', 'addLabel']);
tb.push([null, 'input', PoodleWYSIWYG.VALUE_FUNCTION, 'Insert form field', 'input', 'addInput']);
tb.push([null, 'button', PoodleWYSIWYG.VALUE_FUNCTION, 'Insert button', 'button', 'addButton']);
tb.push([null, 'select', PoodleWYSIWYG.VALUE_FUNCTION, 'Insert select', 'select', 'addSelect']);
tb.push([null, 'textarea', PoodleWYSIWYG.VALUE_FUNCTION, 'Insert textarea', 'textarea', 'addTextarea']);
tb.push([null, 'fieldset', PoodleWYSIWYG.VALUE_FUNCTION, 'Insert fieldset', 'fieldset', 'addFieldset']);

})();}
