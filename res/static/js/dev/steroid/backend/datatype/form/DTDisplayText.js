define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/_DTFormFieldMixin",
	"steroid/backend/datatype/_DTString",
	"dojo/dom-construct",
	"dojo/dom-attr"
], function (declare, _DTFormFieldMixin, DTString, domConstruct, domAttr) {

	return declare([_DTFormFieldMixin], {

		valueNodeName: 'displayNode',
		value: null,

		postCreate: function () {
			var me = this;

			me.displayNode = domConstruct.create('div', { "class": 'STDisplayTextNode' });

			me.domNode.appendChild(me.displayNode);

			me.inherited(arguments);
		},
		_setValueAttr: function (value) {
			var me = this;

			var val = me.formatValue(value);

			me.displayNode.innerHTML = val;

			me.value = val;

			me.inherited(arguments);
		},
		_getValueAttr: function () {
			var me = this;

			return me.value;
		},
		formatValue: function (value) {
			return value;
		},
		destroy: function () {
			var me = this;

			domConstruct.destroy(me.displayNode);

			me.inherited(arguments);
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.initComplete();
		}
	});
});