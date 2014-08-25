define([
	"dojo/_base/declare",
	"dijit/form/SimpleTextarea",
	"dijit/form/ValidationTextBox",
	"steroid/backend/datatype/form/_DTFormFieldMixin",
	"dojo/i18n!steroid/backend/nls/FieldErrors",
	"dojo/string",
	"dojo/dom-attr",
	"dojo/dom-construct"
], function (declare, SimpleTextarea, ValidationTextBox, _DTFormFieldMixin, i18nErr, string, domAttr, domConstruct) {

	return declare([ValidationTextBox, SimpleTextarea, _DTFormFieldMixin], {

		valueNode: null,

		postMixInProperties: function () {
			var me = this;

			me.trim = true;

			if (me.constraints && me.constraints.regExp) {
				me.regExp = me.constraints.regExp;
			} else if (me.fieldConf.fixedLen) {
				me.regExp = '(?:.|\\s){' + me.fieldConf.maxLen + '}';
			} else {
				me.regExp = '(?:.|\\s){' + (me.fieldConf.nullable ? '0' : '1') + ',' + me.fieldConf.maxLen + '}';
			}

			me.inherited(arguments);
		},
		generateErrorMessage: function () {
			var me = this;

			var v = me.get('value');

			if ((!v || !v.length) && !me.fieldConf.nullable) {
				return me.inherited(arguments);
			}

			if (me.fieldConf.isFixed && v.length != me.fieldConf.maxLen) {
				return string.substitute(i18nErr.string.fixedLen, { num: me.fieldConf.maxLen });
			}

			if (v.length > me.fieldConf.maxLen) {
				return string.substitute(i18nErr.string.maxLen, { num: me.fieldConf.maxLen });
			}

			return me.inherited(arguments);
		},
		reset: function () {
			var me = this;

			me.inherited(arguments);
		},
		_setValueAttr: function (value) {
			var me = this;

			if (!me.valueNode) {
				me.valueNode = domConstruct.create('textarea', { innerHTML: '', style: 'display: none' });
				domConstruct.place(me.valueNode, me.domNode, 'after');
			}

			me.valueNode.value = value;

			me.inherited(arguments, [value]);
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.initComplete();
		}
	});
});