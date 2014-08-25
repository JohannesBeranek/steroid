define([
	"dojo/_base/declare",
	"dijit/TitlePane",
	"dojo/_base/event",
	"dojo/dom-construct",
	"dojo/on",
	"steroid/backend/mixin/_hasInitListeners",
	"steroid/backend/ModuleContainer",
	"steroid/backend/mixin/_ModuleContainerList",
	"dojo/when",
	"dojo/window",
	"dijit/Dialog",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dojo/i18n!steroid/backend/nls/Clipboard",
	"dojo/dom-style",
	"dojo/aspect",
	"dijit/form/Button",
	"dojo/_base/json"
], function (declare, TitlePane, event, domConstruct, on, _hasInitListeners, ModuleContainer, _ModuleContainerList, when, win, Dialog, i18nRC, i18nClipboard, domStyle, aspect, Button, json) {

	return declare([TitlePane, _hasInitListeners], {
		closeNode: null,
		clipboard: null,
		closeHandle: null,
		insertHandle: null,
		insertNode: null,
		backend: null,
		class: 'STStaticRecord',
		moduleContainer: null,
		ownClassConfig: null,
		moduleCloseAspect: null,
		moduleSelectAspect: null,
		valueToBeSet: null,

		startup: function () {
			var me = this;

			//FIXME: dup from clipboardWidget
			me.closeNode = domConstruct.create('div', { class: 'closeNode STWidgetIcon_close', title: i18nRC.widgets.close });
			me.titleBarNode.appendChild(me.closeNode);

			me.closeHandle = on(me.closeNode, 'click', function (e) {
				event.stop(e);

				me.remove();

				return false;
			});

			me.insertNode = domConstruct.create('div', { class: 'insertNode STWidgetIcon_insert', title: i18nRC.widgets.insert });
			me.titleBarNode.appendChild(me.insertNode);

			me.insertHandle = on(me.insertNode, 'click', function (e) {
				event.stop(e);

				me.insert();

				return false;
			});

			me.inherited(arguments);
		},
		remove: function () {
			var me = this;

			me.clipboard.removeItem(me);
		},
		insert: function () {
			var me = this;

			if (me.moduleContainer) {
				return;
			}

			var customContainer = declare([ModuleContainer, _ModuleContainerList], {
				classConfig: me.ownClassConfig,
				style: 'width: 100%; height:100%',
				isRecordSelector: true,
				backend: me.backend,
				hasMultiple: false,
				fetchRecordOnSelect: false,
				baseQuery: {
//					exclude: json.toJson([me.valueToBeSet.primary]),
					mainRecordClass: 'RCClipboard'
				}
			});

			me.moduleContainer = new customContainer({});

			me.moduleContainer.startup();

			when(me.moduleContainer.listPane.loadInitDef, function () {
				var screenWidth = win.getBox();

				me.moduleContainer.inDialog = new Dialog({
					title: i18nClipboard.overrideInsertListTitle.replace('###PAGE###', me.valueToBeSet._title || me.valueToBeSet.title),
					autofocus: false,
					style: 'width: 80%;height:80%',
					content: me.moduleContainer
				});

				domStyle.set(me.moduleContainer.inDialog.containerNode, {
					height: '95%',
					padding: 0
				});

				me.moduleContainer.inDialog.startup();
				me.moduleContainer.inDialog.show();
			});

			me.moduleCloseAspect = aspect.after(me.moduleContainer, 'hasClosed', function () {
				me.moduleContainer.inDialog.destroyRecursive();

				delete me.moduleContainer;
			});

			me.moduleSelectAspect = aspect.after(me.moduleContainer, 'recordSelected', function (record) {
				me.doInsert(record);

				me.moduleContainer.close();

				me.removeModuleContainer();
			});
		},
		doInsert: function (parent) {
			var me = this;

			me.backend.STServerComm.sendAjax({
				data: {
					requestType: 'copyPage',
					recordID: me.valueToBeSet.primary,
					parent: parent
				},
				success: function (response) {
					if (response && response.success) {
						me.copySuccess(response);
					}
				},
				error: function (response) {
					if (response.error === 'RecordDoesNotExistException') {
						me.remove();
					}

					me.backend.showError(response);
				}
			});
		},
		copySuccess: function (response) {
			var me = this;

			me.backend.showToaster('copyRecord');

			if (me.backend.moduleContainer && me.backend.moduleContainer.listPane && me.backend.moduleContainer.listPane.classConfig.className == 'RCPage') {
				me.backend.moduleContainer.listPane.view.refresh();
			}
		},
		removeModuleContainer: function () {
			var me = this;

			if (me.moduleContainer) {
				me.moduleContainer.destroyRecursive();
				delete me.moduleContainer;
			}

			if (me.moduleCloseAspect) {
				me.moduleCloseAspect.remove();
				delete me.moduleCloseAspect;
			}

			if (me.moduleSelectAspect) {
				me.moduleSelectAspect.remove();
				delete me.moduleSelectAspect;
			}
		},
		destroy: function () {
			var me = this;

			me.removeModuleContainer();

			me.closeHandle.remove();
			domConstruct.destroy(me.closeNode);

			me.insertHandle.remove();
			domConstruct.destroy(me.insertNode);

			delete me.clipboard;
			delete me.backend;

			delete me.ownClassConfig;

			me.inherited(arguments);
		}
	});
});
