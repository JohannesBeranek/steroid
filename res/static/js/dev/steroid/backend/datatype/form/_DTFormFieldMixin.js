define([
	"dojo/_base/declare",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dojo/i18n!steroid/backend/nls/FieldErrors",
	"dojo/dom-attr",
	"dojo/dom-style",
	"steroid/backend/datatype/_DataType",
	"dojo/_base/lang",
	"dojo/Deferred",
	"dojo/_base/array",
	"dijit/Tooltip",
	"dojo/dom-construct",
	"dojo/dom-class",
	"steroid/backend/mixin/_hasInitListeners",
	"steroid/backend/FieldExtensionContainer",
	"dojo/aspect"
], function (declare, i18n, i18nErr, domAttr, domStyle, _DataType, lang, Deferred, array, Tooltip, domConstruct, domClass, _hasInitListeners, FieldExtensionContainer, aspect) {
	return declare([_DataType, _hasInitListeners], {
		value: null,
		labelNode: null,
		generatedRegExp: null,
		submitName: null,
		dirtyNess: 0,
		changeWatch: 1,
		isResetting: false,
		mainClassConfig: null,
		owningRecordClass: null,
		valueNodeName: 'valueNode',
		class: 'STFormField',
		hideField: false,
		isFieldConditionSource: false,
		isFieldConditionTarget: false,
		extensions: null,
		extensionContainer: null,
		startReadOnly: false,
		readOnly: false,
		wasReadOnly: false,
		formSubmitAspect: null,
		valueSetKey: null,
		detailPane: null,

		postMixInProperties: function () {
			var me = this;

			me.inherited(arguments);

			if (me.isFieldConditionSource) {
				me.class += ' STFieldConditionSource';
			}

			if (me.isFieldConditionTarget) {
				me.class += ' STFieldConditionTarget';
			}

			me.class += ' ' + me.fieldName.replace(':', '_');

			me.constraints = me.getConstraints();

			me.doConstraints();
		},
		getConstraints: function () {
			return this.fieldConf.constraints || {};
		},
		setConditionalFieldConf: function (conf) {
			var me = this;

			if (typeof conf.visible !== 'undefined') {
				if (conf.visible) {
					domClass.remove(me.domNode, 'conditionallyHidden');
					domClass.remove(me.labelNode, 'conditionallyHidden');
				} else {
					domClass.add(me.domNode, 'conditionallyHidden');
					domClass.add(me.labelNode, 'conditionallyHidden');
				}
			}

			if (typeof conf.readOnly !== 'undefined') {
				me.set('readOnly', conf.readOnly);
			}
		},
		isHidden: function () {
			var me = this;

			if(me.backend.debugMode || me.form.isFilterPane){
				return false;
			}

			if(typeof me.fieldConf.showInForm !== 'undefined'){
				return !me.fieldConf.showInForm;
			}

			return me.hideField;
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			if (me.isHidden()) {
				domStyle.set(me.domNode, 'display', 'none');
			} else {
				me.createLabel();
			}

			me.loadExtensions();

//			if(me.backend.moduleContainer && me.backend.moduleContainer.detailPane){
//				me.formSubmitAspect = aspect.before(me.backend.moduleContainer.detailPane, 'loadRecord', function(request){
//					me.wasReadOnly = me.get('readOnly');
//
//					me.set('readOnly', false); // need to be able to programmatically set values after submit, will be set back to correct value after values set
//
//					return request;
//				});
//			}

			var isReadOnly = me.isReadOnly();

			me.wasReadOnly = isReadOnly;

			me.set('readOnly', isReadOnly);
		},
		loadExtensions: function () {
			var me = this;

			if (typeof me.fieldConf.extensions != 'undefined') {
				me.extensionContainer = new FieldExtensionContainer({
					doLayout: false,
					class: 'STFieldExtensionContainer'
				});

				me.extensionContainer.startup();

				domConstruct.place(me.extensionContainer.domNode, me.domNode, 'after');

				me.extensions = [];

				var load = [];

				for (var idx in me.fieldConf.extensions) {
					load.push(me.fieldConf.extensions[idx]);
				}
// TODO:
// - cancel require in destroy, if it's still running, so we don't instance stuff after dataType has already been destroyed	(how to do this???)
				require(load, function () {
					for (var i = 0, ii = arguments.length; i < ii; i++) {
						var ext = new arguments[i](me);

						ext.detailPane = me.detailPane;

						me.extensions.push(ext);

						ext.startup();

						ext.setReadOnly(me.readOnly);
					}
				});
			}
		},
		isReadOnly: function () {
			var me = this;

			return me.fieldConf.startReadOnly || me.fieldConf.readOnly || false;
		},
		_setReadOnlyAttr: function (readOnly) {
			var me = this;

			if (me.isReadOnly()) {
				readOnly = true;
			}

			me.inherited(arguments);

			if (typeof readOnly == 'undefined') {
				return; // gets called by dojo during applyAttributes
			}

			me.addInitListener(function () {
				if (me.domNode) {
					if (readOnly) {
						domClass.add(me.domNode, 'STReadOnly');
					} else {
						domClass.remove(me.domNode, 'STReadOnly');
					}
				}

				if (me.labelNode) {
					if (readOnly) {
						domClass.add(me.labelNode, 'STReadOnly');
					} else {
						domClass.remove(me.labelNode, 'STReadOnly');
					}
				}

				if (me.extensions && me.extensions.length) {
					for (var i = 0, item; item = me.extensions[i]; i++) {
						item.setReadOnly(readOnly);
					}
				}
			});
		},
		getExtensionByName: function (name) {
			var me = this;

			if (typeof me.extensions === 'undefined' || !me.extensions.length) {
				return null;
			}

			for (var i = 0, item; item = me.extensions[i]; i++) {
				if (item.extensionName === name) {
					return item;
				}
			}

			return null;
		},
		doConstraints: function () {
			var me = this;

			me.required = me.getRequired();
		},
		getRequired: function () {
			var me = this;

			return !me.fieldConf.nullable;
		},
		createLabel: function () {
			var me = this;

			me.labelNode = domConstruct.create('label', {'for': me.id, innerHTML: me.getLabel(), class: 'STLabel_' + me.fieldName.replace(':', '-')});
			domConstruct.place(me.labelNode, me.domNode, 'before');
		},
		_setValueAttr: function (value) {
			var me = this;

			me.inherited(arguments);

			me.valueComplete();

			me.set('STValue', me.STSetValue(value));
		},
		valueComplete: function () {
			var me = this;

			me.inherited(arguments);

			if (me.get('readOnly') != me.wasReadOnly) {
				me.set('readOnly', me.wasReadOnly);
			}
		},
		collectTitle: function () {
			return this.value;
		},
		STSetValue: function (value) {
			var me = this;

			value = (typeof value === 'number' && isNaN(value)) || typeof value == 'undefined' || value === '' || value == 'Invalid Date' ? null : value;

			if (typeof me.originalValue == 'undefined') {
				me.originalValue = value;
			}

			return value;
		},
		getDirtyNess: function () {
			var me = this;

			var dirtyNess = (me.STValue === me.originalValue ? 0 : 1);

			return dirtyNess;
		},
		reset: function () {
			var me = this;

			me.isResetting = true;

			delete me.originalValue;

			if (typeof me.extensions != 'undefined' && me.extensions) {
				for (var i = 0, ii = me.extensions.length; i < ii; i++) {
					me.extensions[i].reset();
				}
			}

			me.inherited(arguments);
		},
		_setMessageAttr: function (message) {
			var me = this;

			var state = me.get('state');

			if (me.isFilterPane) {
				return;
			}

			var valid = state == '';

			if (valid) {
				if (me.labelNode) {
					domClass.remove(me.labelNode, 'STInvalid');
				}

				domClass.remove(me.domNode, 'STInvalid');
			} else {
				if (me.labelNode) {
					domClass.add(me.labelNode, 'STInvalid');
				}

				domClass.add(me.domNode, 'STInvalid');

				message = me.generateErrorMessage(state);
			}

			me.inherited(arguments, [message, state]);
		},
		generateErrorMessage: function (state) {
			var me = this;

			var v = me.get('value');

			return i18nErr.generic[!v ? 'empty' : 'error'];
		},
		updateSubmitName: function (submitName) {
			var me = this;

			me.submitName = submitName;

			if (me[me.valueNodeName]) {
				if (domAttr.get(me[me.valueNodeName], 'name')) {
					me.setSubmitName(true);
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

			if (me[me.valueNodeName]) {
				domAttr.set(me[me.valueNodeName], 'name', setName ? me.submitName : '');
			}
		},
		destroy: function () {
			var me = this;

			if (typeof me.extensions != 'undefined' && me.extensions) {
				for (var i = 0, ii = me.extensions.length; i < ii; i++) {
					if ('destroy' in me.extensions[i]) {
						me.extensions[i].destroy();
					}
				}

				delete me.extensions;

				me.extensionContainer.destroyRecursive();
				delete me.extensionContainer;
			}

			me.removeValueSetListener(me.valueSetKey);
			delete me.valueSetKey;

			if (me.labelNode) {
				domConstruct.destroy(me.labelNode);
				delete me.labelNode;
			}

			me.initListeners = [];

			me.valueSetListeners = [];

			delete me.originalValue;
			delete me.STValue;
			delete me.detailPane;

			me.inherited(arguments);
		}

	});
});