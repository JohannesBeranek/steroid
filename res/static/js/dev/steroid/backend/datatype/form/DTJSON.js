define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/DTText",
	"dojo/_base/json"
], function (declare, DTText, json) {

	return declare([DTText], {
		hideField: true,

		_setValueAttr: function (value) {
			var me = this;

			if (value && typeof value === 'object') {
				value = json.toJson(value);
			}

			me.inherited(arguments, [value]);
		}
	});
});