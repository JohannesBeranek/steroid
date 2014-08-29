define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/DTCheckBoxToCSV",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dijit/TitlePane",
	"dojo/dom-construct",
	"dojo/dom-style",
	"dojo/_base/lang"
], function (declare, DTCheckBoxToCSV, i18nRC, TitlePane, domConstruct, domStyle, lang) {

	return declare([DTCheckBoxToCSV], {
		recordConfig: null,
		nonSelectableFields: null,

		constructor: function(){
			var me = this;

			me.nonSelectableFields = ['primary', 'id', 'live', 'language', 'domainGroup'];
		},
		_setDisabledAttr: function(disabled){
			var me = this;

			domStyle.set(me.checkBoxContainer.domNode, 'display', disabled ? 'none' : 'block');
			domStyle.set(me.labelNode, 'display', disabled ? 'none' : 'block');

			me.inherited(arguments);
		},
		_setValueAttr: function(value){
			var me = this;

			me.checkBoxContainer.set('title', value ? (lang.isArray(value) ? value.join(me.separator) : value) : '');

			me.inherited(arguments);
		},
		getPossibleValues: function(){
			var me = this;

			var fields = [];

			me.recordConfig = me.backend.getClassConfigFromClassName(me.permissionRecordClass);

			for(var fieldName in me.recordConfig.formFields){
				if(me.isSelectableField(fieldName, me.recordConfig.formFields[fieldName])){
					fields.push({
						name: fieldName,
						label: me.getFieldLabel(fieldName)
					});
				}
			}

			return fields;
		},
		createCheckBoxContainer: function () {
			var me = this;

			me.checkBoxContainer = new TitlePane({
				class: 'STFieldSelector',
				open: false
			});

			me.checkBoxContainer.startup();

			domConstruct.place(me.checkBoxContainer.domNode, me.domNode, 'before');
		},
		getFieldLabel: function(fieldName){
			var me = this;

			if(me.recordConfig.formFields[fieldName].addedByRC){
				var i18n = me.backend.getClassConfigFromClassName(me.recordConfig.formFields[fieldName].addedByRC).i18nExt;

				if(i18n && i18n[me.recordConfig.className] && i18n[me.recordConfig.className][fieldName] ){
					return i18n[me.recordConfig.className][fieldName];
				}
			}

			if (me.recordConfig.i18nExt && me.recordConfig.i18nExt[me.permissionRecordClass] && me.recordConfig.i18nExt[me.permissionRecordClass][fieldName]){
				return me.recordConfig.i18nExt[me.permissionRecordClass][fieldName];
			}

			if (me.recordConfig.formFields[fieldName].selectableRecordClassConfig) { // foreign reference
				var classConfig = me.backend.getClassConfigFromClassName(me.recordConfig.formFields[fieldName].selectableRecordClassConfig.recordClass);

				var i18n = classConfig.i18nExt || i18nRC;

				if (i18n[me.recordConfig.formFields[fieldName].selectableRecordClassConfig.recordClass + '_name']) {
					return i18n[me.recordConfig.formFields[fieldName].selectableRecordClassConfig.recordClass + '_name'];
				}
			}

			if (me.recordConfig.formFields[fieldName].recordClass) { // record reference
				var classConfig = me.backend.getClassConfigFromClassName(me.recordConfig.formFields[fieldName].recordClass);

				var i18n = classConfig.i18nExt || i18nRC;

				if (i18n[me.recordConfig.formFields[fieldName].recordClass + '_name']) {
					return i18n[me.recordConfig.formFields[fieldName].recordClass + '_name'];
				}
			}

			if(i18nRC[me.permissionRecordClass] && i18nRC[me.permissionRecordClass][fieldName]){
				return i18nRC[me.permissionRecordClass][fieldName];
			}

			if(i18nRC.generic[fieldName]){
				return i18nRC.generic[fieldName];
			}

			return fieldName;
		},
		isSelectableField: function(fieldName, fieldConf){
			var me = this;

			return me.nonSelectableFields.indexOf(fieldName) < 0;
		}
	});
});