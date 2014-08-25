define([
	"dojo/_base/declare",
	"steroid/backend/datatype/list/_DTListMixin",
	"steroid/backend/datatype/_DTBool"
], function (declare, _DTListMixin, _DTBool) {

	return declare([_DTListMixin, _DTBool], {
		listFormat:function (value) {
			var me = this;

			return value == null ? 0 : parseInt(value, 10);
		}
	});
});
