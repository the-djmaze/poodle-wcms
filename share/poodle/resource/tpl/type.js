/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	@import "poodle"
	@import "poodle/forms"
*/

(K => {

var editor,
types = [
	/**
	 * "options" is an array of {value:'',label:''} objects
	 * When flag & 2 (call function) is set, "get" sets all attribs.
	 */
	{id: 1,name:"TEXT",attribs:["list","maxlength","pattern","required"]},
	{id: 2,name:"CHECKBOX",attribs:["required","value"]},
	{id: 3,name:"COLOR",attribs:[]},
	{id: 4,name:"DATE",attribs:["max","min","required"/*,"step"*/]},
	{id: 5,name:"DATETIME",attribs:["max","min","required"/*,"step"*/]},
	{id: 6,name:"DATETIME_LOCAL",attribs:["max","min","required"/*,"step"*/]},
	{id: 7,name:"EMAIL",attribs:["list","maxlength","pattern","required"]},
	{id: 8,name:"FILE",attribs:["required"]},
	{id: 9,name:"MONTH",attribs:["max","min","required"/*,"step"*/]},
	{id:10,name:"NUMBER",attribs:["max","min","required","step"]},
	{id:11,name:"RADIO",attribs:["required","value"]},
	{id:12,name:"RANGE",attribs:["max","min","step"]},
	{id:13,name:"TEL",attribs:["list","maxlength","pattern","required"]},
	{id:14,name:"TIME",attribs:["max","min","required"/*,"step"*/]},
	{id:15,name:"URL",attribs:["list","maxlength","pattern","required"]},
	{id:16,name:"WEEK",attribs:["max","min","required"/*,"step"*/]},
	{id:17,name:"TEXTAREA",attribs:["maxlength","required"]},
	{id:18,name:"HTMLAREA",attribs:[]}, // uses WYSIWYG
	{id:19,name:"SELECT",attribs:["options"]},
	{id:20,name:"TIMEZONE",attribs:[]}, // uses Poodle_Resource.timezones
	{id:21,name:"COUNTRY",attribs:[]},  // uses Poodle_Resource.countries
	{id:22,name:"COMBOBOX",attribs:["options","maxlength","pattern","required"]},
	{id:23,name:"CUSTOM",attribs:["callFunction"]} // callFunction generates field in JavaScript
];

function editTypeField(data)
{
	if (data) {
		var w_id="field_type_window";
		if (!editor) {
			editor = document.$B().$A([
			"div",{id:w_id, "class":"windowbg", hidden:''},
				["div",{"class":"window"},
					["div", {"class":"header"},
						["span",{ref:"t"}],
						["a", {"class":"close", innerHTML:"x", title:_("Close"), onclick:function(){K.$(w_id).hide();}}]
					],
					["form",{ref:"f",action:"?field",method:"post"},
						["div", {"class":"body",style:{height:"auto"}},
							["div", {style:{cssFloat:"left"}},
								["label",null,
									["span",null,_("type")],
									["select",{ref:"rtf_type",name:"type"},
										["option",{value:1},"TEXT"],
										["option",{value:2},"CHECKBOX"],
										["option",{value:3},"COLOR"],
										["option",{value:4},"DATE"],
										["option",{value:5},"DATETIME"],
										["option",{value:6},"DATETIME_LOCAL"],
										["option",{value:7},"EMAIL"],
										["option",{value:8},"FILE"],
										["option",{value:9},"MONTH"],
										["option",{value:10},"NUMBER"],
										["option",{value:11},"RADIO"],
										["option",{value:12},"RANGE"],
										["option",{value:13},"TEL"],
										["option",{value:14},"TIME"],
										["option",{value:15},"URL"],
										["option",{value:16},"WEEK"],
										["option",{value:17},"TEXTAREA"],
										["option",{value:18},"HTMLAREA"],
										["option",{value:19},"SELECT"],
										["option",{value:20},"TIMEZONE"],
										["option",{value:21},"COUNTRY"],
										["option",{value:22},"COMBOBOX"],
										["option",{value:23},"CUSTOM"]
									]
								],
								["label",null,
									["span",null,_("name")],
									["input",{ref:"rtf_new_name",name:"new_name",type:"text",maxlength:64,readonly:true}],
									["input",{ref:"rtf_name",name:"name",type:"hidden"}]
								],
								["label",null,
									["span",null,_("label")],
									["input",{ref:"rtf_label",name:"label",type:"text",maxlength:64}]
								],
								["label",null,
									["span",null,_("sortorder")],
									["input",{ref:"rtf_sortorder",name:"sortorder",type:"number",min:0,max:127}]
								],
								["label",null,
									["input",{ref:"rtf_flag_l10n",name:"flags[]",type:"checkbox",value:1}],
									" ",["span",null,_("language specific")]
								],
								["label",null,
									["input",{ref:"rtf_flag_exec",name:"flags[]",type:"checkbox",value:2}],
									" ",["span",null,_("execute function")]
								],
								["label",{style:{paddingLeft:"40px"}},
									["span",null,_("PHP get field attributes function")],
									["input",{ref:"rtf_attributes_get",name:"rtf_attributes[get]",type:"text"}]
								],
								["label",{style:{paddingLeft:"40px"}},
									["span",null,_("PHP set field value function")],
									["input",{ref:"rtf_attributes_set",name:"rtf_attributes[set]",type:"text"}]
								]
							],
							["div", {ref:"rtf_attribs",style:{cssFloat:"right"}}
							],
							["div", {style:{clear:"both"}},
								["button", {type:"submit", innerHTML:_("Save"), onclick:function(){console.log("save");}}],
								" ",
								["button", {type:"reset", innerHTML:_("Reset")}]
							]
				]	]	]
			]);
			K.HTML5Form(editor.f);
			editor.rtf_type.on("change",function(){
				var ft = types[this.selectedIndex], fa=editor.rtf_attribs, i=0, a=editor.data.attributes;
				fa.html("");
				for (;i<ft.attribs.length;++i) {
					var t = ft.attribs[i];
					if ("required"==t) {
						fa.$A(["label",null,
							["input",{name:"rtf_attributes[required]",type:"checkbox",checked:a[t]}],
							" ",["span",null,_("required")]
						]);
					} else if (-1!=[4,5,6,9,16].indexOf(ft.id) && ("max"==t || "min"==t)) {
						fa.$A(["label",null,
							["span",null,_(t)],
							["input",{name:"rtf_attributes["+t+"]",type:ft.name.toLowerCase().replace("_","-"),value:a[t]}]
						]);
					} else if ("max"==t || "min"==t || "step"==t) {
						fa.$A(["label",null,
							["span",null,_(t)],
							["input",{name:"rtf_attributes["+t+"]",type:"number",value:a[t]}]
						]);
					} else if ("maxlength"==t) {
						fa.$A(["label",null,
							["span",null,_(t)],
							["input",{name:"rtf_attributes["+t+"]",type:"number",min:0,value:a[t]}]
						]);
					} else {
						// pattern, value, custom.callFunction
						fa.$A(["label",null,
							["span",null,_(t)],
							["input",{name:"rtf_attributes["+t+"]",type:"text",value:a[t]}]
						]);
					}
/*
		data.attributes.list
		data.attributes.options = [{value:'',label:''}]
		// CUSTOM
		data.attributes.callFunction
"list",
id:19,name:"SELECT",attribs:["options"]
*/
				}
				fa.$A(["label",null,
					["span",null,_('title')],
					["input",{name:"rtf_attributes[title]",type:"text",value:a.title}]
				]);
				K.HTML5Form(editor.f);
			});
		}
		editor.data = data;
		editor.rtf_type.selectedIndex = data.type-1;
		editor.rtf_type.trigger("change");
		editor.rtf_name.initValue(data.name);
		editor.rtf_new_name.initValue(data.name);
		editor.rtf_label.initValue(data.label);
		editor.rtf_sortorder.initValue(data.sortorder);
		editor.rtf_flag_l10n.initValue(data.flags & 1);
		editor.rtf_flag_exec.initValue(data.flags & 2);
		editor.rtf_attributes_get.initValue(data.attributes['get']||'');
		editor.rtf_attributes_set.initValue(data.attributes['set']||'');
		editor.show();
	}
}

K.onDOMReady(()=>{
	var o = K.$Q('#resource-type-fields tbody',1);
	if (o) {
		o.on('click',function(e){
			var tr = e.target.getParentByTagName('tr',1);
			if (tr) { editTypeField(JSON.parse(tr.attr('data-typefield'))); }
		});
	}
	o = K.$('add-resource-type-field');
	if (o) {
		o.on('click',function(){
			editTypeField({name:"",label:"",type:1,flags:0,sortorder:0,attributes:[]});
		});
	}
});

})(Poodle);
