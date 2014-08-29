define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/DTInlineEditableRecord"
], function (declare, DTInlineEditableRecord) {

	return declare([DTInlineEditableRecord], {

		getFieldsToHide: function () {
			var me = this;

			var fields = me.inherited(arguments);

			fields.push('template', 'forwardTo');

			return fields;
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			if (me.fieldConf.readOnly) {
				me.set('readOnly', true);
			}
		}
	});
});