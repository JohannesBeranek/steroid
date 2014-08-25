define([
	"dojo/_base/declare",
	"steroid/backend/datatype/_DataType",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"steroid/backend/Localizor"
], function (declare, _DataType, i18nRC, Localizor) {

	return declare([_DataType], {
		getOptionLabel: function (value) {
			var me = this;

			var loc = new Localizor({
				backend: me.backend
			});

			var labels = loc.getFieldLabel(me.fieldConf, me.owningRecordClass, me.fieldName, me.i18nExt, false, '_values');

			return labels[value];
		}
	});
});