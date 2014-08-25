define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/DTSelect",
	"dojo/i18n!steroid/backend/nls/RecordClasses"
], function (declare, DTSelect, i18nRC) {

	return declare([DTSelect], {
		values: null,

		constructor: function () {
			this.values = [];
		},
		getOptionLabel: function (value) {
			var me = this, classConfig = me.backend.getClassConfigFromClassName(value);

			return (classConfig && classConfig.i18nExt && classConfig.i18nExt[value + '_name']) ? classConfig.i18nExt[value + '_name'] : (i18nRC[value + '_name'] || value);
		}
	});
});
