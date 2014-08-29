define([
	"dojo/_base/declare",
	"steroid/backend/datatype/_DataType",
	"dojo/i18n!steroid/backend/nls/RecordClasses"
], function (declare, _DataType) {

	return declare([_DataType], {
		getWidth: function(){
			return 400;
		}
	});
});
