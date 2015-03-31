define([
	"dojo/_base/declare",
	"steroid/backend/dnd/SourceWidget",
	"dijit/layout/AccordionContainer",
	"dijit/layout/ContentPane",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dojo/dom-geometry",
	"dojo/on",
	"dojo/dom-class"
], function (declare, SourceWidget, Accordion, ContentPane, i18nRC, domGeom, on, domClass) {
	return declare([Accordion], {
		orientation: 'vert',
		widgetConf: [],
		widgets: [],
		showLabels: false,
		backend: null,
		submitName: null,
		owningRecordClass: null,
		parentWidget: null, //widgetPanel's parentWidget is the canvas
		classPanes: null,
		"class": 'STWidgetPanel',
		scrollHandle: null,

		constructor: function () {
			this.parentWidget = null;
			this.classPanes = {};
		},
		startup: function () {
			var me = this;

			var widgetClassTitles = [];

			for (var i = 0; i < me.widgetConf.length; i++) { // collect class titles
				if (me.widgetConf[i].isDependency && (me.widgetConf[i].className !== 'RCArea' && me.widgetConf[i].mayWrite)) {
					continue;
				}

				var exists = false;

				for (var j = 0, existing; existing = widgetClassTitles[j]; j++) {
					if (existing["class"] === me.widgetConf[i].widgetType) {
						exists = true;
						break;
					}
				}

				if (exists) {
					continue;
				}

				widgetClassTitles.push({
					"class": me.widgetConf[i].widgetType,
					title: i18nRC.widgets['type_' + me.widgetConf[i].widgetType + '_name']
				});
			}

			widgetClassTitles.sort(function (a, b) { // sort widget classes by title
				return a.title > b.title ? 1 : a.title == b.title ? 0 : -1;
			});

			for (var i = 0, itemClass; itemClass = widgetClassTitles[i]; i++) { // create panes for all classes
				for (var j = 0, item; item = me.widgetConf[j]; j++) {
					if (item.widgetType === itemClass["class"]) {
						me.createClassPane(item);
						break;
					}
				}
			}

			var widgetTitles = [];

			for (var i = 0; i < me.widgetConf.length; i++) { // collect class titles){
				if (me.widgetConf[i].isDependency && (me.widgetConf[i].className !== 'RCArea' && me.widgetConf[i].mayWrite)) {
					continue;
				}

				var exists = false;

				for (var j = 0, existing; existing = widgetTitles[j]; j++) {
					if (existing["class"] === me.widgetConf[i].className) {
						exists = true;
						break;
					}
				}

				if (exists) {
					continue;
				}

				var i18n = me.widgetConf[i].i18nExt || i18nRC;

				widgetTitles.push({
					"class": me.widgetConf[i].className,
					title: i18n[me.widgetConf[i].className + '_name']
				});
			}

			widgetTitles.sort(function (a, b) { // sort widget classes by title
				return a.title > b.title ? 1 : a.title == b.title ? 0 : -1;
			});

			for (var i = 0, widgetClass; widgetClass = widgetTitles[i]; i++) {
				for (var j = 0, item; item = me.widgetConf[j]; j++) {
					if (item.className === widgetClass["class"]) {
						me.addWidget(item);
						break;
					}
				}
			}

			me.scrollHandle = on(me.backend.moduleContainer.detailPane.formContainer.containerNode, 'scroll', function (e) {
				me.scrolled();
			});

			me.inherited(arguments);
		},
		scrolled: function () {
			var me = this;

			var pos = domGeom.position(me.domNode, true);
			var canvasPos = domGeom.position(me.parentWidget.domNode, true);

			if (pos.y < 76 && !domClass.contains(me.domNode, 'fixed')) {
				domClass.add(me.domNode, 'fixed');
				domClass.add(me.parentWidget.domNode, 'panelFixed');
			}

			if (pos.y < canvasPos.y && domClass.contains(me.domNode, 'fixed')) {
				domClass.remove(me.domNode, 'fixed');
				domClass.remove(me.parentWidget.domNode, 'panelFixed');
			}
		},
		createClassPane: function (widgetConf) {
			var me = this;

			var pane = new ContentPane({
				title: i18nRC.widgets['type_' + widgetConf.widgetType + '_name']
			});

			me.classPanes[widgetConf.widgetType] = pane;

			pane.startup();

			me.addChild(pane);
		},
		addWidget: function (widgetConf) {
			var me = this;

			var classPane = me.classPanes[widgetConf.widgetType];

			var widget = new SourceWidget({
				widgetConf: widgetConf,
				type: widgetConf.className == 'RCArea' ? 'area' : 'widget',
				open: false,
				submitName: me.submitName,
				backend: me.backend,
				owningRecordClass: me.owningRecordClass,
				dndManager: me.dndManager,
				mainClassConfig: me.mainClassConfig,
				parentWidget: me.parentWidget
			});

			widget.startup();

			me.widgets.push(widget);

			classPane.containerNode.appendChild(widget.domNode);
		},
		findContainer: function (widgetContainer) {
			var me = this;

			if (widgetContainer == me) {
				return widgetContainer;
			}

			return false;
		},
		destroy: function () {
			var me = this;

			delete me.parentWidget;

			me.scrollHandle.remove();
			delete me.scrollHandle;

			var widgetLen = me.widgets.length;

			for (var i = 0; i < widgetLen; i++) {
				me.widgets[i].destroyRecursive();
			}

			delete me.widgets;

			for (var type in me.classPanes) {
				me.classPanes[type].destroyRecursive();
			}

			delete me.classPanes;

			me.inherited(arguments);
		}
	});
});