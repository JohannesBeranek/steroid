define([
	"dojo/_base/declare",
	"steroid/backend/datatype/list/DTText",
], function (declare, DTText) {

	return declare([DTText], {
		getWidth: function () {
			return 100;
		}
	});
});