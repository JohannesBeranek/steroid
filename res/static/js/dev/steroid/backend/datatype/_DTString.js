define([

	"dojo/_base/declare",
	"steroid/backend/datatype/_DataType",
	"dojo/i18n!steroid/backend/nls/RecordClasses"
], function (declare, _DataType) {

	return declare([_DataType], {
		getWidth: function(){
			var me = this;

			return me.fieldConf.maxLen ? Math.max(Math.min((me.fieldConf.maxLen)*10, 200), 50) : 400;
		}
	});
});

