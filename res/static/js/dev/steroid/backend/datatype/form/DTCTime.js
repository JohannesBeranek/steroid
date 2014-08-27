define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/_DTFormFieldMixin",
	"steroid/backend/datatype/_DTDateTime",
	"dijit/form/MappedTextBox"
], function (declare, _DTFormFieldMixin, _DTDateTime, MappedTextBox) {

	return declare([MappedTextBox, _DTFormFieldMixin, _DTDateTime], {
		hideField: true,

		isValid: function () {
			return true;
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.initComplete();
		}
	});
});