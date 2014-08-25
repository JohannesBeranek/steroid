define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/DTMenuItemForeignReference"
], function (declare, DTMenuItemForeignReference) {

	return declare([DTMenuItemForeignReference], {
		isSubItem: true // FIXME: remove
	});
});
