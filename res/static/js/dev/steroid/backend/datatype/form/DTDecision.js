define([
	"dojo/_base/declare",
	"dijit/_Widget",
	"steroid/backend/datatype/_DTEnum",
	"steroid/backend/datatype/form/_DTFormFieldMixin",
	"steroid/backend/datatype/form/RadioButton",
	"dojo/dom-construct",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dojo/Deferred",
	"steroid/backend/Localizor"
], function (declare, _Widget, _DTEnum, _DTFormFieldMixin, RadioButton, domConstruct, i18nRC, Deferred, Localizor) {

	return declare([_Widget, _DTFormFieldMixin, _DTEnum], {
		options: null,
		class: 'STRadioGroup',
		current: '',
		lastOptionValue: null,
		disabled: false,

		constructor: function () {
			this.options = {};
		},
		postCreate: function () {
			var me = this;

			me.setOptions(me.fieldConf.values);
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.initComplete();
		},
		setOptions: function (values) {
			var me = this;

			me.lastOptionValue = values[values.length - 1];

			var loc = new Localizor({
				backend: me.backend
			});

			var labels = loc.getFieldLabel(me.fieldConf, me.owningRecordClass, me.fieldName, me.i18nExt, false, '_values');

			for (var i = 0; i < values.length; i++) {

				var label = labels[values[i]];

				var option = new RadioButton({
					checked: false,
					value: values[i],
					label: label,
					gutes: me,
					name: me.submitName + '[]'

				});

				option.startup();

				me.options[values[i]] = option;

				var container = domConstruct.create('div', { class: 'STRadio' });
				var label = domConstruct.create('label', { 'for': option.id, innerHTML: label });

				container.appendChild(label);
				container.appendChild(option.domNode);

				me.domNode.appendChild(container);
			}
		},
		_setReadOnlyAttr: function (readOnly) {
			var me = this;

			if (me.isReadOnly()) {
				readOnly = true;
			}

			me.inherited(arguments);

			me.addInitListener(function () {
				for (var i in me.options) {
					me.options[i].set('readOnly', readOnly);
				}
			});
		},
		_setDisabledAttr: function (disabled) {
			var me = this;

			for (var i in me.options) {
				me.options[i].set('disabled', disabled);
			}

			me.inherited(arguments);

			me.disabled = disabled;
		},
		reset: function () {
			var me = this;

			for (var i in me.options) {
				me.options[i].set('disabled', false);
			}

			me.disabled = false;

			delete me.originalValue;
		},
		radioUpdated: function () {
			var me = this;

			me.set('STValue', me.STSetValue(me.current));

			me.set('changeWatch', me.get('changeWatch') * (-1));
		},
		_setValueAttr: function (value) {
			var me = this;

			if (value) {
				me.options[value].set('checked', true);
				me.current = value;
				me.set('STValue', me.STSetValue(me.current));
			} else {
				me.reset();
			}

			me.valueComplete();
		},
		_getValueAttr: function () {
			var me = this;

			return me.current;
		},
		updateSubmitName: function (submitName) {
			var me = this;

			me.submitName = submitName;

			for (var i in me.options) {
				me.options[i].set('name', me.submitName);
			}
		},
		setSubmitName: function (setName) {
			var me = this;

			for (var i in me.options) {
				if (me.fieldConf.alwaysDirty || (setName && !me.disabled)) {
					me.options[i].set('disabled', false);
				} else {
					if (i == me.current) {
						me.options[i].set('disabled', true);
					}
				}
			}
		},
		destroy: function () {
			var me = this;

			for (var i in me.options) {
				me.options[i].destroyRecursive();
			}

			delete me.options;
			delete me.lastOptionValue;
			delete me.current;

			me.inherited(arguments);
		}
	});
});
