define([
	"dojo/_base/declare",
	"steroid/backend/datatype/_DTForeignReference",
	"steroid/backend/datatype/form/_DTFormFieldMixin",
	"dojo/_base/lang",
	"dojox/lang/functional",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dojo/i18n!steroid/backend/nls/Permissions",
	"dijit/layout/ContentPane",
	"dojo/Deferred",
	"dojo/DeferredList",
	"dojo/dom-construct",
	"dojo/dom-class",
	"dijit/form/Button",
	"dojo/_base/event",
	"dojo/dom-style",
	"dojo/_base/array",
	"steroid/backend/dnd/PermissionEntityRecord",
	"dijit/layout/TabContainer"
], function (declare, _DTForeignReference, _DTFormFieldMixin, lang, langFunc, i18nRC, i18nPerm, ContentPane, Deferred, DeferredList, domConstruct, domClass, Button, event, domStyle, array, PermissionEntityRecord, TabContainer) {

	return declare([ContentPane, _DTForeignReference, _DTFormFieldMixin], {
		isSortable: false,
		isStatic: false,
		hasJoin: false,
		dataTypes: null,
		editableRecordConfig: null,
		recordGroupContainers: null,
		recordGroupMap: null,
		"class": 'STStaticInlineEdit',
		formWatchHandles: null,
		dirtyFields: null,
		subFormFields: null,
		enabledCount: 0,
		changeWatches: null,
		permissionEntities: null,
		tabContainer: null,

		constructor: function () {
			this.editableRecordConfig = {};
			this.dataTypes = {};
			this.recordGroupContainers = {};
			this.recordGroupMap = {};
			this.formWatchHandles = [];
			this.dirtyFields = [];
			this.subFormFields = [];
			this.changeWatches = [];
			this.permissionEntities = [];
		},
		postCreate: function () {
			var me = this;

			me.editableRecordConfig = me.backend.getClassConfigFromClassName(me.fieldConf.editableRecordClassConfig ? me.fieldConf.editableRecordClassConfig.recordClass : me.fieldConf.recordClass);
			me.editableRecordConfig.prefilledInstances = me.backend.config.recordClasses;
			me.hasJoin = true;
			me.isSortable = false;
			me.isStatic = true;

			me.inherited(arguments);

			me.init();
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.initComplete();
		},
		resetOriginalValue: function () {
			var me = this;

			me.inherited(arguments);

			for (var recordGroup in me.recordGroupContainers) {
				me.recordGroupContainers[recordGroup].destroyRecursive();
			}

			for (var i = 0; i < me.formWatchHandles.length; i++) {
				me.formWatchHandles[i].unwatch();
			}

			me.dirtyFields = [];
			me.subFormFields = [];

			me.enabledCount = 0;

			me.init();
		},
		_setReadOnlyAttr: function (readOnly) {
			var me = this;

			me.inherited(arguments);

			me.addInitListener(function () {
				for (var group in me.recordGroupContainers) {
					for (var className in me.recordGroupContainers[group].fieldContainers) {
						var permissionRecord = me.recordGroupContainers[group].fieldContainers[className];

						permissionRecord.set('readOnly', readOnly);
					}
				}
			});
		},
		recordIsEditable: function (record) {
			var me = this;

			return record.hasPrimaryField && record.mayWrite && !record.isDependency;
		},
		init: function () {
			var me = this;

			if (!me.tabContainer) {
				me.tabContainer = new TabContainer({
					"class": 'STPermissionTabContainer',
					doLayout: false
				});

				me.tabContainer.startup();

				me.domNode.appendChild(me.tabContainer.domNode);
			}

			var groups = [];

			for (var recordGroup in me.editableRecordConfig.prefilledInstances) {
				if (!me.backend.debugMode && (recordGroup == 'system')) {
					continue;
				}

				var hasEditable = false;

				for (var i = 0; i < me.editableRecordConfig.prefilledInstances[recordGroup].length; i++) {
					var record = me.editableRecordConfig.prefilledInstances[recordGroup][i];

					if (me.recordIsEditable(record)) {
						hasEditable = true;
						break;
					}
				}

				var records = [];

				for (var i = 0, ilen = me.editableRecordConfig.prefilledInstances[recordGroup].length; i < ilen; i++) {
					var record = me.editableRecordConfig.prefilledInstances[recordGroup][i];

					records.push({
						record: record,
						label: (record.i18nExt && record.i18nExt[record.className + '_name']) ? (record.i18nExt[record.className + '_name']) : (i18nRC[record.className + '_name'] || record.className)
					});
				}

				records.sort(function (a, b) {
					return a.label > b.label ? 1 : a.label == b.label ? 0 : -1;
				});

				var title = i18nRC['type_' + recordGroup];

				var groupConf = {
					records: records,
					name: recordGroup,
					title: title,
					hasEditable: hasEditable
				};

				groups.push(groupConf);
			}

			groups.sort(function (a, b) {
				return a.title > b.title ? 1 : a.title == b.title ? 0 : -1;
			});

			for (var i = 0, ilen = groups.length; i < ilen; i++) {
				var records = groups[i].records;

				var recordGroupContainer = new ContentPane({
					title: groups[i].title,
					recordGroup: groups[i].name,
					fieldContainers: {},
					"class": 'STPermissionGroup',
					disabled: !groups[i].hasEditable
				});

				me.tabContainer.addChild(recordGroupContainer);

				me.recordGroupContainers[groups[i].name] = recordGroupContainer;

				for (var j = 0, jlen = records.length; j < jlen; j++) {
					var record = records[j].record;

					me.recordGroupMap[record.className] = groups[i].name;

					var permissionEntity = new PermissionEntityRecord({
						backend: me.backend,
						ownClassConfig: me.editableRecordConfig,
						i18nExt: record.i18nExt || i18nRC,
						permissionRecordClass: record.className,
						mainClassConfig: me.mainClassConfig,
						label: records[j].label,
						backendType: groups[i].name
					});

					me.permissionEntities.push(permissionEntity);

					permissionEntity.startup();

//					if(!me.recordIsEditable(record)){
//						domClass.add(permissionEntity.domNode, 'non-editable');
//					}

					recordGroupContainer.containerNode.appendChild(permissionEntity.domNode);
					recordGroupContainer.fieldContainers[record.className] = permissionEntity;
				}
			}

			var permissionCount = me.permissionEntities.length;

			for (var i = 0, item; item = me.permissionEntities[i]; i++) {
				item.addInitListener(function () {
					permissionCount--;

					if (!permissionCount) {
						me.initComplete();
					}
				});
			}
		},
		initComplete: function () {
			var me = this;

			me.inherited(arguments);

			for (var i = 0, item; item = me.permissionEntities[i]; i++) {
				item.addInitListener(function (entityWithValue) {
					entityWithValue.updateSubmitName('permission:RCPermissionPermissionEntity[' + i + '][permissionEntity]');
				});

				item.addValueSetListenerOnce(function (permEnt) {
					me.changeWatches.push(permEnt.watch('STValue', function (name, oldValue, newValue) {
						me.set('STValue', me.get('value'));
					}));
				});
			}
		},
		reset: function () {
			var me = this;

			for (var group in me.recordGroupContainers) {
				for (var className in me.recordGroupContainers[group].fieldContainers) {
					var permissionRecord = me.recordGroupContainers[group].fieldContainers[className];

					permissionRecord.reset();
				}
			}

			me.inherited(arguments);
		},
		setPermissionValues: function (value) {
			var me = this;

			var valueCount = me.permissionEntities.length;

			for (var i = 0, item; item = me.permissionEntities[i]; i++) {
				var permVal = {};

				for (var j in value) {
					if (value[j].permissionEntity.recordClass == item.permissionRecordClass) {
						permVal = value[j];
					}
				}

				item.set('value', permVal);

				item.addValueSetListenerOnce(function () {
					valueCount--;

					if (!valueCount) {
						me.valueComplete();
					}
				});
			}
		},
		_setValueAttr: function (value) {
			var me = this;

			me.addInitListener(function () {
				me.setPermissionValues(value);
			});
		},
		_getValueAttr: function () {
			var me = this;

			var val = {};

			for (var group in me.recordGroupContainers) {
				for (var className in me.recordGroupContainers[group].fieldContainers) {
					var permissionRecord = me.recordGroupContainers[group].fieldContainers[className];

					val[permissionRecord.permissionRecordClass] = permissionRecord.get('value');
				}
			}

			return val;
		},
		getDirtyNess: function () {
			var me = this;

			for (var group in me.recordGroupContainers) {
				for (var className in me.recordGroupContainers[group].fieldContainers) {
					var permissionRecord = me.recordGroupContainers[group].fieldContainers[className];

					var dirtyNess = permissionRecord.getDirtyNess();

					if (dirtyNess) {
						return dirtyNess;
					}
				}
			}

			return 0;
		},
		setSubmitName: function (setName) {
			var me = this;

			for (var group in me.recordGroupContainers) {
				for (var className in me.recordGroupContainers[group].fieldContainers) {
					var permissionRecord = me.recordGroupContainers[group].fieldContainers[className];

					var doSubmit = permissionRecord.isDependency && (permissionRecord.getDirtyNess() < 1) ? false : setName;

					permissionRecord.setSubmitName(doSubmit);
				}
			}

		},
		destroy: function () {
			var me = this;

			me.tabContainer.destroyRecursive();

			delete me.recordGroupContainers;

			for (var i = 0; i < me.changeWatches.length; i++) {
				me.changeWatches[i].unwatch();
			}

			delete me.changeWatches;

			delete me.editableRecordConfig;
			delete me.hasJoin;
			delete me.isSortable;
			delete me.isStatic;

			me.inherited(arguments);
		}
	});
});
