define([
	"dojo/_base/declare",
	"steroid/backend/dnd/SourceWidget",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dojo/i18n!steroid/backend/nls/Clipboard",
	"dojo/dom-construct",
	"dojo/on",
	"dojo/_base/event",
	"dijit/Dialog",
	"dojo/Deferred",
	"dojox/layout/TableContainer",
	"dijit/form/Button"
], function (declare, SourceWidget, i18nRC, i18nClipboard, domConstruct, on, event, Dialog, Deferred, TableContainer, Button) {

	return declare([SourceWidget], {
		valueToBeSet: null,
		closeHandle: null,
		closeNode: null,
		clipboard: null,
		"class": 'STWidget STClipboardWidget',
		dialog: null,

		postCreate: function () {
			var me = this;

			me.inherited(arguments);

			if (me.valueToBeSet) {
				var elementTitle = me.getElementTitle(me.valueToBeSet, me.widgetConf.titleFields);
				var className = me.widgetConf.i18nExt ? me.widgetConf.i18nExt[me.widgetConf.className + '_name'] : i18nRC[me.widgetConf.className + '_name'];

				me.set('title', className + ' - ' + elementTitle); //TODO put title collection into own class and mix it in
			}

			me.setupCloseButton();
		},
		getElementTitle: function (value, titleFields) {
			var me = this;

			var title = '';

			if(!value){
				return title;
			}

			for (var fieldName in titleFields) {
				if (titleFields[fieldName] && typeof titleFields[fieldName] === 'object') {
					title += me.getElementTitle(value[fieldName], titleFields[fieldName]);
				} else {
					if (Object.prototype.toString.call(value[fieldName]) === '[object Array]') { //DTSelect type value
						for (var i = 0, len = value[fieldName].length; i < len; i++) {
							if (value[fieldName][i].selected) {
								return value[fieldName][i].label;
							}
						}
					} else {
						title += value[fieldName] || value['_title'];
					}
				}
			}

			return title;
		},
		getWidget: function (container) {
			var me = this;

			var widget = me.inherited(arguments);

			var def = new Deferred();

			me.backend.STServerComm.sendAjax({
				data: {
					requestType: 'insertFromClipboard',
					recordClass: widget.inlineClassConfig.className,
					recordID: me.valueToBeSet.primary
				},
				error: function (response) {
					res.cancel('Aborted');
					me.backend.showError(response);
				},
				success: function (response) {
					if (response && response.success) {
						me.getUserAction(widget, def, response.data.exists, container);
					}
				}
			});

			return def;
		},
		recursiveCopyWidgets: function(area){
			var me = this;

			if(area.length){
				for(var i = 0, ilen = area.length; i < ilen; i++){
					area[i].primary = null;

					area[i].element.id = null;
					area[i].element.primary = null;

					if(area[i].element['area:RCElementInArea']){
						me.recursiveCopyWidgets(area[i].element['area:RCElementInArea']);
					}
				}
			}
		},
		getUserAction: function (widget, def, exists, container) {
			var me = this;

			var contentContainer = new TableContainer({
				cols: 1,
				showLabels: false
			});

			var btCopy = new Button({
				label: i18nClipboard.btCopy,
				style: 'margin-top:1.3em; margin-bottom:1.5em; margin-right:1.5em;float:left;width:15%;',
				onClick: function () {
					var value = dojo.clone(me.valueToBeSet);

					value.primary = null;
					value.id = null;

					if(value['area:RCElementInArea']){
						me.recursiveCopyWidgets(value['area:RCElementInArea']);
					}

					// widget.addInitListener(function () {
					// drop leads to request to RCElementInArea which sets "element" field which leads to getRecord request with id = new for element which in turn returns empy values for a new widget 
					// in case this completes after values of the widget are set they get overriden by empty values though somehow this only affects the first field ...
					widget.addValueSetListenerOnce(function () {
						widget.set('value', {
							element: value
						});
					});

					def.resolve(widget);

					me.dialog.destroyRecursive();
					delete me.dialog;
				}
			});

			var btReference = new Button({
				label: i18nClipboard.btReference,
				style: 'margin-top:1.3em; margin-bottom:1.5em; margin-right:1.5em;float:left;width:15%;',
				disabled: !exists,
				onClick: function () {
					// widget.addInitListener(function () {
					// drop leads to request to RCElementInArea which sets "element" field which leads to getRecord request with id = new for element which in turn returns empy values for a new widget 
					// in case this completes after values of the widget are set they get overriden by empty values though somehow this only affects the first field ...
					widget.addValueSetListenerOnce(function () {
						widget.set('value', {
							element: me.valueToBeSet
						});

						widget.setSubmitPrimaryOnly(true);
					});

					def.resolve(widget);

					me.dialog.destroyRecursive();
					delete me.dialog;
				}
			});

			var btCancel = new Button({
				label: i18nClipboard.btCancel,
				onClick: function () {
					me.cancelInsert(def, container);
				}
			});

			var copyHelp = domConstruct.create('p', { innerHTML: i18nClipboard.copyHelp, style: 'float:left;width:80%' });
			var referenceHelp = domConstruct.create('p', { innerHTML: i18nClipboard.referenceHelp + (exists ? '' : i18nClipboard.referenceDisabled), style: 'float:left;width:80%' });

			contentContainer.addChild(btCopy);
			contentContainer.addChild(btReference);
			contentContainer.addChild(btCancel);

			me.dialog = new Dialog({
				title: i18nClipboard.userActionDialogTitle,
				content: contentContainer
			});

			me.dialog.show();

			domConstruct.place(copyHelp, btCopy.domNode, 'after');

			domConstruct.place(referenceHelp, btReference.domNode, 'after');

			me.dialog.resize();
		},
		cancelInsert: function (def, container) {
			var me = this;

			me.dialog.destroyRecursive();
			delete me.dialog;

			def.cancel();
			container.dragOut();
		},
		setupContent: function () {
			var me = this;

			var i18n = me.widgetConf.i18nExt || i18nRC;

			me.set('title', i18n[me.widgetConf.className + '_name']);
			me.set('content', i18nClipboard.widget_description);
		},
		setupCloseButton: function () {
			var me = this;

			me.closeNode = domConstruct.create('div', { "class": 'closeNode STWidgetIcon_close', title: i18nRC.widgets.close });
			me.titleBarNode.appendChild(me.closeNode);

			me.closeHandle = on(me.closeNode, 'click', function (e) {
				event.stop(e);

				me.remove();

				return false;
			});
		},
		remove: function () {
			var me = this;

			me.clipboard.removeItem(me);
		},
		destroy: function () {
			var me = this;

			me.closeHandle.remove();
			domConstruct.destroy(me.closeNode);

			delete me.clipboard;

			if (me.dialog) {
				me.dialog.destroyRecursive();
				delete me.dialog;
			}

			me.inherited(arguments);
		}
	});
});
