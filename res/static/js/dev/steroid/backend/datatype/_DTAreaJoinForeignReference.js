define([
	"dojo/_base/declare",
	"steroid/backend/datatype/_DataType",
	"dojo/i18n!steroid/backend/nls/RecordClasses"
], function (declare, _DataType, i18n) {

	return declare([_DataType], {
		getLabel:function () {
			var me = this;

			if (i18n[me.fieldConf.recordClass + '_name']) {
				return i18n[me.fieldConf.recordClass + '_name'];
			}

			return me.inherited(arguments);
		}
	});
});
