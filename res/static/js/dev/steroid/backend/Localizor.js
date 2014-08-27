define([
	"dojo/_base/declare",
	"dijit/_Widget",
	"dojo/i18n!steroid/backend/nls/RecordClasses"
], function (declare, _Widget, i18nRC) {
	return declare([_Widget], {
		backend: null,

		getFieldLabel: function (fieldConf, owningRecordClass, fieldName, i18nExt, skipModifier, keyModifier) {
			var me = this, label;

			if(!keyModifier){
				keyModifier = '';
			}

			if(fieldConf.addedByClass){
				var foreignClassConf = me.backend.getClassConfigFromClassName(fieldConf.addedByClass);

				var i18nForeign = foreignClassConf.i18nExt;

				if (i18nForeign && i18nForeign[owningRecordClass] && i18nForeign[owningRecordClass][fieldName + keyModifier]) {
					label = i18nForeign[owningRecordClass][fieldName + keyModifier];
				} else if (i18nForeign && i18nForeign[fieldConf.addedByClass] && i18nForeign[fieldConf.addedByClass][fieldName + keyModifier]){
					label = i18nForeign[fieldConf.addedByClass][fieldName + keyModifier];
				}
			}

			if (!label && i18nExt && i18nExt[owningRecordClass] && i18nExt[owningRecordClass][fieldName + keyModifier]) {
				label = i18nExt[owningRecordClass][fieldName + keyModifier];
			}

			if (!label && i18nRC[owningRecordClass] && i18nRC[owningRecordClass][fieldName + keyModifier]) {
				label = i18nRC[owningRecordClass][fieldName + keyModifier];
			}

			if (!label) {
				label = i18nRC.generic[fieldName + keyModifier];
			}

			if(keyModifier == ''){
				if (!label && fieldConf.selectableRecordClassConfig) { // foreign reference
					var classConfig = me.backend.getClassConfigFromClassName(fieldConf.selectableRecordClassConfig.recordClass);

					var i18n = classConfig.i18nExt || i18nRC;

					if (i18n[fieldConf.selectableRecordClassConfig.recordClass + '_name']) {
						label = i18n[fieldConf.selectableRecordClassConfig.recordClass + '_name'];
					}
				}

				if (!label && fieldConf.recordClass) { // record reference
					var classConfig = me.backend.getClassConfigFromClassName(fieldConf.recordClass);

					var i18n = classConfig.i18nExt || i18nRC;

					if (i18n[fieldConf.recordClass + '_name']) {
						label = i18n[fieldConf.recordClass + '_name'];
					}
				}
			}

			if (!label) {
				if(keyModifier != ''){
					return '';
				}

				label = fieldName;
			}

			return label;
		},
		destroy: function () {
			var me = this;

			delete me.backend;

			me.inherited(arguments);
		}
	});
});