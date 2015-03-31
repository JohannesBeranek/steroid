define([
	"dojo/_base/declare",
	"dijit/_Widget",
	"steroid/backend/datatype/_DTSet",
	"steroid/backend/datatype/form/_DTFormFieldMixin",
	"dijit/form/CheckBox",
	"dojo/dom-construct",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dojo/dom-attr",
	"dojo/dom-class",
	"dojo/Deferred"
], function (declare, _Widget, _DTSet, _DTFormFieldMixin, CheckBox, domConstruct, i18nRC, domAttr, domClass, Deferred) {

	return declare([_Widget, _DTFormFieldMixin, _DTSet], {
		checkBox: null,
		"class": 'STCheckBoxGroup',
		watchHandles: null,
		disabled: false,

		constructor: function () {
			this.watchHandles = [];
		},
		_setDisabledAttr: function (value) {
			var me = this;

			value = !!value;

			me.inherited(arguments, [value]);

			if (!me.checkBox) {
				me.addInitListener(function () {
					me.set('disabled', value);
				});

				return;
			}

			me.checkBox.set('disabled', value);

			domAttr.set(me.emptyNode, 'disabled', value);

			me.disabled = value;
		},
		_setReadOnlyAttr: function (value) {
			var me = this;

			value = !!value;

			if (me.isReadOnly()) {
				value = true;
			}

			me.addInitListener(function () {
				me.checkBox.set('readOnly', value);
			});

			me.inherited(arguments);
		},
		createLabel: function () {
			var me = this;

			me.labelNode = domConstruct.create('label', {'for': me.checkBox.id, innerHTML: me.getLabel(), "class": 'STLabel_' + me.fieldName.replace(':', '-')});
			domConstruct.place(me.labelNode, me.domNode);
		},
		startup: function () {
			var me = this;

			me.checkBox = new CheckBox({
				checked: false,
				value: "1",
				disabled: !!me.disabled,
				readOnly: !!me.readOnly,
				name: me.submitName
			});

			me.checkBox.startup();

			me.watchHandles.push(me.checkBox.watch('checked', function (name, newValue, oldValue) {
				if (!me.settingValue) {
					me.checkBoxUpdated();
				}
			}));

			me.container = domConstruct.create('div', { "class": 'STCheckBox' });

			me.container.appendChild(me.checkBox.domNode);

			me.domNode.appendChild(me.container);

			me.emptyNode = domConstruct.create('input', { type: 'hidden', value: 0 });
			me.domNode.appendChild(me.emptyNode);

			me.inherited(arguments);

			me.initComplete();
		},
		checkBoxUpdated: function () {
			var me = this;

			var current = me.checkBox.checked;

			me.set('STValue', me.STSetValue(current));

			me.set('changeWatch', me.get('changeWatch') * (-1));
		},
		_getValueAttr: function () {
			var me = this;

			return me.STValue ? 1 : 0;
		},
		_setValueAttr: function (value) {
			var me = this;

			me.settingValue = true;

			me.checkBox.set('checked', !!value);

			me.settingValue = false;

			me.checkBoxUpdated();

			me.valueComplete();

			if (me.domNode) {
				if (value) {
					domClass.replace(me.domNode, 'STValue_true', 'STValue_true');
				} else {
					domClass.replace(me.domNode, 'STValue_false', 'STValue_true');
				}
			}
		},
		setSubmitName: function (setName) {
			var me = this;

			if (me.backend.debugMode && me.labelNode) {
				if (setName) {
					domClass.add(me.labelNode, 'willSubmit');
				} else {
					domClass.remove(me.labelNode, 'willSubmit');
				}
			}

			me.checkBox.focusNode.name = setName && me.STValue ? me.submitName : '';

			me.emptyNode.name = setName && !me.STValue ? me.submitName : '';

			me.emptyNode.disabled = me.disabled ? 'disabled' : '';
		},
		updateSubmitName: function (submitName) {
			var me = this;

			me.submitName = submitName;

			if ((me.emptyNode && domAttr.get(me.emptyNode, 'name')) || domAttr.get(me.checkBox.focusNode, 'name')) {
				me.setSubmitName(true);
			}
		},
		reset: function () {
			var me = this;

			me.disabled = false;

			me.inherited(arguments);
		},
		destroy: function () {
			var me = this;

			if (me._beingDestroyed) {
				me.inherited(arguments);
				return;
			}

			me.checkBox.destroyRecursive();

			domConstruct.destroy(me.emptyNode);
			domConstruct.destroy(me.container);

			var watchLen = me.watchHandles.length;

			for (var i = 0; i < watchLen; i++) {
				me.watchHandles[i].unwatch();
			}

			delete me.watchHandles;

			me.inherited(arguments);
		}
	});
});
