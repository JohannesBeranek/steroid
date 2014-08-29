define([

	"dojo/_base/declare",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dojo/_base/lang",
	"steroid/backend/datatype/_DataType"
], function (declare, i18n, lang, _DataType) {
	return declare([_DataType], {
		hideField: false,

		isSortable: function () {
			return true;
		},
		getStructure: function () {
			var me = this;

			me.isTitleField = false;

			for (i in me.classConfig.titleFields) {
				if (i == me.fieldName || me.classConfig.titleFields[i] == me.fieldName) {
					me.isTitleField = true;
					break;
				}
			}

			var classConf = me.backend.getClassConfigFromClassName(me.owningRecordClass);

			if (classConf) {
				me.i18nExt = classConf.i18nExt;
			}

			var hidden = me.fieldName === 'primary' || me.hideField || (me.isTitleField && !(me.fieldConf.recordClass && me.fieldConf.recordClass == 'RCFile'));

			if (typeof me.fieldConf.showInList !== 'undefined' && me.fieldConf.showInList) {
				hidden = false;
			}

			var struct = {
				label: me.getLabel(true),
				field: me.fieldName,
				sortable: me.isSortable(),
				width: typeof me.fieldConf.listWidth != 'undefined' ? me.fieldConf.listWidth : me.getWidth(),
				hidden: hidden,
				unhidable: false,
				isTitleField: me.isTitleField
			};

			if (me.listFormat) {
				struct.formatter = lang.hitch(me, 'listFormat');
			}

			return struct;
		}
	});
});