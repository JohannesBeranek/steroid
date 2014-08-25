define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/DTInt"
], function (declare, DTInt) {

	return declare([DTInt], {
		hideField:true,

		STSetValue:function (value) {
			var me = this;

			value = (typeof value === 'number' && isNaN(value)) || typeof value == 'undefined' || value === '' ? null : value;

			if (typeof me.originalValue == 'undefined' && value !== null) {
				me.originalValue = value;
			}

			return value;
		},
		isValid:function () {
			return true;
		}
	});
});
