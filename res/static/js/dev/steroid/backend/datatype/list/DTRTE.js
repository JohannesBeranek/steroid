define([
	"dojo/_base/declare",
	"steroid/backend/datatype/list/_DTListMixin",
	"steroid/backend/datatype/_DTText"
], function (declare, _DTListMixin, _DTText) {

	return declare([_DTListMixin, _DTText], {
		hideField: true
	});
});