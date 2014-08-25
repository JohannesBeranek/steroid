define([
	"dojo/_base/declare",
	"dijit/Dialog",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dojo/i18n!steroid/backend/nls/Errors",
	"dijit/form/Button",
	"dojo/dom-construct",
	"dijit/TitlePane",
	"dojox/layout/TableContainer",
	"dijit/layout/ContentPane",
	"dojox/lang/functional",
	"dojo/on",
	"dojo/dom-attr",
	"dojo/aspect"
], function (declare, Dialog, i18nRC, i18nErr, Button, domConstruct, TitlePane, TableContainer, ContentPane, langFunc, on, domAttr, aspect) {
	return declare([Dialog], {
		backend: null,
		response: null,
		onYes: null,
		onClose: null,
		lastAction: null,
		domainGroupContainers: null,
		class: 'STReferenceDialog',
		messagesForOtherDomainGroups: null,
		checkboxes: null,
		labels: null,
		cbListeners: null,
		recordsDisplayed: null,
		childContainers: null,
		mainRecordClass: null,
		mainRecordID: null,
		recordsSelected: null,

		constructor: function () {
			this.response = {};
			this.onYes = null;
			this.onClose = null;
			this.lastAction = '';
			this.domainGroupContainers = {};
			this.checkboxes = [];
			this.labels = [];
			this.cbListeners = [];
			this.recordsDisplayed = [];
			this.childContainers = [];
			this.recordsSelected = [];

			this.inherited(arguments);
		},
		formatReferenceMessage: function (data) {
			var me = this;

			me.messagesForOtherDomainGroups = {};

			if (!langFunc.keys(data).length) {
				me.contentArea.appendChild(domConstruct.toDom('<p>' + i18nErr.confirm.message + '</p>'));
				return;
			}

			if (data.optional && data.required) { //publish
				me.createPublishDialog(data);
			} else {
				me.createHideDialog(data);
			}
		},
		createPublishDialog: function (data) {
			var me = this;

			me.createHideDialog(data.required, true);

			data = data.optional;

			for (var i in data) {
				// i == domainGroup name
				var isOwnDomainGroup = i === me.backend.config.system.domainGroups.current._title;

				if (!isOwnDomainGroup) {
					continue;
				}

				var domainGroupContainer = new TitlePane({
					title: i + ' ' + i18nErr.optional,
					open: false,
					tableContainer: null,
					recordClassContainers: {},
					style: 'min-width: 600px'
				});

				me.domainGroupContainers[i + '_opt'] = domainGroupContainer;

				domainGroupContainer.startup();

				me.contentArea.appendChild(domainGroupContainer.domNode);

				domainGroupContainer.tableContainer = new TableContainer({
					orientation: 'horiz',
					labelWidth: '25%'
				});

				domainGroupContainer.tableContainer.startup();

				domainGroupContainer.containerNode.appendChild(domainGroupContainer.tableContainer.domNode);

				for (var j in data[i]) {
					// j == recordClass name
					var classConf = me.backend.getClassConfigFromClassName(j);
					var label = i18nRC[j + '_name'] || classConf.i18nExt[j + '_name'];

					var cp = new ContentPane({
						label: label
					});

					cp.startup();

					domainGroupContainer.recordClassContainers[j + '_opt'] = cp;

					for (var k in data[i][j]) {
						if (me.recordsDisplayed.indexOf(j + '_' + data[i][j][k].primary) == -1) {
							me.addOptional(data[i][j][k], j, cp.containerNode);
						}
					}

					domainGroupContainer.tableContainer.addChild(domainGroupContainer.recordClassContainers[j + '_opt']);
				}

				domainGroupContainer.tableContainer._initialized = false;
				domainGroupContainer.tableContainer._started = false;
				domainGroupContainer.tableContainer.startup();
			}
		},
		addOptional: function (record, recordClass, containerNode) {
			var me = this;

			// k == record name
			var title = record.title;

			title = title.replace(/(\#RC[a-zA-Z]*\#)/g, function (str, p1) {
				var tmp = p1.replace(/\#/gi, '');
				var classConf2 = me.backend.getClassConfigFromClassName(tmp);

				return (i18nRC[tmp + '_name'] || classConf2.i18nExt[tmp + '_name']);
			});

			var cb = domConstruct.create('input', { type: 'checkbox', 'data-rc': recordClass, 'data-primary': record.primary, id: recordClass + '_' + record.primary});

			me.cbListeners.push(on(cb, 'change', function (e) {
				var checked = domAttr.get(e.target, 'checked');
				var rc = domAttr.get(e.target, 'data-rc');
				var primary = domAttr.get(e.target, 'data-primary');

				if (checked) {
					me.loadReferences(rc, primary, e.target);
					me.recordsSelected.push(rc + '_' + primary);
				} else {
					me.removeReferences(rc, primary);
					var idx = me.recordsSelected.indexOf(rc + '_' + primary);

					if (idx > -1) {
						me.recordsSelected.splice(idx, 1);
					}
				}
			}));

			me.checkboxes.push(cb);

			me.recordsDisplayed.push(recordClass + '_' + record.primary);

			var label = domConstruct.create('label', { for: recordClass + '_' + record.primary, innerHTML: title, id: 'label_' + recordClass + '_' + record.primary });

			me.labels.push(label);

			containerNode.appendChild(cb);
			containerNode.appendChild(label);
		},
		loadReferences: function (rc, primary, cb) {
			var me = this;

			me.backend.doStandBy();

			var conf = {
				data: {
					requestType: 'getPublishableReferences',
					recordClass: rc,
					recordID: primary,
				},
				success: function (response) {
					me.backend.hideStandBy();

					if (response && response.data && response.data.items) {
						me.addReferences(response.data.items, cb);
					} else {
						me.backend.hideStandBy();
						me.backend.showError(response)
					}
				},
				error: function (response) {
					me.backend.hideStandBy();
					me.backend.showError(response)
				}
			};

			me.backend.STServerComm.sendAjax(conf);
		},
		addReferences: function (items, cb) {
			var me = this;

			var childContainer = new TableContainer({
				orientation: 'horiz',
				labelWidth: '15%',
				style: 'margin-left: 10px',
				parentCB: domAttr.get(cb, 'id')
			});

			childContainer.startup();

			domConstruct.place(childContainer.domNode, dojo.byId('label_' + domAttr.get(cb, 'data-rc') + '_' + domAttr.get(cb, 'data-primary')), 'after');

			for (var domainGroup in items['required']) {
				for (var recordClass in items['required'][domainGroup]) {
					var entries = [];

					var classConf = me.backend.getClassConfigFromClassName(recordClass);
					var label = (classConf.widgetType ? 'Widget ' : '') + (i18nRC[recordClass + '_name'] || classConf.i18nExt[recordClass + '_name']) + (classConf.widgetType ? ' in' : '');

					var recordContainer = new ContentPane({
						label: label,
						recordsDisplayed: []
					});

					var asp = aspect.before(recordContainer, 'destroy', function () {
						for (var i = 0, ilen = this.recordsDisplayed.length; i < ilen; i++) {
							var idx = me.recordsDisplayed.indexOf(this.recordsDisplayed[i]);

							if (idx > -1) {
								me.recordsDisplayed.splice(idx, 1);
							}
						}

						this.asp.remove();
						delete this.asp;
					});

					recordContainer.asp = asp;

					for (var record in items['required'][domainGroup][recordClass]) {
						if (me.recordsDisplayed.indexOf(recordClass + '_' + items['required'][domainGroup][recordClass][record].primary) == -1) {
							entries.push(me.addNormal(items['required'][domainGroup][recordClass][record], recordClass, true));
							recordContainer.recordsDisplayed.push(recordClass + '_' + items['required'][domainGroup][recordClass][record].primary);
						}
					}

					entries = entries.join('');

					if (entries.length) {
						childContainer.addChild(recordContainer);
						recordContainer.containerNode.appendChild(domConstruct.toDom(entries));
					}
				}
			}

			for (var domainGroup in items['optional']) {
				for (var recordClass in items['optional'][domainGroup]) {
					var classConf = me.backend.getClassConfigFromClassName(recordClass);
					var label = (classConf.widgetType ? 'Widget ' : '') + (i18nRC[recordClass + '_name'] || classConf.i18nExt[recordClass + '_name']) + (classConf.widgetType ? ' in' : '');

					var recordContainer = new ContentPane({
						label: label,
						recordsDisplayed: []
					});

					var asp = aspect.before(recordContainer, 'destroy', function () {
						for (var i = 0, ilen = this.recordsDisplayed.length; i < ilen; i++) {
							var idx = me.recordsDisplayed.indexOf(this.recordsDisplayed[i]);

							if (idx > -1) {
								me.recordsDisplayed.splice(idx, 1);
							}

							var idx = me.recordsSelected.indexOf(this.recordsDisplayed[i]);

							if (idx > -1) {
								me.recordsSelected.splice(idx, 1);
							}
						}

						this.asp.remove();
						delete this.asp;
					});

					recordContainer.asp = asp;

					childContainer.addChild(recordContainer);

					var hasRecords = false;

					for (var record in items['optional'][domainGroup][recordClass]) {
						if (me.recordsDisplayed.indexOf(recordClass + '_' + items['optional'][domainGroup][recordClass][record].primary) == -1) {
							hasRecords = true;

							me.addOptional(items['optional'][domainGroup][recordClass][record], recordClass, recordContainer.containerNode);

							recordContainer.recordsDisplayed.push(recordClass + '_' + items['optional'][domainGroup][recordClass][record].primary);
						}
					}

					if (!hasRecords) {
						childContainer.removeChild(recordContainer);
					}
				}
			}

			childContainer._initialized = false;
			childContainer._started = false;
			childContainer.startup();

			me.childContainers.push(childContainer);
		},
		removeReferences: function (rc, primary) {
			var me = this;

			for (var i = 0, ilen = me.childContainers.length; i < ilen; i++) {
				if (me.childContainers[i].parentCB == rc + '_' + primary) {
					me.childContainers[i].destroyRecursive();
					me.childContainers.splice(i, 1);
					break;
				}
			}

			me.resize();
		},
		createHideDialog: function (data, required) {
			var me = this;

			for (var i in data) {
				var isOwnDomainGroup = i === me.backend.config.system.domainGroups.current._title;

				if (!isOwnDomainGroup) {
					me.messagesForOtherDomainGroups[i] = {};
				}

				var domainGroupContainer = new TitlePane({
					title: i + (required ? ' ' + i18nErr.required : ''),
					open: isOwnDomainGroup,
					tableContainer: null,
					recordClassContainers: {},
					style: 'min-width: 600px'
				});

				me.domainGroupContainers[i] = domainGroupContainer;

				domainGroupContainer.startup();

				me.contentArea.appendChild(domainGroupContainer.domNode);

				domainGroupContainer.tableContainer = new TableContainer({
					orientation: 'horiz',
					labelWidth: '15%'
				});

				domainGroupContainer.tableContainer.startup();

				domainGroupContainer.containerNode.appendChild(domainGroupContainer.tableContainer.domNode);

				for (var j in data[i]) {
					var classConf = me.backend.getClassConfigFromClassName(j);
					var label = (classConf.widgetType ? 'Widget ' : '') + (i18nRC[j + '_name'] || classConf.i18nExt[j + '_name']) + (classConf.widgetType ? ' in' : '');

					if (!isOwnDomainGroup && !me.messagesForOtherDomainGroups[i][label]) {
						me.messagesForOtherDomainGroups[i][label] = [];
					}

					var content = [];

					for (var k in data[i][j]) {
						if (me.recordsDisplayed.indexOf(j + '_' + data[i][j][k].primary) == -1) {
							content.push(me.addNormal(data[i][j][k], j, required));
						}
					}

					content = content.join('');

					domainGroupContainer.recordClassContainers[j] = new ContentPane({
						label: label,
						content: content
					});

					domainGroupContainer.tableContainer.addChild(domainGroupContainer.recordClassContainers[j]);

					if (!isOwnDomainGroup) {
						me.messagesForOtherDomainGroups[i][label].push(content);
					}
				}

				domainGroupContainer.tableContainer._initialized = false;
				domainGroupContainer.tableContainer._started = false;
				domainGroupContainer.tableContainer.startup();
			}
		},
		addNormal: function (record, recordClass, required) {
			var me = this;

			var content = [];

			var title = (record.title + (record.count ? ' (' + record.count + 'x)' : ''));

			title = title.replace(/(\#RC[a-zA-Z]*\#)/g, function (str, p1) {
				var tmp = p1.replace(/\#/gi, '');
				var classConf2 = me.backend.getClassConfigFromClassName(tmp);

				return (i18nRC[tmp + '_name'] || classConf2.i18nExt[tmp + '_name']);
			});

			if (required) {
				me.recordsDisplayed.push(recordClass + '_' + record.primary);
			}

			return '<input type="checkbox" checked disabled /><label>' + title + '</label>';
		},
		postCreate: function () {
			var me = this;

			me.contentArea = domConstruct.create('div', { class: 'dijitDialogPaneContentArea' }, me.containerNode);

			me.errorType = me.response.error;

			if (langFunc.keys(me.response.data).length) {
				me.formatReferenceMessage(me.response.data);
			} else {
				me.lastAction = 'confirm';
				me.errorType = 'confirm';
			}

			me.message = domConstruct.create('div', { innerHTML: i18nErr.affected[me.lastAction] || '', style: 'margin-bottom: 10px;' }, me.contentArea);

			me.actionBar = domConstruct.create('div', { class: 'dijitDialogPaneActionBar' }, me.containerNode);

			me.BtnYes = new Button({
				label: me.errorType == 'MissingReferencesException' ? i18nErr.BtnPublish : i18nErr.BtnYes,
				onClick: function () {
					if (me.onYes) {
						me.onYes();
					}

					me.destroy();
				}
			});

			me.BtnYes.placeAt(me.actionBar);

			me.BtnNo = new Button({
				label: me.errorType == 'MissingReferencesException' ? i18nErr.BtnCancel : i18nErr.BtnNo,
				onClick: function () {
					if (me.onClose) {
						me.onClose();
					}

					me.destroy();
				}
			});

			me.BtnNo.placeAt(me.actionBar);

			me.set('title', i18nErr[me.errorType] ? i18nErr[me.errorType].title : i18nErr.generic.title);

			me.recordsDisplayed.push(me.mainRecordClass + '_' + me.mainRecordID);

			me.inherited(arguments);
		},
		destroy: function () {
			var me = this;

			domConstruct.destroy(me.contentArea);
			domConstruct.destroy(me.message);
			domConstruct.destroy(me.actionBar);

			me.BtnYes.destroyRecursive();
			delete me.BtnYes;

			me.BtnNo.destroyRecursive();
			delete me.BtnNo;

			for (var i = 0, ilen = me.checkboxes.length; i < ilen; i++) {
				domConstruct.destroy(me.checkboxes[i]);
			}

			me.checkboxes = [];

			for (var i = 0, ilen = me.labels.length; i < ilen; i++) {
				domConstruct.destroy(me.labels[i]);
			}

			me.labels = [];

			for (var i = 0, ilen = me.cbListeners.length; i < ilen; i++) {
				me.cbListeners[i].remove();
			}

			me.cbListeners = [];

			for (var i in me.domainGroupContainers) {
				me.domainGroupContainers[i].destroyRecursive();
			}

			delete me.recordsDisplayed;

			delete me.domainGroupContainers;

			delete me.backend;

			me.inherited(arguments);
		}
	});
});