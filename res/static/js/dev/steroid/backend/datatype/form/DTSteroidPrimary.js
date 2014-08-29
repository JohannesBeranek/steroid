define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/DTInt"
], function (declare, DTInt) {

	return declare([DTInt], {
		hideField: true,

		isValid:function () {
			return true;
		}
	});
});
