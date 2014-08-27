define([
	"dojo/_base/declare",
	"steroid/backend/datatype/list/_DTRecordAsTagMixin",
	"dojo/_base/lang"
], function (declare, _DTRecordAsTagMixin, lang) {

	return declare([_DTRecordAsTagMixin], {
		listFormat: function (value) {
			var me = this;

			if (!value.length) {
				return '';
			}

			return '"' + value.join('", "') + '"';
		},
		isSortable: function () {
			return false;
		},
		getHiddenInList: function () {
			return !this.isTitleField;
		}
	});
});
