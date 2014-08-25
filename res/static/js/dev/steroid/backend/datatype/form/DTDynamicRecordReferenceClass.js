define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/DTString"
], function (declare, DTString) {

	return declare([DTString], {

		staticValue: null,

		_setValueAttr: function (value) {
			var me = this;

			value = me.staticValue;

			me.inherited(arguments, [value]);
		}
	});
});
