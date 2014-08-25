define([
	"dojo/_base/declare",
	"steroid/backend/datatype/_DataType"
], function (declare, _DataType) {

	return declare([_DataType], {
		getWidth: function(){
			return 200;
		}
	});
});

