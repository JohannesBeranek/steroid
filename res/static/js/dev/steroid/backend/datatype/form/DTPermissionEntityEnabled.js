define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/DTBool",
	"dojo/i18n!steroid/backend/nls/RecordClasses"
], function (declare, DTBool, i18nRC) {

	return declare([DTBool], {
		label: null,

		getLabel: function () {
			var me = this;

			return me.fieldConf.label;
		},
		setSubmitName: function (setName) {
			this.inherited(arguments, [false]);
		}
	});
});
