define([
	"dojo/_base/declare",
	"steroid/backend/datatype/_DataType",
	"dojo/i18n!steroid/backend/nls/RecordClasses"
], function (declare, _DataType, i18n) {

	return declare([_DataType], {
		getConstraints: function () {
			var me = this;

			return me.fieldConf.constraints;
		},
		getFormValue: function (record) {
			return record && record.primary ? record.primary : null;
		},
		getDisplayValue: function (value, titleFields) {
			var me = this;

			if (!value) {
				return null;
			}

			var classConfig = me.backend.getClassConfigFromClassName(me.fieldConf.recordClass);

			var titles = me.getRecordDisplayValue(value, titleFields || classConfig.titleFields);

			return titles.join(' ');
		},
		getRecordDisplayValue: function (record, titleFields) {
			var me = this;

			var vals = [];

			if (!record) {
				return vals;
			}

			if (typeof record._title != 'undefined') {
				return [ record.title ];
			}

			for (i in titleFields) {
				if (typeof titleFields[i] == 'object' || typeof titleFields[i] == 'array') {
					vals.push(me.getRecordDisplayValue(record[i], titleFields[i]));
				} else {
					vals.push(record[titleFields[i]]);
				}
			}

			return vals;
		}
	});
});
