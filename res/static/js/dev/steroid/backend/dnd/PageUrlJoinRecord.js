define([
	"dojo/_base/declare",
	"steroid/backend/dnd/JoinRecord",
	"dojo/i18n!steroid/backend/nls/RecordClasses"
], function (declare, JoinRecord, i18nRC) {

	return declare([JoinRecord], {
		style: 'width: 100%;clear:both;',

		_setValueAttr: function (value) {
			var me = this;

			if (value && value.url) {
				value.url._title = value.url._title + ' (' + (value.url.returnCode == 200 ? i18nRC.RCUrl.primary : i18nRC.RCUrl.secondary) + ')';
			}

			me.inherited(arguments, [value]);
		}
	});
});
