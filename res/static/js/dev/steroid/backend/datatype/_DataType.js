define([
	"dojo/_base/declare",
	"dijit/_Widget",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"steroid/backend/Localizor"
], function (declare, _Widget, i18nRC, Localizor) {
	return declare([_Widget], {

		classConfig: null,
		fieldConf: null,
		fieldName: null,
		width: null,
		backend: null,
		i18nExt: null,
		labelPrefix: null,
		localizor: null,

		getWidth: function () {
			return 100;
		},
		getLabel: function (skipModifier, keyModifier) {
			var me = this;

			if(!me.localizor){
				me.localizor = new Localizor({
					backend: me.backend
				});
			}

			var label = me.localizor.getFieldLabel(me.fieldConf, me.owningRecordClass, me.fieldName, me.i18nExt, skipModifier, keyModifier);

			if(skipModifier){
				return label;
			}

			return me.labelModifier(label);
		},
		labelModifier: function (label) {
			var me = this;

			if (me.labelPrefix) {
				label = me.labelPrefix + label;
			}

			label += me.getLabel(true, '_help_');

			return label;
		},
		destroy: function () {
			var me = this;

			delete me.backend;

			if(me.localizor){
				me.localizor.destroyRecursive();
				delete me.localizor;
			}

			me.inherited(arguments);
		}
	});
});