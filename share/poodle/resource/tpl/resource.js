/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	@import "poodle"
	@import "poodle/forms"
	@import "poodle/media/wysiwyg"
*/

var Poodle_Resource = (K => {

var
// taken from class Poodle_DataType
input_types = [null,
	'text',     // FIELD_TYPE_TEXT
	'checkbox', // FIELD_TYPE_CHECKBOX
	'color',    // FIELD_TYPE_COLOR
	'date',     // FIELD_TYPE_DATE
	'datetime', // FIELD_TYPE_DATETIME
	'datetime-local', // FIELD_TYPE_DATETIME_LOCAL
	'email',    // FIELD_TYPE_EMAIL
	'file',     // FIELD_TYPE_FILE
	'month',    // FIELD_TYPE_MONTH
	'number',   // FIELD_TYPE_NUMBER
	'radio',    // FIELD_TYPE_RADIO
	'range',    // FIELD_TYPE_RANGE
	'tel',      // FIELD_TYPE_TEL
	'time',     // FIELD_TYPE_TIME
	'url',      // FIELD_TYPE_URL
	'week',     // FIELD_TYPE_WEEK
	null,       // FIELD_TYPE_TEXTAREA
	null,       // FIELD_TYPE_HTMLAREA
	null,       // FIELD_TYPE_SELECT
	null,       // FIELD_TYPE_TIMEZONE
	null,       // FIELD_TYPE_COUNTRY
	'text',     // FIELD_TYPE_COMBOBOX
	null        // FIELD_TYPE_CUSTOM
],
FIELD_TYPE_NO_DATA = 2,
FIELD_L10N   = 1;

K.onDOMReady(()=>{
	Poodle_Resource.init();
});

return {
	data: {
		allowed_methods: ["GET", "HEAD", "POST"],
		// resource
		id: 36,
		uri: '',
		parent_id: 0,
		type_id: 0,
		ctime: 0,
		ptime: 0,
		etime: 0,
		flags: 0,
		status: 0,
		creator_identity_id: 0,
		// resource data
		l10n_id: 0,
		mtime: 0,
		title: '',
		body: null,
		searchable: false,
		modifier_identity_id: 0,
		// Meta information
		metadata:[],
		groups_perms:[]
	},
	types: [],
	timezones: [],
	countries: [],
	ext2mime: [],
	form: null,
	def_bli: 0, // default bodylayout selectedIndex

	init: function()
	{
		var o = K.$('resource_bodylayout_id'), type=K.$('resource_type_id'), br=this;
		if (o) {
			br.def_bli = o.selectedIndex;
		}

		o = K.$('resource_data-l10n_id');
		if (o) {
			o.on('change',function(e){document.location.href="?l10n_id="+e.target.value;});
		}

		if (type) {
			br.form = type.form;
			type.on('change',function(){br.initType(type.value);}).trigger('change');
		}

		o = K.$Q('#resource-advanced > a',1);
		if (o) {
			o.on('click',function(){
				K.$('resource-advanced').toggleClass('expanded');
			});
		}
	},

	initType: function(id)
	{
		var i = 1, t = this.types, flags = t[0].flags, wcfg;
		this.generateTypeFields(t[0].fields, 1);
		if (0 < id) {
			for (; i<t.length; ++i) {
				if (t[i].id == id) {
					this.generateTypeFields(t[i].fields);
					flags |= t[i].flags;
					if (this.def_bli<1) {
						var bl = K.$('resource_bodylayout_id');
						if (bl) { bl.setSelectedByValue(t[i].bodylayout_id); }
					}
					wcfg = t[i].wysiwyg_cfg;
					break;
				}
			}
		}

		if (flags & FIELD_TYPE_NO_DATA) {
			K.$('resource_data-searchable').hide();
			K.$('resource_data-body').hide();
		} else {
			K.$('resource_data-searchable').show();
			var w = K.$('resource_data-body').show().$Q('textarea',1).data('wysiwyg',wcfg||'').wysiwyg;
			if (w) { w.setConfig(wcfg); }
//			var div=K.$('type-fields-content'), o;
//			o = div.$A('textarea',{id:'resource_body',name:'resource_body','class':'resizable wysiwyg',textContent:this.data.body});
//			new PoodleResizer(o);
		}

		K.HTML5Form();
		if (PoodleWYSIWYG) { PoodleWYSIWYG.init(); }
	},

	generateTypeFields: function(fields, clear)
	{
		var i=0, j, f, g=K.$('type-fields-general'), c=K.$('type-fields-content'), label, s, a, v, id, o, so,
			enctype="application/x-www-form-urlencoded", value, data = this.data;
		if (clear) {
			c.html("");
			g.html("");
		}
		for (;i<fields.length;++i) {
			s = 0;
			f = fields[i];
			id = (f.flags & FIELD_L10N) ? data.l10n_id : 0;
			label = (id ? c : g).$A('label');
			value = data.metadata ? data.metadata[f.name] : null;

			a = f.attributes || {};
			a.name = "resource_metadata["+id+"]["+f.name+"]";
			if (!defined(f.value)) f.value = value;

			if (2!=f.type && 11!=f.type) {
				label.$A('span',{textContent:f.label});
			} else if (value) {
				a.checked = "";
			}

			if (input_types[f.type]) {
				a.type = input_types[f.type];
				if (8 == f.type) { enctype = "multipart/form-data"; }
				else if (f.value) { a.value = f.value; }

				// Combobox
				if (22 == f.type) { a.list = (a.name+'-list').replace(/[^a-z0-9_]+/ig,'-'); }

				o = label.$A('input',a);
				if (2 == f.type || 11== f.type) {
					if (value) { o.attr("checked","").checked = true; }
					label.$A(label.$D().createTextNode(' '));
					label.$A('span',{textContent:f.label});
				}
				if (8 == f.type && f.value) {
					var m = f.value.match(/\.(png|svg|jpe?g|gif)$/);
					if (m) {
						label.$A('br');
						label.$A('img',{src:K.PATH+"media/"+f.value});
					} else {
						m = f.value.match(/\.([^.]+)$/)[1];
						if (this.ext2mime[m]) { m += " mime-"+this.ext2mime[m].replace('/',' '); }
						label.$A('a',{
							href:K.PATH+"media/"+f.value,
							textContent:f.value,
							"class":"mime-16 "+m
						});
					}
//					label.$A('input',{name:a.name,type:"checkbox",value:"delete"});
//					label.$A('span',{textContent:"delete"});
				}
				if (15 == f.type) { o.attr('placeholder','http://'); }
				if (22 == f.type) {
					// <input type='text' list='example'><datalist id='example'><option value=""/></datalist>
					if (a.options) {
						s = label.$A('datalist',{id:a.list});
						for (j=0;j<a.options.length;++j) {
							s.$A('option',{value:a.options[j].value});
						}
						s=0;
					}
				}
			}
			else switch (f.type)
			{
			case 18: // FIELD_TYPE_HTMLAREA
				a.className = (a.className||'')+' wysiwyg';
			case 17: // FIELD_TYPE_TEXTAREA
				if (f.value) a.textContent = f.value;
				label.$A('textarea',a);
				break;

			case 19: // FIELD_TYPE_SELECT
				if (defined(a.multiple)) { a.name += '[]'; }
				s = label.$A('select',a);
				break;

			case 20: // FIELD_TYPE_TIMEZONE
				s = label.$A('select',a);
				var tz = this.timezones, gv, og;
				for (v in tz) {
					if (is_string(tz[v])) {
						s[s.length] = new Option(tz[v], v, v===f.value, v===f.value);
					} else {
						for (gv in tz[v]) {
							if (!gv) og = s.$A('optgroup',{label:tz[v][gv]});
							else og.$A(new Option(tz[v][gv], gv, v===f.value, v===f.value));
						}
					}
				}
				s=0;
				break;

			case 21: // FIELD_TYPE_COUNTRY
				s = label.$A('select',a);
				a.options = this.countries;
				break;

			case 23: // FIELD_TYPE_CUSTOM
				if (a.callFunction) eval(a.callFunction+'(label, f, a);');
				break;
			}
			if (s && a.options) {
				var selected, cv = defined(a.multiple) ? f.value.split(',') : [f.value];
				for (j=0;j<a.options.length;++j) {
					o = a.options[j];
					v = String(o.value);
					selected = (0<=cv.indexOf(v));
//					s.$A('option',{value:o.value, textContent:o.label, className:o["class"]});
					so = new Option(o.label, v, selected, selected);
					if (o["class"]) { so.className = o["class"]; }
					s[s.length] = so;
				}
			}
		}
		this.form.enctype = enctype;
	}
};

})(Poodle);
