define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/DTInt",
	"dojo/_base/lang"
], function (declare, DTInt, lang) {

	return declare([DTInt], {
		hideField:true,

		_setValueAttr: function(value) {
			var me = this;

			if (value && lang.isObject(value)) {
				value = value.primary;
			}

			me.inherited(arguments, [value]);
		},
		isValid: function() {
			return true;
		}
	});
});
