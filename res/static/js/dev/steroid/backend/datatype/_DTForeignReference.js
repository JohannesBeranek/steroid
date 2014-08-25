define([
	"dojo/_base/declare",
	"steroid/backend/datatype/_DTRecordAsTagMixin"
], function (declare, _DTRecordAsTagMixin) {

	return declare([_DTRecordAsTagMixin], {
		getWidth: function(){
			return 'auto';
		}
	});
});
