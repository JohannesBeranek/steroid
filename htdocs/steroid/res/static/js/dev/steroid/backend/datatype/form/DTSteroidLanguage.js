define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/DTRecordSelector",
	"dojo/_base/lang"
], function (declare, DTRecordSelector, lang) {

	return declare([DTRecordSelector], {

		hideField: true,

		isValid: function () {
			return true;
		}
	});
});
