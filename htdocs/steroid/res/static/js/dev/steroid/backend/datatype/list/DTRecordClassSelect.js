define([
	"dojo/_base/declare",
	"steroid/backend/datatype/list/DTSelect",
	"dojo/i18n!steroid/backend/nls/RecordClasses"
], function (declare, DTSelect, i18nRC) {

	return declare([DTSelect], {
		listFormat: function (value) {
			var me = this;

			var classConfig = me.backend.getClassConfigFromClassName(value);

			return (classConfig && classConfig.i18nExt && classConfig.i18nExt[value + '_name']) ? classConfig.i18nExt[value + '_name'] : (i18nRC[value + '_name'] || value);
		}
	});
});
