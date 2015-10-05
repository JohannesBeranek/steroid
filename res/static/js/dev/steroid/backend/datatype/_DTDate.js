define([
	"dojo/_base/declare",
	'steroid/backend/datatype/_DataType'
], function (declare, _DataType) {

	return declare([_DataType], {
		getWidth: function(){
			return 125;
		},
		_setValueAttr: function(value) {
			var me = this;

			var date;

			if (typeof value === "string") {
				date = new Date(value ? value.replace(/\-/g, '\/') : null); // need to convert to YYYY/MM/DD or firefox and IE won't recognize as valid date
			} else if (value instanceof Date) {
				date = value;
			} else {
				date = null;
			}

			if (date) {
				value = date.getFullYear() + '-' + ('0' + (date.getMonth() + 1)).slice(-2) + '-' + ('0' + date.getDate()).slice(-2);
			} else {
				value = null;
			}

			me.inherited(arguments, [value]);
		}
	});
});
