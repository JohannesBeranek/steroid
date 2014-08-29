define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/DTRecordSelector",
	"dojo/_base/lang"
], function (declare, DTRecordSelector, lang) {

	return declare([DTRecordSelector], {
		hideField: true,

		isValid: function () {
			return true;
		},
		_setValueAttr: function (value) {
			var me = this;

			if (!me.form.isFilterPane && (!value || value.primary == 0)) {
				value = me.backend.config.User.values;
			}

			me.inherited(arguments, [value]);
		}
	});
});
