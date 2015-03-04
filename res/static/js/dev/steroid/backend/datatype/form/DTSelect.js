define([
	"dojo/_base/declare",
	"steroid/backend/datatype/_DTEnum",
	"steroid/backend/datatype/form/_DTFormFieldMixin",
	"dijit/form/Select",
	"dojo/dom-construct",
	"dojo/_base/lang",
	"dojo/dom-attr"
], function (declare, _DTEnum, _DTFormFieldMixin, Select, domConstruct, lang, domAttr) {

	return declare([Select, _DTFormFieldMixin, _DTEnum], {
		startup: function () {
			var me = this;

			this._loadingStore = true; // need to set this so formselectmixin doesn't set an empty value on startup and in addOption (which would break dirty check)
			me.inherited(arguments);

			if (me.fieldConf.values && me.fieldConf.values.length) {
				me.setOptions(me.fieldConf.values);
			}

			this._loadingStore = false;

			me.initComplete();
		},
		_setValueAttr: function(value){
			var me = this;
			
			if(value === null){
				value = '';
			}

			me.inherited(arguments, [value]);
		},
		setOptions: function (values) {
			var me = this;

			var options = [];

			if (me.fieldConf.nullable) {
				options.push({
					value: '',
					label: ' --- '
				});
			}

			for (var i = 0; i < values.length; i++) {
				options.push(me.createOption(values[i]));
			}

			if (options.length) {
				me.addOption(options);
			}
		},
		createOption: function (value) {
			var me = this;

			var label = me.getOptionLabel(value);

			var option = {
				value: value,
				label: label
			};

			return option;
		},
		getDirtyNess: function () {
			var me = this;

			var stval = (me.STValue && lang.isObject(me.STValue)) ? me.STValue.value : me.STValue;
			var orival = (me.originalValue && lang.isObject(me.originalValue)) ? me.originalValue.value : me.originalValue;

			var dirtyNess = (stval == orival ? 0 : 1);

			return dirtyNess;
		},
		destroy: function () {
			var me = this;

			if (me._beingDestroyed) {
				me.inherited(arguments);
				return;
			}

			var optLen = me.options.length;

			for (var i = 0; i < optLen; i++) {
				me.options[i].destroyRecursive();
			}

			delete me.options;
			delete me.lastOptionValue;
			delete me.current;

			me.inherited(arguments);
		}
	});
});
