define([
	"dojo/_base/declare",
	"steroid/backend/datatype/list/_DTListMixin",
	"steroid/backend/datatype/_DTEnum"
], function (declare, _DTListMixin, _DTEnum) {

	return declare([_DTListMixin, _DTEnum], {
		listFormat: function(value){
			var me = this;

			return me.getOptionLabel(value);
		}
	});
});
