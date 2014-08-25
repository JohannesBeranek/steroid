define([
	"dojo/_base/declare",
	"steroid/backend/mixin/_SubFormMixin",
	"dijit/layout/ContentPane"
], function (declare, _SubFormMixin, ContentPane) {

	return declare([ContentPane, _SubFormMixin], {

		submitName: null,

		updateSubmitName: function (submitName) {
			var me = this;

			me.submitName = submitName;

			for (var fieldName in me.ownFields) {
				me.ownFields[fieldName]._dt.updateSubmitName(me.submitName + '[' + fieldName + ']');
			}
		}
	});
});
