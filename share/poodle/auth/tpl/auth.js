/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	@import "poodle"
*/

function PoodleAuth()
{
	function process_login(pxhr)
	{
		switch (pxhr.xhr.status)
		{
		case 202:
			if ("application/json" == pxhr.xhr.getResponseHeader("Content-Type")) {
				var data = JSON.parse(pxhr.xhr.responseText);
				if (data) {
					if (302 == data.status) {
						self.location.href = data.location;
					} else if (data.form) {
						var p, f = data.form, ff, fff, i=0, n,
						 form = pxhr.form;
//						 name_prefix = 'auth['+intval(data.provider_id)+'][%s]';
						if (f.submit) {
							form.style.display="none";
							form = p = Poodle.$C("form",{method:"post"});
						} else {
							p = form.$Q(".auth-fields");
							p = p ? p[0].insertBefore(Poodle.$C("div"),p[0].firstChild) : form.$A("div");
							form.auth_action = form.action;
							form.auth_div = p;
							form.addClass(f["class"]||"auth-other").addClass("visible").on("mouseout",remove);
						}
						form.action = f.action;
						if (f.fields) {
							while (ff=f.fields[i++]) {
								if (ff.label) p.$A("label",{
									"for":ff.name,
									innerHTML:ff.label
								});
								n = p.$A("input",{
									id:ff.name,
									name:ff.name,//sprintf(name_prefix,ff.name),
									value:ff.value,
									type:ff.type?ff.type:"text"
								});
								if (!fff && "hidden"!=ff.type) fff=n;
							}
						}
//						n = p.$A("input",{name:'provider',value:data.provider_id,type:"hidden"});
						if (f.submit) {
							p.$A("button",{type:"submit",innerHTML:"Continue"});
							p.placeAfter(pxhr.form);
							p.submit();
						} else if (fff) fff.focus();
					}
					break;
				}
			} else {
				self.location.reload(true);
				break;
			}
		default: console.error(pxhr.xhr.responseText);
		}
	}

	function remove(e)
	{
		e.currentTarget.removeClass("visible").off("mouseout",remove);
	}

	function reset(e)
	{
		var f = e.target, c=f.className.replace(/.*( auth-[^ ]+)?.*$/,'$1');
		if (f.auth_action) f.action = f.auth_action;
		if (f.auth_div) f.auth_div.parentNode.removeChild(f.auth_div);
		if (f.auth_input) f.auth_input.focus();
		if (c) f.removeClass(c);
	}

	function submit(e)
	{
		var xhr = new PoodleXHR();
//		xhr.async = false;
		xhr.oncomplete = process_login;
		if (xhr.sendForm(e.target))
			e.stop();
	}

	function select_provider(e)
	{
		var i = this.provider_idx, f=this.auth_form, oid=f.auth_input, p;
		reset({target:f});
		if (f && oid && (0<i || 0===i)) {
			oid.value = PoodleAuth.providers[i][1];
			p=oid.value.indexOf("{username}");
			if (0<p) {
				var n = prompt("Fill in your "+this.title+" username:");
				if (n) {
					oid.value = oid.value.replace("{username}", n);
					f.trigger('submit'); // f.submit();
				} else {
					oid.focus();
					oid.setSelectionRange(p,p+10);
				}
			} else {
				f.submit();
			}
		}
		e.stop();
	}

	var i=-1, f=arguments, n, p=PoodleAuth.providers, pi;
	if (!f.length) f=Poodle.$Q("form.auth");
	while (f[++i]) {
//		n = f[i].$Q('input[name="auth_claimed_id"]',1);
		n = f[i].$Q('input[name="openid_identifier"]',1);
		if (n) {
			f[i].auth_input = n;
			n = f[i].$Q(".providers");
			if (n && n.length) {
				n[0].$A("div",{innerHTML:"or select provider:"});
				for (pi=0;pi<p.length;++pi) {
					n[0].$A("a",{
						className:p[pi][0],
						textContent:p[pi][0],
						title:p[pi][0],
						href:"#",
						onclick:select_provider,
						provider_idx:pi,
						auth_form:f[i]
					});
				}
			}
			f[i].on("reset",reset).on("submit",submit);
		}
	}
}

PoodleAuth.providers = [
	["I-Name",      "xri://{username}"],
	["Google",      "https://profiles.google.com/{username}"],
	["Yahoo",       "http://me.yahoo.com/"],
	["myOpenID",    "http://{username}.myopenid.com/"],
	["AOL",         "http://openid.aol.com/{username}"],
	["Flickr",      "http://flickr.com/{username}/"],
	["Technorati",  "http://technorati.com/people/technorati/{username}/"],
	["Wordpress",   "http://{username}.wordpress.com/"],
	["Blogger",     "http://{username}.blogspot.com/"],
	["Verisign",    "http://{username}.pip.verisignlabs.com/"],
	["Vidoop",      "http://{username}.myvidoop.com/"],
	["ClaimID",     "http://claimid.com/{username}"],
	["LiveJournal", "http://{username}.livejournal.com"],
	["MySpace",     "http://www.myspace.com/{username}"]
];

Poodle.onDOMReady(()=>PoodleAuth());
