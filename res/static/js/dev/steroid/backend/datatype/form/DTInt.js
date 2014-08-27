define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/_DTFormFieldMixin",
	"steroid/backend/datatype/_DTInt",
	"dijit/form/NumberTextBox",
	"dojo/i18n!steroid/backend/nls/FieldErrors",
	"dojo/string"
], function (declare, _DTFormFieldMixin, DTInt, NumberTextBox, i18nErr, string) {

	return declare([NumberTextBox, _DTFormFieldMixin, DTInt], {
		postMixInProperties: function () {
			var me = this;

			me.set('constraints', me.getConstraints());

			me.inherited(arguments);
		},
		_setValueAttr: function (value) {
			var me = this;

			value = parseInt(value, 10);

			if (isNaN(value)) {
				value = null;
			}

			me.inherited(arguments, [value]);
		},
		_getValueAttr: function () {
			var me = this;

			var value = me.inherited(arguments);

			if (isNaN(value)) {
				value = null;
			}

			return value;
		},
		generateErrorMessage: function () {
			var me = this;

			var v = me.get('value');

			if (isNaN(v)) {
				return i18nErr.numeric.isNaN;
			}

			if (v < me.constraints.min || v > me.constraints.max) {
				return string.substitute(i18nErr.numeric.outOfRange, {min: me.constraints.min, max: me.constraints.max});
			}

			return me.inherited(arguments);
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.initComplete();
		}
	});
});