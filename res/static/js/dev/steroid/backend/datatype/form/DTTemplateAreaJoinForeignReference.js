define([
	"dojo/_base/declare",
	"steroid/backend/mixin/_DTAreaJoinForeignReference",
	"steroid/backend/dnd/TemplateCanvas"
], function (declare, _DTAreaJoinForeignReference, TemplateCanvas) {

	return declare([_DTAreaJoinForeignReference, TemplateCanvas], {
		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.initComplete();
		}
	});
});