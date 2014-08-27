define([
	"dojo/_base/declare",
	"steroid/backend/dnd/InlineEditableRecord",
	"dojo/_base/array"
], function (declare, InlineEditableRecord, array) {

	return declare([InlineEditableRecord], {
		postMixInProperties: function () {
			var me = this;

			me.inherited(arguments);

			if (me.onlyTitleEditable) {
				for (var fieldName in me.ownFields) {
					if (array.indexOf(me.submitFieldsIfDirty, fieldName) < 0 && !me.ownClassConfig.titleFields[fieldName]) {
						delete me.ownFields[fieldName];
					}
				}
			}
		}
	});
});
