define([
	"dojo/_base/declare",
	"dijit/form/MappedTextBox",
	"steroid/backend/datatype/form/_DTFormFieldMixin"
], function (declare, MappedTextBox, _DTFormFieldMixin) {

	return declare([MappedTextBox, _DTFormFieldMixin], {
		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.initComplete();
		}
	});
});
