define([
	"dojo/_base/declare",
	"steroid/backend/mixin/_DTAreaJoinForeignReference",
	"steroid/backend/dnd/PageCanvas"
], function (declare, _DTAreaJoinForeignReference, PageCanvas) {

	return declare([_DTAreaJoinForeignReference, PageCanvas], {
		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.initComplete();
		}
	});
});