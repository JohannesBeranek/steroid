define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/DTBool"
], function (declare, DTBool) {

	return declare([DTBool], {
		hideField:true,

		isValid: function(){
			return true;
		}
	});
});
