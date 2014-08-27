define([
	"dojo/_base/declare",
	"steroid/backend/dnd/InlineEditableRecord",
	"dojo/_base/lang",
	"dojo/_base/array",
	"dojo/dom-style"
], function (declare, InlineEditableRecord, lang, array, domStyle) {

	return declare([InlineEditableRecord], {
		postMixInProperties: function () {
			var me = this;

			me.ownClassConfig = lang.clone(me.backend.getClassConfigFromClassName(me.fieldConf.recordClass));

			if (me.fieldConf.formFields) { // override from datatype
				for (var fieldName in me.ownClassConfig.formFields) {
					if (array.indexOf(me.fieldConf.formFields, fieldName) < 0) {
						delete me.ownClassConfig.formFields[fieldName];
					}
				}
			}

			me.submitName = me.fieldName;
			me.isNew = me.form.isNew;

			me.inherited(arguments);
		},
		_setValueAttr: function (value) {
			var me = this;

			if (me.record && me.record.primary && parseInt(me.record.primary, 10)) {
				me.isNew = false;
			} else {
				me.isNew = true;
			}

			me.inherited(arguments);
		},
		isHidden: function () {
			var me = this;

			return (me.hideField && !me.backend.debugMode && !me.form.isFilterPane && !(typeof me.fieldConf.showInForm !== 'undefined' && me.fieldConf.showInForm)) || (typeof me.fieldConf.showInForm !== 'undefined' && !me.fieldConf.showInForm);
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			if (me.isHidden()) {
				domStyle.set(me.domNode, 'display', 'none');
			}
		}
	});
});
