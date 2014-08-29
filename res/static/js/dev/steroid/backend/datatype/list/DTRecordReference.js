define([
	"dojo/_base/declare",
	"dojo/_base/lang",
	"steroid/backend/datatype/list/_DTRecordAsTagMixin",
	"steroid/backend/datatype/_DTRecordReference"
], function (declare, lang, _DTRecordAsTagMixin, _DTRecordReference) {

	return declare([_DTRecordAsTagMixin, _DTRecordReference], {
		listFormat: function (value) {
			var me = this, vals;

			if (!value) {
				return null;
			}

			if (lang.isArray(value)) {
				vals = [];

				for (var i = 0, ilen = value.length; i < ilen; i++) {
					vals.push(me.listFormat(value[i]));
				}
			} else {
				if (typeof value._title != 'undefined') {
					return value._title;
				}

				var classConfig = me.backend.getClassConfigFromClassName(me.fieldConf.recordClass);

				vals = me.getRecordDisplayValue(value, classConfig.titleFields);
			}

			return vals.join(' | ');
		}
	});
});