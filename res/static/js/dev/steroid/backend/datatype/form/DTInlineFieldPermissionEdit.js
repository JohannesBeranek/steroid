define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/DTInlineEditableRecord",
	"dojo/i18n!steroid/backend/nls/RecordClasses"
], function (declare, DTInlineEditableRecord, i18nRC) {
	return declare([DTInlineEditableRecord], {
		class: 'STInlineFieldPermissionEditor',

		getLabel: function(){
			var me = this;

			return i18nRC.RCFieldPermission.readOnlyFields;
		},
		getFieldPath: function(entry, i){
			var me = this;

			if(i == 'readOnlyFields'){
				return 'steroid/backend/datatype/form/DTFieldSelect';
			}

			return me.inherited(arguments);
		},
		getFieldConf: function(entry, i){
			var me = this;

			var fieldConf = me.inherited(arguments);

			if(i == 'readOnlyFields'){
				fieldConf.permissionRecordClass = me.fieldConf.permissionRecordClass;
			}

			return fieldConf;
		},
		setFieldDirty: function (setName, fieldDirtyNess, fieldName) {
			var me = this;

			return (fieldDirtyNess || setName) && fieldName !== 'primary';

//			return fieldDirtyNess || (fieldName == 'primary' && setName && me.ownFields[fieldName]._dt.get('value'));
		}
	});
});