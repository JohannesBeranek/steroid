define([
	"dojo/_base/declare",
	"steroid/backend/datatype/_DTAreaJoinForeignReference",
	"steroid/backend/datatype/list/_DTListMixin"
], function (declare, _DTAreaJoinForeignReference, _DTListMixin) {

	return declare([_DTListMixin, _DTAreaJoinForeignReference], {
		hideField: true
	});
});
