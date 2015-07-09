define([
	"dojo/_base/declare",
	"steroid/backend/ListPane",
	"steroid/backend/mixin/_ListPaneFilterable",
	"dojo/aspect",
	"dojo/dom-class",
	"dojox/lang/functional"
], function (declare, ListPane, _ListPaneFilterable, aspect, domClass, langFunc) {
	return declare([], {

		listPane: null,
		isRecordSelector: false,
		selectAspect: null,
		applyAspect: null,
		afterNewRecordAspect: null,
		beforeNewRecordAspect: null,
		listCloseAspect: null,
		beforeRecordLoadAspect: null,
		cancelLoadRecordAspect: null,

		postCreate: function () {
			var me = this;

			me.inherited(arguments);

			var mixins = [ListPane];

			if (me.classConfig.filterFields && langFunc.keys(me.classConfig.filterFields).length) {
				mixins.push(_ListPaneFilterable);
			}

			var customList = declare(mixins, {
				style: 'width: 100%;height: 100%;overflow:hidden;padding:0;',
				region: 'left',
				splitter: false,
				gutters: false,
				"class": 'STModuleContainerList',
				hasMultiple: me.hasMultiple,
				isRecordSelector: me.isRecordSelector,
				backend: me.backend,
				fetchRecordOnSelect: me.fetchRecordOnSelect,
				moduleContainer: me,
				classConfig: me.classConfig,
				query: me.baseQuery || {}
			});

			me.listPane = new customList({});

			if (!me.classConfig.listOnly) {
				me.selectAspect = aspect.after(me.listPane, 'recordSelected', function (record) {
					me.recordSelected(record);
				});

				me.applyAspect = aspect.after(me.listPane, 'applySelection', function (records) {
					me.applySelection(records);
				});

				me.afterNewRecordAspect = aspect.after(me.listPane, 'newRecord', function (method, options) {
					me.newRecord(options);
				});

				me.beforeNewRecordAspect = aspect.before(me.listPane, 'newRecord', function (options) {
					return me.beforeNewRecord(options);
				});
			}

			me.listCloseAspect = aspect.after(me.listPane, 'close', function (record) {
				me.close();
			});

			me.addChild(me.listPane);
		},
		beforeRecordSelected: function (options) {
			var me = this;

			if (me.detailPane && me.detailPane.record && me.detailPane.record.primary && me.detailPane.record.primary !== 'new') {
				options.previousEditedRecordClass = me.detailPane.classConfig.className;
				options.previousEditedRecordID = me.detailPane.record.primary;
			}

			return options;
		},
		beforeRecordLoad: function (currentRecord) {
			var me = this, el;

			if (el = me.listPane.view.row(currentRecord.primary).element) {
				domClass.remove(el, 'STRecordBeingEdited');
			}
		},
		applySelection: function (records) {
			return records;
		},
		detailPaneClosed: function () {
			var me = this;

			me.listPane.maximize();
			me.listPane.view.clearSelection();
			me.resize();
		},
		detailActionSuccess: function (action) {
			var me = this;

			if (action !== 'previewRecord') {
				me.listPane.view.refresh();
			}
		},
		buildDetailPane: function () {
			var me = this;

			me.inherited(arguments);

			me.detailPane.form.addValueSetListener(function () {
				me.listPane.hideStandBy();
			});

			me.beforeRecordLoadAspect = aspect.after(me.detailPane, 'beforeRecordLoad', function (currentRecord) {
				me.beforeRecordLoad(currentRecord);
			});

			me.cancelLoadRecordAspect = aspect.after(me.detailPane, 'cancelLoadRecord', function () {
				me.listPane.hideStandBy();
			});
		},
		afterStartup: function () {
			// do nothing here
		},
		finishClose: function () {
			var me = this;

			me.listPane.destroyRecursive();

			me.inherited(arguments);
		},
		recordSelected: function (records) {
			var me = this;

			if (!me.isRecordSelector) {
				me.editRecord(records[0]);
			}

			return records;
		},
		beforeNewRecord: function (options) {
			var me = this;

			if (me.detailPane) {
				options.previousEditedRecordClass = me.detailPane.classConfig.className;
				options.previousEditedRecordID = me.detailPane.record.primary;
			}

			return options;
		},
		newRecord: function (options) {
			var me = this;

			me.backend.doStandBy(me);

			me.listPane.minimize();

			me.layout();

			return me.inherited(arguments);
		},
		editRecord: function (record) {
			var me = this;

			me.backend.doStandBy(me);

			me.inherited(arguments);

			me.listPane.minimize();

			me.layout();

			me.listPane.doStandBy();
		},
		recordSaved: function (record, isChainedAction) {
			var me = this;

			if (!isChainedAction) {
				me.doSpecialActions(record);
			}

			if (me.isRecordSelector) {
				me.createdByField.addItemsFromList(record);
			} else {
				me.detailPane.set('value', record);

				if (!isChainedAction) {
					me.listPane.refreshAndSelect(record);
				}
			}
		},
		recordDeleted: function () {
			var me = this;

			me.inherited(arguments);

			me.listPane.view.refresh();
		},
		recordHidden: function () {
			var me = this;

			me.inherited(arguments);

			me.listPane.view.refresh();
		},
		recordPublished: function (record) {
			var me = this;

			me.inherited(arguments);

			me.listPane.view.refresh();
		},
		languageSwitched: function () {
			var me = this;

			me.listPane.view.refresh();

			me.inherited(arguments);
		},
		domainGroupSwitched: function (domainGroup) {
			var me = this;

			me.listPane.view.refresh();

			me.inherited(arguments);

			me.listPane.domainGroupSwitched(domainGroup);
		},
		recordIsLockedException: function () {
			var me = this;

			me.inherited(arguments);

			me.listPane.hideStandBy();
			me.listPane.view.refresh();
		},
		endEditingWithPreviousRecord: function (sync, rec, className) {
			var me = this, row;

			me.listPane.endEditingWithPreviousRecord(rec);

			me.inherited(arguments);
		},
		destroy: function () {
			var me = this;

			me.inherited(arguments);

			me.listPane.destroyRecursive();
			delete me.listPane;

			me.selectAspect.remove();
			delete me.selectAspect;

			me.applyAspect.remove();
			delete me.applyAspect;

			me.afterNewRecordAspect.remove();
			delete me.afterNewRecordAspect;

			me.beforeNewRecordAspect.remove();
			delete me.beforeNewRecordAspect;

			me.listCloseAspect.remove();
			delete me.listCloseAspect;

			if (!me.isRecordSelector) {
				me.beforeRecordLoadAspect.remove();
				delete me.beforeRecordLoadAspect;

				me.cancelLoadRecordAspect.remove();
				delete me.cancelLoadRecordAspect;
			}
		}
	});
});