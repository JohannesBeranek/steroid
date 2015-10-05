define([
	"dojo/_base/declare",
	'steroid/backend/datatype/_DataType',
	"dojo/date/stamp"
], function (declare, _DataType, stamp) {

	return declare([_DataType], {
		getWidth: function(){
			return 125;
		},
		_setValueAttr: function(value) {
			var me = this;

			if ((value instanceof Date) && !(value.toString() === 'Invalid Date')) {
				value = 'T' + value.toTimeString().split(" ")[0];
			}

			me.inherited(arguments, [value]);
		}
	});
});
