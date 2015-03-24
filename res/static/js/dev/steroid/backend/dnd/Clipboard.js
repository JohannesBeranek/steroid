define([
	"dojo/_base/declare",
	"dijit/DropDownMenu",
	"dijit/PopupMenuBarItem",
	"dijit/layout/AccordionContainer",
	"dijit/TitlePane",
	"dijit/layout/ContentPane",
	"steroid/backend/dnd/DropContainer",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dojo/i18n!steroid/backend/nls/Clipboard",
	"steroid/backend/dnd/Widget",
	"steroid/backend/dnd/DndManager",
	"steroid/backend/dnd/ClipboardWidget",
	"steroid/backend/dnd/ClipboardRecord",
	"steroid/backend/dnd/ClipboardPage",
	"steroid/backend/dnd/InlineRecord",
	"dojo/dom-class",
	"dojo/_base/fx",
	"dojo/dom-geometry",
	"dojo/dom-construct",
	"dojo/dom-style"
], function (declare, DropDownMenu, PopupMenuBarItem, AccordionContainer, TitlePane, ContentPane, DropContainer, i18nRC, i18nClipboard, Widget, DndManager, ClipboardWidget, ClipboardRecord, ClipboardPage, InlineRecord, domClass, baseFx, domGeom, domConstruct, domStyle) {

	return declare([PopupMenuBarItem], {
		"class": 'STClipboard',
		label: i18nClipboard.clipboard_name,
		style: 'float: right;',
		accordion: null,
		backend: null,
		widgetContainer: null,
		orphanContainer: null,
		classPanes: [],

		getFieldByFieldName: function () {

		},
		postCreate: function () {
			var me = this;

			me.accordion = new AccordionContainer({});

			me.addClipboardItems();

			me.popup = new DropDownMenu({});

			me.popup.addChild(me.accordion);

			me.inherited(arguments);
		},
		addClassPane: function (classConf) {
			var me = this;

			for (var i = 0, item; item = me.classPanes[i]; i++) {
				if (item.recordClass == classConf.className) {
					return item;
				}
			}

			var classPane = new ContentPane({
				title: classConf.i18nExt ? classConf.i18nExt[classConf.className + '_name'] : i18nRC[classConf.className + '_name'],
				content: '',
				"class": 'STClipboardItemContainer',
				recordClass: classConf.className
			});

			me.classPanes.push(classPane);

			me.accordion.addChild(classPane);

			return classPane;
		},
		addClipboardItems: function () {
			var me = this, widgets;

			for (var className in me.backend.config.clipboard) {
				if (className == 'widget') {
					widgets = me.backend.config.clipboard[className];
					continue;
				}

				var classConf = me.backend.getClassConfigFromClassName(className);

				var classPane = me.addClassPane(classConf);

				for (var i = 0, item; item = me.backend.config.clipboard[className][i]; i++) {
					if (classConf.className != 'RCPage') { // only widgets and pages supported for now
						continue;
					}

					var record = new ClipboardPage({
						ownClassConfig: classConf,
						backend: me.backend,
						clipboard: me,
						open: false,
						title: item.values._title || item.values.title,
						valueToBeSet: item.values
					});

					record.startup();

					record.set('value', item.values);

					classPane.containerNode.appendChild(record.domNode);

					classPane.resize();
				}
			}

			me.widgetContainer = new DropContainer({
				title: i18nClipboard.widgetContainer_name,
				accept: [],
				content: '',
				"class": 'STClipboardItemContainer',
				parentWidget: me,
				getFieldByFieldName: function () {
					//stub
				}
			});

//			me.orphanContainer = new DropContainer({ // use this once we improve template switching
//				title: i18nClipboard.orphanContainer_name,
//				accept: [],
//				content: '',
//				"class": 'STClipboardItemContainer',
//				parentWidget: me,
//				getFieldByFieldName: function () {
//					//stub
//				}
//			});

			if (widgets && widgets.length) {
				for (var i = 0, item; item = widgets[i]; i++) {
					me.createClipboardWidget(me.backend.getClassConfigFromClassName(item.recordClass), item.values);
				}
			}

			me.accordion.addChild(me.widgetContainer);
//			me.accordion.addChild(me.orphanContainer);
		},
		createClipboardRecord: function (originRecord) {
			var me = this;

			var record = new ClipboardRecord({
				clipboard: me,
				ownClassConfig: originRecord.ownClassConfig,
				open: false,
				backend: me.backend
			});

			record.addInitListener(function () {
				record.set('value', originRecord.record);
			});

			record.startup();

			return record;
		},
		createClipboardWidget: function (widgetClassConf, widgetValue, isOrphan) {
			var me = this;

			var widget = new ClipboardWidget({
				clipboard: me,
				widgetConf: widgetClassConf,
				type: widgetClassConf.className == 'RCArea' ? 'area' : 'widget',
				open: false,
				backend: me.backend,
				dndManager: me.backend.dndManager,
				owningRecordClass: me.owningRecordClass,
				mainClassConfig: me.backend.getClassConfigFromClassName('RCPage'),
				valueToBeSet: widgetValue,
				parentWidget: null
			});

			if (isOrphan) {
				me.orphanContainer.addItem(widget);
			} else {
				me.widgetContainer.addItem(widget);
			}
		},
		copyWidget: function (widget, isOrphan) {
			var me = this,
				widgetValue = widget.get('value').element,
				widgetClassName = widget.inlineClassConfig.className,
				widgetClassConf = widget.inlineClassConfig,
				widgetPrimary = widgetValue.primary;

			me.backend.STServerComm.sendAjax({
				data: {
					requestType: 'copyToClipboard',
					recordClass: widgetClassName,
					recordID: widgetPrimary
				},
				error: function (response) {
					me.backend.showError(response);
				},
				success: function (response) {
					if (response && response.success) {
						if (!response.data.exists) {
							me.copySuccess();
							me.createClipboardWidget(widgetClassConf, widgetValue, isOrphan);
						}
					} else {
						me.backend.showError(response);
					}
				}
			});

			me.animateWidget(widget);
		},
		copySuccess: function () {
			var me = this;

			domStyle.set(me.domNode, {
				backgroundColor: '#efefef'
			});

			baseFx.animateProperty({
				node: me.domNode,
				properties: {
					backgroundColor: 'rgb(157, 243, 154)'
				},
				duration: 150,
				onEnd: function () {
					baseFx.animateProperty({
						node: me.domNode,
						properties: {
							backgroundColor: '#efefef'
						},
						duration: 150,
						onEnd: function () {
							domStyle.set(me.domNode, {
								backgroundColor: 'transparent'
							});
						}
					}).play();
				}
			}).play();
		},
		animateWidget: function (widget) {
			var me = this;

			var geom = domGeom.getMarginBox(widget.domNode);
			var pos = domGeom.position(widget.domNode, true);

			var ghost = new TitlePane({
				title: widget.get('title'),
				open: false,
				style: 'width: ' + geom.w + 'px; height: ' + geom.h + 'px; position:absolute; top:' + pos.y + 'px; left:' + pos.x + 'px;'
			});

			ghost.startup();

			domConstruct.place(ghost.domNode, document.body);

			baseFx.animateProperty({
				node: ghost.domNode,
				properties: {
					top: 0,
					left: 1500,
					opacity: { start: 1, end: 0 }
				},
				duration: 200,
				onEnd: function () {
					ghost.destroyRecursive();
				}
			}).play();
		},
		createClipboardPage: function (values) {
			var me = this;

			var page = new ClipboardPage({
				title: values._title || values.title,
				ownClassConfig: me.backend.getClassConfigFromClassName('RCPage'),
				backend: me.backend,
				clipboard: me,
				open: false,
				valueToBeSet: values
			});

			page.startup();

			page.addInitListener(function () {
				page.set('value', values);
			});

			var pane = me.addClassPane(me.backend.getClassConfigFromClassName('RCPage'));

			domConstruct.place(page.domNode, pane.domNode);
		},
		copyPage: function (pagePrimary) {
			var me = this;

			me.backend.STServerComm.sendAjax({
				data: {
					requestType: 'copyToClipboard',
					recordClass: 'RCPage',
					recordID: pagePrimary
				},
				error: function (response) {
					me.backend.showError(response);
				},
				success: function (response) {
					if (response && response.success) {
						if (!response.data.exists) {
							me.copySuccess();
							me.createClipboardPage(response.data.items[0]);
						}
					} else {
						me.backend.showError(response);
					}
				}
			});
		},
//		copyRecord: function(record){
//			var me = this, classPane;
//
//			var recordClass = record.ownClassConfig.className;
//
//			me.backend.STServerComm.sendAjax({
//				data: {
//					requestType: 'copyToClipboard',
//					recordClass: recordClass,
//					recordID: record.primary // doesn't work that way for join records, which most of them are
//				},
//				error: function (response) {
//					me.backend.showError(response);
//				},
//				success: function (response) {
//					if (response && response.success) {
//						if (!response.data.exists) {
//							me.copySuccess();
//							me.createClipboardWidget(widgetClassConf, widgetValue, isOrphan);
//						}
//					} else {
//						me.backend.showError(response);
//					}
//				}
//			});
//
//			for(var i = 0, item; item = me.classPanes[i]; i++){
//				if(item.recordClass == recordClass){
//					classPane = item;
//				}
//			}
//
//			if(!classPane){
//				classPane = me.addClassPane(record.ownClassConfig);
//			}
//
//			var clipboardRecord = me.createClipboardRecord(record);
//
//			domConstruct.place(clipboardRecord.domNode, classPane.containerNode);
//		},
		removeItem: function (item) {
			var me = this,
				itemClassName = item.ownClassConfig ? item.ownClassConfig.className : item.widgetConf.className,
				itemValue = item.get('value') || item.valueToBeSet,
				itemPrimary = itemValue.primary;

			me.backend.STServerComm.sendAjax({
				data: {
					requestType: 'removeFromClipboard',
					recordClass: itemClassName,
					recordID: itemPrimary
				},
				error: function (response) {
					me.backend.showError(response);
				},
				success: function (response) {
					if (response && response.success) {
						item.destroy();
					}
				}
			});
		},
		destroy: function () {
			var me = this;

			me.widgetContainer.destroyRecursive();
			delete me.widgetContainer;

//			me.orphanContainer.destroyRecursive();
//			delete me.orphanContainer;

			me.accordion.destroyRecursive();
			delete me.accordion;

			delete me.classPanes;

			me.inherited(arguments);
		}
	});
});
