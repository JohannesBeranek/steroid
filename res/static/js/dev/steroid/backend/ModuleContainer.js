define([
	"dojo/_base/declare",
	"dijit/layout/BorderContainer",
	"steroid/backend/DetailPane",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dojo/Deferred",
	"dojo/aspect",
	"steroid/backend/mixin/_hasStandBy",
	"dojo/_base/lang"
], function (declare, BorderContainer, DetailPane, i18nRC, Deferred, aspect, _hasStandBy, lang) {
	return declare([BorderContainer, _hasStandBy], {

		closingDef: null,
		classConfig: null,
		isClosing: false,
		hasMultiple: false,
		splitter: false,
		gutters: false,
		detailPane: null,
		backend: null,
		afterDetailCloseAspect: null,
		beforeDetailCloseAspect: null,
		actionSuccessAspect: null,
		i18nExt: null,
		newRecordOnInit: null,
		loadRecordOnInit: null,
		fetchRecordOnSelect: true,

		postCreate: function () {
			var me = this;

			me.inherited(arguments);

			me.standByNode = me.domNode;
		},
		detailPaneClosed: function () {
			var me = this;

			me.close();
		},
		detailActionSuccess: function (action) {
			//stub
		},
		createDetailPane: function () {
			var me = this;

			var mixins = [DetailPane];

			if (me.classConfig.customJS && me.classConfig.customJS.indexOf('detailPane') !== -1) {
				var path;

				if (me.classConfig.isCore) {
					path = "steroid/backend/detailPane/" + me.classConfig.className;
				} else {
					path = me.classConfig.classLocation + "/res/static/js/detailPane/" + me.classConfig.className;
				}

				require([path], function (customPane) {
					mixins.push(customPane);

					me.buildDetailPane(mixins);
				});
			} else {
				me.buildDetailPane(mixins);
			}
		},
		buildDetailPane: function (customPane) {
			var me = this;

			var pane = declare(customPane, {});

			me.detailPane = new pane({
				style: 'border: 0;overflow:hidden;padding:0;',
				region: 'center',
				class: 'STDetailPane',
				splitter: false,
				gutters: false,
				moduleContainer: me,
				backend: me.backend,
				classConfig: me.classConfig,
				i18nExt: me.i18nExt
			});

			me.beforeDetailCloseAspect = aspect.before(me.detailPane, 'close', function () {
				me.endEditing();
			});

			me.afterDetailCloseAspect = aspect.after(me.detailPane, 'close', function (closingDef) {
				if (!me.isClosing) {
					dojo.when(me.detailPane.closingDef, function () {
						delete me.detailPane;
						me.detailPaneClosed();
					});
				}

				return closingDef;
			});

			me.actionSuccessAspect = aspect.after(me.detailPane, 'actionSuccess', function (action, response) {
				if (!action || typeof action === 'object') {
					response = response[1];
					action = response[0];
				}

				if (response) {
					me.detailActionSuccess(action);

					if (action !== 'previewRecord') {
						if (action === 'deleteRecord' || action === 'duplicateRecord') {
							me.detailPane.close(true);
						} else {
							me.detailPane.suspendDirtyCheck = true;
							me.detailPane.loadRecord(response.data);
						}
					}
				}
			});

			me.addChild(me.detailPane);

			me.detailPane.addInitListener(function () {
				if (me.newRecordOnInit) {
					me.detailPane.newRecord(me.newRecordOnInit);
					delete me.newRecordOnInit;
				} else if (me.loadRecordOnInit) {
					me.detailPane.loadRecord(me.loadRecordOnInit);
					delete me.loadRecordOnInit;
				}
			});
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.afterStartup();
		},
		afterStartup: function () {
			var me = this;

			me.newRecord();
		},
		finishClose: function () {
			var me = this;

			me.hasClosed();

			me.closingDef.resolve();
			me.isClosing = false;
		},
		close: function (force) {
			var me = this;

			me.isClosing = true;

			me.closingDef = new Deferred();

			if (me.detailPane) {
				var dpcd = me.detailPane.close(force);

				dojo.when(dpcd, function () {
					delete me.detailPane;

					me.finishClose();
				}, function (error) {
					me.isClosing = false;
				});

			} else {
				me.finishClose();
			}

			return me.closingDef;

		},

		hasClosed: function () {
			// stub
		},
		newRecord: function (options) {
			var me = this;

			options = options || {};

			if (options.recordClass) {
				options.recordClass = me.classConfig.className;
			}

			if (!me.detailPane) {
				me.newRecordOnInit = options || {};
				me.createDetailPane();
			} else {
				me.detailPane.newRecord(options);
			}
		},
		editRecord: function (record) {
			var me = this;

			if (!me.detailPane) {
				me.loadRecordOnInit = record;
				me.createDetailPane();
			} else {
				me.detailPane.loadRecord(record);
			}
		},
		doSpecialActions: function (record) {
			var me = this;

			if (me.classConfig.className == 'RCDomainGroup') {
				//TODO
				return;
			}

			if (me.classConfig.className == 'RCLanguage') {
				//TODO
				return;
			}
		},
		languageSwitched: function () {
			var me = this;

			if (me.detailPane) {
				me.detailPane.close();
			}
		},
		endEditing: function (sync) {
			var me = this;

			if (me.detailPane) {
				var rec = me.detailPane.record;
				var className = me.detailPane.classConfig.className;

				if (rec && rec.primary && rec.primary !== 'new') {
					me.endEditingWithPreviousRecord(sync, rec, className);
				}
			}
		},
		endEditingWithPreviousRecord: function (sync, rec, className) {
			var me = this;

			me.backend.STServerComm.endEditing(className, rec, sync);
		},
		storeGetError: function () {
			var me = this;

			if (me.detailPane) {
				me.detailPane.close();
			}
		},
		domainGroupSwitched: function (domainGroup) {
			var me = this;

			if (me.detailPane) {
				me.detailPane.close(true);
			}
		},
		recordIsLockedException: function () {
			//stub
		},
		recordSaved: function (record, isChainedAction) {
			var me = this;

			if (!isChainedAction) {
				me.doSpecialActions(record);
			}

			me.detailPane.set('value', record);
		},
		recordDeleted: function () {
			var me = this;

			me.doSpecialActions();
		},
		recordHidden: function () {
			// stub
		},
		recordPublished: function (record) {
			var me = this;

			me.doSpecialActions(record);
		},
		destroy: function () {
			var me = this;

			if (me._beingDestroyed) {
				me.inherited(arguments);
				return;
			}

			if (me.detailPane) {
				me.detailPane.destroyRecursive();
				delete me.detailPane;

				me.afterDetailCloseAspect.remove();
				delete me.afterDetailCloseAspect;

				me.beforeDetailCloseAspect.remove();
				delete me.beforeDetailCloseAspect;

				me.actionSuccessAspect.remove();
				delete me.actionSuccessAspect;
			}

			if (me.closingDef.fired < 0) {
				me.closingDef.resolve();
				delete me.closingDef;
			}

			delete me.backend;

			me.inherited(arguments);
		},
	});
});