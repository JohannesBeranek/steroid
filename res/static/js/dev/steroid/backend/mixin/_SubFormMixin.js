define([
	"dojo/_base/declare",
	"dojo/_base/array",
	"dojo/Deferred",
	"dojo/dom-construct",
	"dijit/_Widget",
	"dojox/lang/functional",
	"steroid/backend/mixin/_hasStandBy",
	"dojo/_base/lang",
	"dojo/DeferredList",
	"steroid/backend/mixin/_hasInitListeners",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dojo/dom-class",
	"dojo/on"
], function (declare, array, Deferred, domConstruct, _Widget, langFunc, _hasStandBy, lang, DeferredList, _hasInitListeners, i18nRC, domClass, on) {
	return declare([_Widget, _hasStandBy, _hasInitListeners], {
		originalRecord: null,
		record: null,
		recordPrimary: null,
		ownFields: null,
		isNew: false,
		isResetting: false,
		loadingDef: null,
		state: null,
		dirtyNess: null,
		fieldWatches: null,
		fieldStati: null,
		ownClassConfig: null,
		mainClassConfig: null,
		backend: null,
		submitFieldsIfDirty: null,
		submitName: '',
		changeWatch: 1,
		fieldName: null,
		currentlySettingValue: false,
		STValue: null,
		fieldChangeRegistered: false,
		i18nExt: null,
		disabled: false,
		fieldSets: null,
		readOnly: false,
		detailPane: null,
		notDisableFields: null,
		fieldSetListeners: null,

		constructor: function () {
			var me = this;

			me.ownFields = {};
			me.backend = null;
			me.fieldWatches = {};
			me.fieldSets = [];
			me.notDisableFields = [];
			me.fieldSetListeners = [];
		},
		_setReadOnlyAttr: function (readOnly) {
			var me = this;

			me.inherited(arguments);

			me.addInitListener(function () {
				for (var i in me.ownFields) {
					me.ownFields[i]._dt.set('readOnly', readOnly);
				}

				if (me.domNode) {
					if (readOnly) {
						domClass.add(me.domNode, 'STReadOnly');
					} else {
						domClass.remove(me.domNode, 'STReadOnly');
					}
				}
			});
		},
		_setDisabledAttr: function (disabled) {
			var me = this;

			me.inherited(arguments);

			me.disabled = disabled;

			me.addInitListener(function () {
				for (var i in me.ownFields) {
					if (array.indexOf(me.notDisableFields, i) < 0) {
						me.ownFields[i]._dt.set('disabled', disabled);
					}
				}
			});
		},
		setSubmitPrimaryOnly: function (primaryOnly) {
			var me = this;

			for (var i in me.ownFields) {
				me.ownFields[i]._dt.set('disabled', (primaryOnly && i !== 'primary'));
			}
		},
		connectChildren: function () {
			// we do this ourselves
		},
		postMixInProperties: function () {
			var me = this;

			me.ownFields = lang.clone(me.ownClassConfig.formFields);

			me["class"] += ' ' + me.ownClassConfig.className;

			me.inherited(arguments);

			if (!me.submitFieldsIfDirty) {
				me.submitFieldsIfDirty = ['primary'];
			}
		},
		postCreate: function () {
			var me = this;

			me.standByNode = me.domNode;

			me.inherited(arguments);
		},
		getFieldPath: function (entry, i) {
			var me = this, isCore, path;

			if (typeof entry.classLocation !== 'undefined') { // extension datatype
				path = '/' + entry.classLocation + '/res/static/js/datatype/form/' + entry.dataType + '.js';
			} else { // core datatype
				path = 'steroid/backend/datatype/form/' + entry.dataType;
			}

			if (i == 'primary') { //TODO: unhardcode?
				path = "steroid/backend/datatype/form/DTSteroidPrimary";
			}

			if (i == 'parent' && (me.ownClassConfig.className == 'RCPage' || me.ownClassConfig.className == 'RCDomainGroup')) { // quick & easy way to enable moving pages to different parent
				path = "steroid/backend/datatype/form/DTRecordSelector";
			}

			return path;
		},
		getFieldSubmitName: function (fieldName) {
			var me = this;

			return me.submitName ? me.submitName + '[' + fieldName + ']' : fieldName;
		},
		getFieldConf: function (entry, i) {
			var me = this;

			var isFieldConditionSource = me.ownClassConfig.conditionalFieldConf && typeof me.ownClassConfig.conditionalFieldConf[i] !== 'undefined';
			var isFieldConditionTarget = me.checkIsFieldConditionTarget(i);

			var fieldConf = {
				fieldConf: entry,
				backend: me.backend,
				fieldName: i,
				owningRecordClass: me.ownClassConfig.className,
				mainClassConfig: me.mainClassConfig,
				submitName: me.getFieldSubmitName(i),
				form: me,
				isFieldConditionSource: isFieldConditionSource,
				isFieldConditionTarget: isFieldConditionTarget,
				i18nExt: me.ownClassConfig.i18nExt,
				readOnly: me.readOnly || me.startReadOnly,
				detailPane: me.detailPane
			};

			if (!me.isFilterPane && array.indexOf(me.getFieldsToHide(), i) >= 0) {
				fieldConf.hideField = true;
			}

			return fieldConf;
		},
		indexChange: function (idx, beingDestroyed) {
			var me = this;

			if (idx === me.ownIndexInParent) {

				if (me.ownClassConfig.sortingField && me.ownFields[me.ownClassConfig.sortingField]._dt.get('value') === null) {
					me.updateSortingField();
				}

				return;
			}

			me.ownIndexInParent = idx;

			if (beingDestroyed || (me.backend.moduleContainer.detailPane && me.backend.moduleContainer.detailPane.form && me.backend.moduleContainer.detailPane.form.isResetting)) {
				return;
			}

			me.updateFieldSubmitNames();
			me.updateSortingField();
		},
		updateFieldSubmitNames: function () {
			var me = this;

			for (var fieldName in me.ownFields) {
				me.ownFields[fieldName]._dt.updateSubmitName(me.submitName + '[' + me.ownIndexInParent + ']' + '[' + fieldName + ']');
			}
		},
		updateSortingField: function () {
			var me = this;

			if (me.ownClassConfig.sortingField) {
				me.ownFields[me.ownClassConfig.sortingField]._dt.set('value', me.ownIndexInParent);
			}
		},
		checkIsFieldConditionTarget: function (targetFieldName) {
			var me = this;

			if (!me.ownClassConfig.conditionalFieldConf) {
				return false;
			}

			for (var fieldName in me.ownClassConfig.conditionalFieldConf) {
				var c = me.ownClassConfig.conditionalFieldConf[fieldName];

				for (var value in c) {
					for (var target in c[value]) {
						if (target == targetFieldName) {
							return true;
						}
					}
				}
			}

			return false;
		},
		getFieldsToHide: function () {
			var me = this;

			var fields = ['pageType'];

			return fields;
		},
		_getStateAttr: function () {
			var me = this;

			var state = '';

			for (var i in me.ownFields) {
				var fieldState = me.ownFields[i]._dt.get('state');

				if (fieldState == 'Incomplete' || fieldState == 'Error') {
					state = fieldState;
					me.set('open', true);
					domClass.add(me.domNode, 'STInvalid');
					break;
				}
			}

			if (state == '') {
				domClass.remove(me.domNode, 'STInvalid');
			}

			return state;
		},
		_getValueAttr: function () {
			var me = this;

			var value = {};

			for (var fieldName in me.ownFields) {
				value[fieldName] = me.ownFields[fieldName]._dt.get('value');
			}

			return value;
		},
		startup: function () {
			var me = this;

//			me.doStandBy();

			me.ownFieldContainerNode = domConstruct.create('div', { "class": 'STFieldContainer' });

			var fieldCount = langFunc.keys(me.ownFields).length;

			var fieldOrder = [];

			var idx = 0;

			var fieldsRequiredDef = new Deferred();

			langFunc.forIn(me.ownFields, function (entry, i) {
				fieldOrder.push(i);

				var fieldPath = me.getFieldPath(entry, i);

				require([fieldPath], function (dataType) {
					var dt = new dataType(me.getFieldConf(entry, i));

					me.hookToDataTypeInstance(dt, entry, i);

					me.ownFields[i]._dt = dt;

					fieldCount--;

					if (fieldCount == 0) {
						fieldsRequiredDef.resolve();
					}
				});

				idx++;
			});

			fieldOrder = me.getFieldOrder(fieldOrder);

			fieldsRequiredDef.then(function () {
				var initFieldCount = langFunc.keys(me.ownFields).length, valueFieldCount;

				if (!me.isFilterPane && me.ownClassConfig.fieldSets && langFunc.keys(me.ownClassConfig.fieldSets).length) {
					var mainFieldSet = me.addFieldSet('fs_main');
					var mainFieldSetContainsField = false;
					var mainFieldSetFieldsVisible = false;
				}

				for (var i = 0; i < fieldOrder.length; i++) {
					var fieldName = fieldOrder[i];

					var containerNode = mainFieldSet || me.ownFieldContainerNode;

					if (!me.isFilterPane && me.ownClassConfig.fieldSets && langFunc.keys(me.ownClassConfig.fieldSets).length) {
						for (var fieldSetName in me.ownClassConfig.fieldSets) {
							if (fieldSetName === '__addedBy') {
								continue;
							}

							if (me.ownClassConfig.fieldSets[fieldSetName].indexOf(fieldName) !== -1) {
								var fieldSetExists = false;

								for (var x = 0, fieldSet; fieldSet = me.fieldSets[x]; x++) {
									if (domClass.contains(fieldSet, me.ownClassConfig.className + '_' + fieldSetName)) {
										fieldSetExists = true;
										break;
									}
								}

								if (!fieldSetExists) {
									var collapsed = me.ownClassConfig.fieldSets[fieldSetName].indexOf('_startCollapsed') !== -1;
									var addedBy = me.ownClassConfig.fieldSets && me.ownClassConfig.fieldSets['__addedBy'] && me.ownClassConfig.fieldSets['__addedBy'][fieldSetName] ? me.ownClassConfig.fieldSets['__addedBy'][fieldSetName] : null;
									var fieldSet = me.addFieldSet(fieldSetName, addedBy, collapsed);
								}

								containerNode = fieldSet;
							}
						}
					}

					var dt = me.ownFields[fieldName]._dt;

					containerNode.appendChild(dt.domNode);

					if (containerNode === mainFieldSet) {
						mainFieldSetContainsField = true;

						if (!dt.isHidden()) {
							mainFieldSetFieldsVisible = true;
						}
					}

					dt.startup();

					dt.addInitListener(function (dt) {
						initFieldCount--;

						if (!initFieldCount) {
							me.containerNode.appendChild(me.ownFieldContainerNode);
							me.initComplete();
						}
					});
				}

				if (mainFieldSet && !mainFieldSetContainsField) {
					me.removeFieldSet('fs_main');
				}

				if (mainFieldSet && !mainFieldSetFieldsVisible) {
					domClass.add(mainFieldSet, ' STHidden');
				}
			});

			me.addValueSetListenerOnce(function () {
				me.addWatches();
			});

			me.addValueSetListener(function () {
//				me.hideStandBy();
			});
		},
		removeFieldSet: function (fieldSetName) {
			var me = this;

			for (var i = 0, item; item = me.fieldSets[i]; i++) {
				if (domClass.contains(item, me.ownClassConfig.className + '_' + fieldSetName)) {
					me.fieldSets.splice(array.indexOf(me.fieldSets, item), 1);
					domConstruct.destroy(item);
				}
			}
		},
		addFieldSet: function (fieldSetName, addedBy, collapsed) {
			var me = this;

			var label = '';

			if (addedBy) {
				var foreignClassConfig = me.backend.getClassConfigFromClassName(addedBy);

				if (foreignClassConfig && foreignClassConfig.i18nExt && foreignClassConfig.i18nExt[me.ownClassConfig.className] && foreignClassConfig.i18nExt[me.ownClassConfig.className][fieldSetName]) {
					label = foreignClassConfig.i18nExt[me.ownClassConfig.className][fieldSetName];
				} else if (foreignClassConfig && foreignClassConfig.i18nExt && foreignClassConfig.i18nExt[addedBy] && foreignClassConfig.i18nExt[addedBy][fieldSetName]) {
					label = foreignClassConfig.i18nExt[addedBy][fieldSetName];
				} else {
					label = foreignClassConfig.i18nExt[addedBy + '_name'];
				}
			}

			if (!label) {
				label =
					(me.ownClassConfig.i18nExt && me.ownClassConfig.i18nExt[me.ownClassConfig.className] && me.ownClassConfig.i18nExt[me.ownClassConfig.className][fieldSetName])
						? me.ownClassConfig.i18nExt[me.ownClassConfig.className][fieldSetName]
						: (i18nRC[me.ownClassConfig.className] && i18nRC[me.ownClassConfig.className][fieldSetName])
						? i18nRC[me.ownClassConfig.className][fieldSetName]
						: i18nRC.generic[fieldSetName];
			}

			var fieldSet = domConstruct.create('fieldset', { "class": 'STFieldSet ' + me.ownClassConfig.className + '_' + fieldSetName + (collapsed ? ' collapsed' : '') });
			var legend = domConstruct.create('legend', { innerHTML: label });
			fieldSet.appendChild(legend);
			me.ownFieldContainerNode.appendChild(fieldSet);
			me.fieldSets.push(fieldSet);
			me.fieldSetListeners.push(on(legend, 'click', function () {
				domClass.toggle(fieldSet, 'collapsed');
			}));

			return fieldSet;
		},
		getFieldOrder: function (fieldOrder) {
			return fieldOrder;
		},
		hookToDataTypeInstance: function (dt, fieldConf, fieldName) {
			//stub
		},
		addWatches: function () {
			var me = this;

			for (var fieldName in me.ownFields) {
				me.fieldWatches[fieldName] = me.ownFields[fieldName]._dt.watch('STValue', function (name, oldValue, newValue) {

					if (!me.isClosing && !me.currentlySettingValue) {
						me.fieldChanged();
					}
				});
			}
		},
		fieldChanged: function () {
			var me = this;

			var val = me.get('value');

			me.record = val;
			me.set('STValue', val);

			if (!me.isFilterPane) {
				me.checkFieldConditions();
			}
		},
		getDataTypeFieldName: function (dataType) {
			var me = this;

			for (var fieldName in me.ownFields) {
				if (me.ownFields[fieldName].dataType == dataType) {
					return fieldName;
				}
			}

			return null;
		},
		getFieldByFieldName: function (targetFieldName) {
			var me = this;

			for (var fieldName in me.ownFields) {
				if (fieldName == targetFieldName) {
					return me.ownFields[fieldName]._dt;
				}
			}

			return null;
		},
		getDirtyNess: function () {
			var me = this;

			for (var fieldName in me.ownFields) {
				var fieldDirtyNess = me.ownFields[fieldName]._dt.getDirtyNess();

				if (fieldDirtyNess > 0) {
					return fieldDirtyNess;
				}
			}

			return 0;
		},
		setSubmitName: function (setName) {
			var me = this;

			for (var fieldName in me.ownFields) {
				var dt = me.ownFields[fieldName]._dt;

				var fieldDirtyNess = dt.getDirtyNess();
				var setFieldDirty = me.setFieldDirty(setName, fieldDirtyNess, fieldName);

				dt.setSubmitName(setFieldDirty);
			}
		},
		setFieldDirty: function (setName, fieldDirtyNess, fieldName) {
			var me = this;

			return me.isNew || (setName && (fieldDirtyNess > 0 || array.indexOf(me.submitFieldsIfDirty, fieldName) >= 0));
		},
		_setValueAttr: function (value) {
			var me = this;

			if (me.valueSet) {
				me.reset();
			}

			me.currentlySettingValue = true;

//			me.doStandBy();

			me.loadingDef = value;

			me.addInitListener(function () {
				dojo.when(me.loadingDef, function (response) {
					me.record = (response ? (response.items && response.items[0] ? response.items[0] : response) : value) || {};

					me.setIsNewFromValue();

					me.originalRecord = me.record;

					me.setFieldValues();
				});
			});
		},
		valueComplete: function () {
			var me = this;

			me.currentlySettingValue = false;

			me.inherited(arguments);

			if (!me.isFilterPane) {
				me.checkFieldConditions();
			}
		},
		setIsNewFromValue: function () {
			var me = this;

			if (me.ownClassConfig.hasPrimaryField && !(me.record && me.record.primary)) {
				me.isNew = true;
			}
		},
		setFieldValues: function () {
			var me = this;

			var valueFieldCount = langFunc.keys(me.ownFields).length;

			for (var fieldName in me.ownFields) {
				me.ownFields[fieldName]._dt.set('value', me.record[fieldName]);

				me.ownFields[fieldName]._dt.addValueSetListenerOnce(function (dt) {
					valueFieldCount--;

					if (!valueFieldCount) {
						me.valueComplete();
					}
				});
			}
		},
		resetRecord: function () {
			var me = this;

			me.reset();

			if (me.originalRecord) {
				me.set('value', me.originalRecord);
			}
		},
		reset: function () {
			var me = this;

			me.isResetting = true;

			me.resetFormFields();

			me.valueSet = false;

			me.set('disabled', false);

			me.inherited(arguments);

			me.isResetting = false;
		},
		resetFormFields: function () {
			var me = this;

			for (var fieldName in me.ownFields) {
				me.ownFields[fieldName]._dt.reset();
			}
		},
		beforeDomMove: function () {
			var me = this;

			for (var fieldName in me.ownFields) {
				// FIXME: should not only trigger on specific dt
				if (me.ownFields[fieldName].dataType == 'DTRTE') {
					me.ownFields[fieldName]._dt.beforeDomMove();
				}
			}
		},
		afterDomMove: function () {
			var me = this;

			for (var fieldName in me.ownFields) {
				// FIXME: should not only trigger on specific dt
				if (me.ownFields[fieldName].dataType == 'DTRTE') {
					me.ownFields[fieldName]._dt.afterDomMove();
				}
			}
		},
		checkFieldConditions: function () {
			var me = this;

			var conf = me.ownClassConfig.conditionalFieldConf;

			if (!conf) {
				return;
			}

			for (var fieldName in conf) {
				var dt = me.getFieldByFieldName(fieldName);

				var fieldValue = dt.get('value');

				if (fieldValue === null) {
					fieldValue = '_null_';
				}

				if (domClass.contains(dt.domNode, 'conditionallyHidden')) { // ignore fields that are hidden
					continue;
				}

				if (conf[fieldName][fieldValue]) {
					for (var affectedField in conf[fieldName][fieldValue]) {
						var affectedDt = me.getFieldByFieldName(affectedField);

						if (affectedDt) {
							affectedDt.setConditionalFieldConf(conf[fieldName][fieldValue][affectedField]);
						} else { // check fieldsets
							for (var i = 0, fieldSet; fieldSet = me.fieldSets[i]; i++) {
								if (domClass.contains(fieldSet, me.ownClassConfig.className + '_' + affectedField)) {
									if (typeof conf[fieldName][fieldValue][affectedField].visible !== 'undefined') {
										if (conf[fieldName][fieldValue][affectedField].visible) {
											domClass.remove(fieldSet, 'conditionallyHidden');
										} else {
											domClass.add(fieldSet, 'conditionallyHidden');
										}
									}

									// TODO: implement readOnly for fieldSets
								}
							}
						}
					}
				}
			}
		},
		collectTitle: function (origin) {
			var me = this;

			var title = '';

			for (var fieldName in origin.ownClassConfig.titleFields) {
				if (!origin.ownFields[fieldName] || !origin.ownFields[fieldName]._dt) {
					continue;
				}

				var fieldTitle = origin.ownFields[fieldName]._dt.collectTitle();

				if (fieldTitle) {
					title += fieldTitle;
				}
			}

			if(title == '' && origin.record && origin.record._title !== ''){
				title = origin.record._title;
			}

			return title;
		},
		destroy: function () {
			var me = this;

			for (var i in me.fieldWatches) {
				me.fieldWatches[i].unwatch();
			}

			delete me.fieldWatches;

			for (var fieldName in me.ownFields) {
				if (me.ownFields[fieldName]._dt) {
					me.ownFields[fieldName]._dt.destroyRecursive();
				}
			}

			me.ownFields = {};

			domConstruct.destroy(me.ownFieldContainerNode);

			delete me.ownFieldContainerNode;
			delete me.backend;
			delete me.STValue;
			delete me.record;
			delete me.originalRecord;
			delete me.detailPane;

			if (me.loadingDef && me.loadingDef.fired < 0) {
				me.loadingDef.resolve();
				delete me.loadingDef;
			}

			me.inherited(arguments);
		}
	});
});