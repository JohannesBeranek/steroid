define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/_DTFormFieldMixin",
	"steroid/backend/datatype/_DTString",
	"dijit/form/MappedTextBox",
	"dojo/i18n!steroid/backend/nls/FieldErrors",
	"dojo/string",
	"steroid/backend/mixin/_hasContentLengthIndicator"
], function (declare, _DTFormFieldMixin, _DTString, MappedTextBox, i18nErr, string, _hasContentLengthIndicator) {

	return declare([MappedTextBox, _DTFormFieldMixin, _DTString, _hasContentLengthIndicator], {

		postMixInProperties: function () {
			var me = this;

			me.trim = true;

			if (me.fieldConf.constraints && me.fieldConf.constraints.regExp) {
				me.regExp = me.fieldConf.constraints.regExp;
			} else if (me.fieldConf.fixedLen) {
				me.regExp = '(?:.|\\s){' + me.fieldConf.maxLen + '}';
			} else {
				me.regExp = '(?:.|\\s){' + (me.fieldConf.nullable ? '0' : '1') + ',' + me.fieldConf.maxLen + '}';
			}

			me.inherited(arguments);
		},
		getPreview: function(){
			var me = this;

			return me.get('value');
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

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

				return validLength && (!this.required || !this._isEmpty(value)) && (this._isEmpty(value) || this.parse(value, constraints) !== undefined);
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
		}
	});
});