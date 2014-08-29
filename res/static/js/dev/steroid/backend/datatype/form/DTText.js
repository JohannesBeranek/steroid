define([
	"dojo/_base/declare",
	"dijit/form/SimpleTextarea",
	"dijit/form/ValidationTextBox",
	"steroid/backend/datatype/form/_DTFormFieldMixin",
	"dojo/i18n!steroid/backend/nls/FieldErrors",
	"dojo/string",
	"dojo/dom-attr",
	"dojo/dom-construct",
	"dojo/_base/lang",
	"steroid/backend/mixin/_hasContentLengthIndicator"
], function (declare, SimpleTextarea, ValidationTextBox, _DTFormFieldMixin, i18nErr, string, domAttr, domConstruct, lang, _hasContentLengthIndicator) {

	return declare([ValidationTextBox, SimpleTextarea, _DTFormFieldMixin, _hasContentLengthIndicator], {

		valueNode: null,

		postMixInProperties: function () {
			var me = this;

			me.trim = false;

			me.inherited(arguments);

			me.regExp = me.constraints.regExp;
		},
		getPreview: function(){
			var me = this;

			return me.get('value');
		},
		startup: function () {
			var me = this;

			me.valueNode = domConstruct.create('textarea', { innerHTML: '', style: 'display: none' });
			domConstruct.place(me.valueNode, me.domNode, 'after');

			me.inherited(arguments);

			me.afterStartup();
		},
		afterStartup: function () {
			var me = this;

			me.initComplete();
		},
		// FIXME: move to mixin, so it can be reused by DTString+DTText
		// [JB 07.03.2013] enhances validation performance in most scenarios and prevents chrome crashing from too long string not validating against regex
		validator: function (/*anything*/ value, /*__Constraints*/ constraints) {
			// summary:
			//		Overridable function used to validate the text input against the regular expression.
			// tags:
			//		protected

			var me = this;

			if (me.constraints && me.constraints.regExp) {
				return me.inherited(arguments);
			} else {
				var len = value.length;

				if (me.fieldConf.fixedLen) {
					var validLength = (len === me.fieldConf.maxLen);
				} else {
					var validLength = (len >= (me.fieldConf.nullable ? 0 : 1) && len <= me.fieldConf.maxLen);
				}

				return validLength && (!this.required || !this._isEmpty(value)) && (this._isEmpty(value) || typeof this.parse(value, constraints) !== 'undefined');
			}
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
		_setValueAttr: function (value) {
			var me = this;

			if (typeof value == 'undefined' || value === null) {
				value = '';
			} else {
				value += ''; // convert to string in case of number
			}

			me.valueNode.value = value;

			me.inherited(arguments, [value]);
		},
		detroy: function () {
			var me = this;

			domConstruct.destroy(me.valueNode);

			me.inherited(arguments);
		}
	});
});