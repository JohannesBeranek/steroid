define([
	"dojo/_base/declare",
	"dijit/layout/ContentPane",
	"steroid/backend/datatype/form/_DTFormFieldMixin",
	"steroid/backend/mixin/RecordSearchField",
	"steroid/backend/dnd/DndManager",
	"steroid/backend/dnd/DropContainer",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dojo/store/Observable",
	"dojo/store/Memory",
	"steroid/backend/STStore",
	"dojo/aspect",
	"steroid/backend/dnd/DraggableJoinRecord",
	"steroid/backend/dnd/StaticRecordItem",
	"dojo/Deferred",
	"dojox/lang/functional",
	"dojo/dom-class",
	"dojo/dom-attr",
	"dojo/_base/lang",
	"dojo/_base/json",
	"dojo/dom-construct"
], function (declare, ContentPane, _DTFormFieldMixin, RecordSearchField, DndManager, DropContainer, i18nRC, ObservableStore, MemoryStore, STStore, aspect, DraggableJoinRecord, StaticRecordItem, Deferred, langFunc, domClass, domAttr, lang, json, domConstruct) {

	return declare([ContentPane, _DTFormFieldMixin], {
		style: 'padding:0;',
		class: 'STRecordSelector STFormField',
		searchField: null,
		recordList: null,
		recordFetchStore: null,
		dndManager: null,
		recordStore: null,
		dndManager: null,
		fieldClassConfig: null, // the record that's actually "meant" to be selected (i.e. the "other side" of a join record)
		directClassConfig: null, // the directly referenced record (can be join record in case of foreign reference)
		values: null,
		fieldRecordFieldName: null,
		dropWatch: null,
		removeAspect: null,
		recordTitle: null,
		items: null,
		isClearing: false,

		isSortable: function () {
			return this.hasMultiple() && !!this.directClassConfig.sortingField;
		},
		hasMultiple: function () {
			return !this.constraints.max || this.constraints.max > 1;
		},
		isRequired: function () {
			return !this.fieldConf.nullable || typeof this.constraints.min == 'undefined' || this.constraints.min > 0;
		},
		postMixInProperties: function () {
			var me = this;

			me.fieldClassConfig = me.backend.getClassConfigFromClassName(me.fieldConf.selectableRecordClassConfig ? me.fieldConf.selectableRecordClassConfig.recordClass : me.fieldConf.recordClass);
			me.directClassConfig = me.backend.getClassConfigFromClassName(me.fieldConf.recordClass);

			if (me.fieldConf.selectableRecordClassConfig) {
				me.fieldRecordFieldName = me.fieldConf.selectableRecordClassConfig.fieldName;
			}

			me.recordFetchStore = new STStore({ // store used to get available records from server
				backend: me.backend,
				classConfig: me.fieldClassConfig
			});

			me.inherited(arguments);
		},
		_setReadOnlyAttr: function (readOnly) {
			var me = this;

			if (me.isReadOnly()) {
				readOnly = true;
			}

			me.inherited(arguments);

			me.addInitListener(function () {
				me.searchField.set('readOnly', readOnly);

				if (me.recordList) {
					me.recordList.set('readOnly', readOnly);
				}
			});
		},
		getRequired: function () {
			var me = this;

			var baseRequired = me.inherited(arguments);

			return baseRequired || me.constraints && me.constraints.min > 0;
		},
		_getValueAttr: function () {
			var me = this;

			if (!me.items) {
				return null;
			}

			if (me.hasMultiple()) {
				var val = [];

				for (var i = 0, item; item = me.items[i]; i++) {
					if (me.fieldConf.selectableRecordClassConfig) {
						var itemObj = {};

						itemObj[ me.fieldConf.selectableRecordClassConfig.fieldName] = item;

						val.push(itemObj);
					} else {
						val.push(item);
					}
				}

				return val;
			}

			return me.items[0];
		},
		_getStateAttr: function () {
			var me = this;

			if ((me.fieldConf.constraints && me.fieldConf.constraints.min)
				&& !((lang.isArray(me.STValue) && me.STValue.length) || (!lang.isArray(me.STValue) && me.STValue))) {
				return 'Incomplete';
			}

			return '';
		},
		createLabel: function () {
			var me = this;

			if (me.form.isFilterPane) {
				me.labelNode = domConstruct.create('label', { 'for': me.id, 'innerHTML': me.getLabel(), class: 'STLabel_' + me.fieldName.replace(':', '-')});

				domConstruct.place(me.labelNode, me.domNode, 'first');
			} else {
				me.inherited(arguments);
			}
		},
		startup: function () {
			var me = this;

			me.createSearchField();

			if (me.hasMultiple()) {
				me.createRecordList();
			}

			me.inherited(arguments);

			me.initComplete();
		},
		_setDisabledAttr: function (disabled) {
			var me = this;

			me.searchField.set('disabled', disabled);

			if (me.recordList) {
				me.recordList.set('disabled', disabled);
			}

			me.inherited(arguments);
		},
		createRecordList: function () {
			var me = this;

			me.dndManager = me.backend.dndManager;

			me.recordList = new DropContainer({
				style: 'float: left;width: auto !important;height: auto !important;padding:10px;margin-bottom:10px;',
				accept: [me.fieldName],
				dndManager: me.dndManager,
				submitName: me.submitName,
				backend: me.backend,
				useIndex: me.fieldConf.useIndex
			});

			me.dropWatch = me.recordList.watch('STValue', function () {
				me.itemChange();
			});

			me.recordStore = new MemoryStore({
				idProperty: 'primary'
			});// used to store the currently selected records

			me.containerNode.appendChild(me.recordList.domNode);
		},
		createSearchField: function () {
			var me = this;

			me.searchField = new RecordSearchField({
				style: 'float: left; margin-right: 8px;',
				readOnly: me.isReadOnly(),
				store: me.recordFetchStore,
				backend: me.backend,
				detailPane: me.detailPane,
				recordStore: me.recordStore,
				required: me.isRequired(),
				name: me.hasMultiple() ? '' : me.submitName,
				autoComplete: !me.hasMultiple(),
				hasMultiple: me.hasMultiple(),
				fieldClassConfig: me.fieldClassConfig,
				mainClassConfig: me.mainClassConfig,
				owningRecordClass: me.owningRecordClass,
				recordSelector: me,
				fieldName: me.fieldName,
				submitName: me.submitName,
				query: {
					mainRecordClass: me.mainClassConfig.className,
					requestFieldName: me.fieldName,
					requestingRecordClass: me.owningRecordClass
				}
			});

			me.searchField.startup();

			me.containerNode.appendChild(me.searchField.domNode);

			me.searchField.addListButton();

			if (!me.hasMultiple()) {
				me.valueNode = me.searchField.valueNode;
			}

			aspect.after(me.searchField, 'itemSelected', function (item) {
				if (me.hasMultiple() && item && item.primary) {
					if (!me.recordStore.get(item.primary)) {
						me.recordStore.add(item);
						me.addToList(item);
					}
				} else {
					me.values = item && item.primary ? item.primary : null;

					me.updateValue();
				}
			});
		},
		addToList: function (item) {
			var me = this;

			if (!item.primary) { // blank
				return;
			}

			var value = {};

			if (me.fieldConf.selectableRecordClassConfig) {
				value[me.fieldConf.selectableRecordClassConfig.fieldName] = item;

				var record = new DraggableJoinRecord({
					backend: me.backend,
					ownClassConfig: me.directClassConfig,
					inlineClassConfig: me.fieldClassConfig,
					inlineSubstitutionFieldName: me.fieldConf.selectableRecordClassConfig.fieldName,
					submitName: me.submitName,
					owningRecordClass: me.owningRecordClass,
					mainClassConfig: me.mainClassConfig,
					dndManager: me.dndManager,
					type: me.fieldName,
					readOnly: me.isReadOnly(),
					showLiveStatus: true
				});
			} else {
				value = item;

				var record = new StaticRecordItem({
					backend: me.backend,
					ownClassConfig: me.directClassConfig,
					submitName: me.submitName,
					owningRecordClass: me.owningRecordClass,
					mainClassConfig: me.mainClassConfig,
					dndManager: me.dndManager,
					type: me.fieldName,
					readOnly: me.isReadOnly()
				});
			}

			record.startup();

			record.addInitListener(function () {
				record.set('value', value);

				record.addValueSetListenerOnce(function () {
					me.recordList.drop(record, true);

//					me.updateValue();

					aspect.before(record, 'destroyRecursive', function () {
						if (!me._beingDestroyed && !this.hasAlreadyBeenDestroyed) {
							me.recordStore.remove(this.inlineRecord ? this.inlineRecord.record.primary : this.record.primary);
//							me.updateValue();
						}
					});
				});
			});
		},
		itemChange: function (name, oldValue, newValue) {
			var me = this;

			me.updateValue();
		},
		updateValue: function () {
			var me = this;

			var skip = false;

			if (me.hasMultiple() && me.recordList.incomingValueCount) {
				return;
			}

			me.addValueSetListenerOnce(function () {
				me.items = [];

				if (me.hasMultiple()) {
					me.values = [];

					me.recordTitle = '';

					me.recordStore.query().forEach(function (item) {
						me.items.push(item);
						me.recordTitle += item.title || item._title + '';
						me.values.push(parseInt(item.primary, 10));
					});

					me.searchField.query.exclude = json.toJson(me.values);
				} else {
					me.values = me.searchField.get('value');

					me.items.push(me.searchField.item);

					me.recordTitle = me.searchField.item ? me.searchField.item.title || me.searchField.item._title : '';
				}

				me.set('STValue', me.STSetValue(me.values));

				me.set('message', '');
			});
		},
		collectTitle: function () {
			var me = this;

			if (me.hasMultiple() && me.items.length) {
				return me.items[0]._title;
			}

			return me.recordTitle;
		},
		labelModifier: function (label) {
			var me = this;

			label = me.inherited(arguments);

			if (me.hasMultiple()) {
				label += i18nRC.multipleSelection;
			} else {
				label += i18nRC.singleSelection;
			}

			return label;
		},
		clear: function () {
			var me = this;

			me.values = [];

			me.searchField.set('value', null);

			if (me.hasMultiple()) {
				if (!me.STValue) {
					me.recordList.originalItems = [];
				}

				me.recordList.reset(true);

				me.resetStore();
			}

			delete me.STValue;
		},
		reset: function () {
			var me = this;

			me.values = [];

			me.searchField.reset();

			if (me.hasMultiple()) {
				me.recordList.reset();

				me.resetStore();
			}

			if (me.dndManager) {
				me.dndManager.reset();
				me.dndManager.registerContainer(me.recordList);
			}

			delete me.originalValue;
			delete me.STValue;
		},
		resetStore: function () {
			var me = this;

			me.recordStore.query().forEach(function (item) {
				me.recordStore.remove(item.primary);
			});
		},
		_setValueAttr: function (value) {
			var me = this;

			me.addInitListener(function () {
				if (me.isClearing) {
					return;
				}

				if (lang.isArray(value) && value[0] == null) {
					value = null;
				}

				if (value === null) {
					me.isClearing = true;
					me.clear();
					me.isClearing = false;
					me.valueComplete();
					me.updateValue();
					if (me.recordList) {
						me.recordList.originalItems = [];
					}

					return;
				}

				var recordListDef = null;

				if (value && me.hasMultiple()) {
					me.recordList.incomingValueCount = langFunc.keys(value).length;

					for (var i in value) {
						me.recordStore.add(value[i][me.fieldConf.selectableRecordClassConfig.fieldName]);
						me.addToList(value[i][me.fieldConf.selectableRecordClassConfig.fieldName]);
					}

					me.recordList.addValueSetListenerOnce(function () {
						me.valueComplete();
						me.updateValue();
					});
				} else {
					me.values = null;

					me.searchField.set('item', value);

					if (me.hasMultiple() && !me.recordList.originalItems) {
						me.recordList.originalItems = [];
					}

					me.valueComplete();
					me.updateValue();
				}
			});
		},
		getDirtyNess: function () {
			var me = this;

			if (me.hasMultiple()) {
				return me.recordList.getDirtyNess();
			}

			return me.inherited(arguments);
		},
		compareArray: function (a, b) {
			var dirtyNess = 0;

			if (a) {
				for (var i = 0; i < a.length; i++) {
					if (!b || !b[i] || b[i] != a[i]) {
						dirtyNess++;
					}
				}
			}

			return dirtyNess;
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

			if (me.hasMultiple()) {
				me.recordList.setSubmitName(setName);
			} else {
				me.searchField.setSubmitName(setName);
			}
		},
		updateSubmitName: function (submitName) {
			var me = this;

			me.submitName = submitName;

			if (me.hasMultiple()) {
				me.recordList.updateSubmitName(me.submitName);
			} else {
				me.searchField.updateSubmitName(me.submitName);
			}
		},
		destroy: function () {
			var me = this;

			me.searchField.destroyRecursive();

			if (me.hasMultiple()) {
				me.recordList.destroyRecursive();

				me.dndManager.destroyRecursive();
			}

			delete me.recordFetchStore;

			delete me.fieldClassConfig;
			delete me.directClassConfig;

			if (me.dropWatch) {
				me.dropWatch.unwatch();
				delete me.dropWatch;
			}

			delete me.recordStore;

			delete me.items;
			delete me.values;
			delete me.recordTitle;
			delete me.submitName;

			me.inherited(arguments);
		}
	});
});
