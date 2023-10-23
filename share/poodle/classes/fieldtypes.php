<?php
/*	Poodle WCMS, Copyright (c) MH X Solutions since 2010. All rights reserved.

	The contents of this file are subject to the terms of the
	Common Development and Distribution License, Version 1.0 only
	(the "License").  You may not use this file except in compliance
	with the License.

	Yes i know this is an anti-pattern.
	There is no better solution, as traits don't allow it for good reasons
*/

namespace Poodle;

interface FieldTypes
{

	const
		FIELD_TYPE_TEXT     = 1,
		FIELD_TYPE_CHECKBOX = 2,
		FIELD_TYPE_COLOR    = 3,
		FIELD_TYPE_DATE     = 4,
		FIELD_TYPE_DATETIME = 5,
		FIELD_TYPE_DATETIME_LOCAL = 6,
		FIELD_TYPE_EMAIL    = 7,
		FIELD_TYPE_FILE     = 8,
		FIELD_TYPE_MONTH    = 9,
		FIELD_TYPE_NUMBER   = 10,
		FIELD_TYPE_RADIO    = 11,
		FIELD_TYPE_RANGE    = 12,
		FIELD_TYPE_TEL      = 13,
		FIELD_TYPE_TIME     = 14,
		FIELD_TYPE_URL      = 15,
		FIELD_TYPE_WEEK     = 16,
		FIELD_TYPE_TEXTAREA = 17,
		FIELD_TYPE_HTMLAREA = 18,
		FIELD_TYPE_SELECT   = 19,
		FIELD_TYPE_TIMEZONE = 20,
		FIELD_TYPE_COUNTRY  = 21,
		FIELD_TYPE_COMBOBOX = 22, // <input list="*"/><datalist id="*"><option value=""/></datalist>
		FIELD_TYPE_CUSTOM   = 23;

	/*
	Field attributes:
		{"value":"mail-data"}
		{"get":"\\Poodle\\Resource\\Admin\\Basic::getResources"}
		{"options":[{"value":"male","label":"Male"},{"value":"female","label":"Female"}],"required":true}

	MySQL:
		ALTER TABLE config CHANGE COLUMN cfg_field_type cfg_field_type
		ENUM('TEXT','CHECKBOX','COLOR','DATE','DATETIME','DATETIME_LOCAL','EMAIL','FILE','MONTH','NUMBER','RADIO','RANGE','TEL','TIME','URL','WEEK','TEXTAREA','HTMLAREA','SELECT','TIMEZONE','COUNTRY','COMBOBOX','CUSTOM')
		NOT NULL DEFAULT 'TEXT'
	*/
}
