define([
	"dojo/_base/declare",
	"steroid/backend/datatype/list/_DTListMixin",
	"steroid/backend/datatype/_DTFile",
	"dojo/_base/lang"
], function (declare, _DTListMixin, _DTFile, lang) {

	return declare([_DTListMixin, _DTFile], {
		listFormat: function(value){
			var me = this;

			if(value && lang.isObject(value) && value.cached){
				value = '<img src="' + value.cached + '" class="STFileName"/>';
			}

			return value;
		}
	});
});