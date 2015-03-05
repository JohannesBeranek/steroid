define([
	"dojo/_base/declare",
	"steroid/backend/dnd/DraggableJoinRecord",
	"dojo/Deferred",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"steroid/backend/STStore",
	"dojo/store/Observable",
	"dojo/aspect",
	"dojo/on",
	"steroid/backend/mixin/_Resizeable",
	"dojo/_base/array",
	"dojo/dom-class",
	"dojo/dom-attr",
	"dojo/dom-construct",
	"dojo/_base/lang",
	"dojo/_base/event",
	"dijit/Tooltip"
], function (declare, DraggableJoinRecord, Deferred, i18nRC, STStore, ObservableStore, aspect, on, _Resizeable, array, domClass, domAttr, domConstruct, lang, event, Tooltip) {

	var WidgetBase = declare([DraggableJoinRecord, _Resizeable], {

		toggleable: true,
		inlineRecordPath: "steroid/backend/dnd/InlineEditableWidget",
		dndManager: null,
		itemsAlreadyExist: null,
		inlineRecordValue: null,
		itemsMovedAspect: null,
		sizeableHoriz: true,
		parentWidget: null,
		parentWidthWatch: null,
		parentFixedWatch: null,
		ownFixedWatch: null,
		isFixed: false,
		hideButton: null,
		hideWatch: null,
		hideHandle: null,
		containerPadding: 10,
		class: 'STWidget',
		i18nExt: null,
		copyButton: null,
		copyHandle: null,
		previewStartHandle: null,
		previewEndHandle: null,

		constructor: function () {
			this.itemsAlreadyExist = [];
		},
		setSubmitPrimaryOnly: function (primaryOnly) {
			var me = this;

			me.addInitListener(function () {
				me.inlineRecord.setSubmitPrimaryOnly(primaryOnly);
			});
		},
		postMixInProperties: function () {
			var me = this;

			me.inherited(arguments);

			me.STStore = new ObservableStore(new STStore({
				backend: me.backend,
				classConfig: me.ownClassConfig
			}));

			// TODO: probably needs adaption for RCRTEArea
			var submitFieldsIfDirty = ['primary', 'sorting', me.inlineSubstitutionFieldName, 'columns', 'key'];

			if (me.ownClassConfig.className == 'RCElementInArea') {
				submitFieldsIfDirty.push('class');
			}

			me.submitFieldsIfDirty = submitFieldsIfDirty;
		},
		collectTitle: function (origin) {
			var me = this;

			var title = me.inherited(arguments);

			var i18n = me.inlineRecord.ownClassConfig.i18nExt || i18nRC;

			title = i18n[me.inlineClassConfig.className + '_name'] + ' - ' + title;

			return title;
		},
		setupCloseButton: function () {
			var me = this;

			if (!me.isFixed && !me.readOnly && me.ownClassConfig.className !== "RCPageArea") {
				me.inherited(arguments);
			}
		},
		postCreate: function () {
			var me = this;

			me.inherited(arguments);

			if (me.ownClassConfig.className !== 'RCPageArea' && me.inlineClassConfig.isDependency) {
				me.set('readOnly', true);
			}

			if (me.ownClassConfig.className == 'RCElementInArea') {
				me.addInitListener(function () {
					var classFieldName = me.getDataTypeFieldName('DTDynamicRecordReferenceClass');

					me.ownFields[classFieldName]._dt.staticValue = me.inlineClassConfig.className;
				});
			} // TODO: might be relevant for RCRTEArea?

			if (me.isNew && !me.inlineRecordValue) {
				me.addInitListener(function () {
					me.set('value', me.STStore.get(null, {
						language: me.backend.config.system.languages.current.primary
					}));
				});
			}

			if (me.mainClassConfig.className != 'RCTemplate' && me.ownClassConfig.className == 'RCElementInArea') {
				me.addValueSetListenerOnce(function () {
					if (!me.readOnly && !me.startReadOnly && !me.isFixed) {
						me.setupHideButton();
					}
				});
			} // TODO: add support for hide button in RCRTEArea
		},
		startup: function(){
			var me = this;

			me.inherited(arguments);

			if(me.ownClassConfig.className !== 'RCArea'){
				me.previewStartHandle = on(me.titleBarNode, 'mouseenter', function(e){
					me.doPreview();
				});

				me.previewEndHandle = on(me.titleBarNode, 'mouseleave', function(e){
					me.removePreview();
				});
			}
		},
		doPreview: function(){
			var me = this, preview;

			if(me.get('open')){
				return;
			}

			for(var fieldName in me.inlineRecord.ownFields){
				if(me.inlineRecord.ownFields[fieldName].dataType == 'DTRTE' || me.inlineRecord.ownFields[fieldName].dataType == 'DTImageRecordReference' || me.inlineRecord.ownFields[fieldName].dataType == 'DTText' ){
					preview = me.inlineRecord.ownFields[fieldName]._dt.getPreview();
					break;
				}
			}

			if(!preview){
				preview = me.collectTitle(me.inlineRecord);
			}

			Tooltip.show(preview, me.domNode);
		},
		removePreview: function(){
			var me = this;

			Tooltip.hide(me.domNode);
		},
        setupPubDateButton: function() {
            var me = this;
            
            if (typeof me.record.element !== 'undefined') {
	            if (typeof me.record.element.pubStart !== 'undefined' && me.record.element.pubStart !== null && me.record.element.pubStart !== '') {
	                
	                var pubStart = dojo.date.locale.format(new Date(me.record.element.pubStart));
	 
	                me.pubstartNode = domConstruct.create('div', { class: 'pubstartNode STWidgetIcon_pubStart', title: 'Publish: ' + pubStart });
					me.titleBarNode.appendChild(me.pubstartNode);
	            }
	            
	            if (typeof me.record.element.pubEnd !== 'undefined' && me.record.element.pubEnd !== null && me.record.element.pubEnd !== '') {
	                
	                var pubEnd = dojo.date.locale.format(new Date(me.record.element.pubEnd));
	                
	                me.pubendNode = domConstruct.create('div', { class: 'pubendNode STWidgetIcon_pubEnd', title: 'Unpublish: ' + pubEnd });
					me.titleBarNode.appendChild(me.pubendNode);
	            }
			}
        },
		setupCopyButton: function () {
			var me = this;

			if (me.copyNode || me.copyHandle || me.readOnly) {
				return;
			}

			if (!me.isFixed && me.inlineRecord.record.primary) {
				me.copyNode = domConstruct.create('div', { class: 'copyNode STWidgetIcon_copy', title: i18nRC.widgets.copy });
				me.titleBarNode.appendChild(me.copyNode);

				me.copyHandle = on(me.copyNode, 'click', function (e) {
					me.backend.Clipboard.copyWidget(me);

					event.stop(e);

					return false;
				});
			}
		},
		setupHideButton: function () {
			var me = this;

			if (me.hideButton || me.hideHandle) {
				return;
			}

			var hiddenField = me.getFieldByFieldName(me.getDataTypeFieldName('DTSteroidHidden'));

			if (hiddenField) {
				me.hideButton = domConstruct.create('div', { class: 'hideNode STWidgetIcon_hide', title: i18nRC.widgets.hide });
				me.titleBarNode.appendChild(me.hideButton);

				me.hideHandle = on(me.hideButton, 'click', function (e) {
					hiddenField.set('value', !hiddenField.get('STValue'));

					event.stop(e);

					return false;
				});

				me.hideWatch = hiddenField.watch('STValue', function () {
					me.setHidden(!!hiddenField.get('STValue'));
				});

				me.setHidden(!!hiddenField.get('STValue'));
			}
		},
		removeHideButton: function () {
			var me = this;

			if (me.hideWatch) {
				me.hideWatch.unwatch();
				delete me.hideWatch;
			}

			if (me.hideHandle) {
				me.hideHandle.remove();
				delete me.hideHandle;
			}

			if (me.hideButton) {
				domConstruct.destroy(me.hideButton);
				delete me.hideButton;
			}
		},
		setHidden: function (hidden) {
			var me = this;

			domClass.replace(me.domNode, (hidden ? 'STHidden' : 'STVisible'), (hidden ? 'STVisible' : 'STHidden'));
			domClass.replace(me.hideButton, (hidden ? 'STWidgetIcon_publish' : 'STWidgetIcon_hide'), (hidden ? 'STWidgetIcon_hide' : 'STWidgetIcon_publish'));
			domAttr.set(me.hideButton, 'title', hidden ? i18nRC.widgets.publish : i18nRC.widgets.hide);
		},
		setupResizeHandle: function () {
			var me = this;

			me.addValueSetListenerOnce(function () {
				if (!me.isFixed && !me.readOnly) {
					me.createResizeHandle();
				}
			});
		},
		setupMouseDownHandler: function () {
			var me = this;

			me.addValueSetListenerOnce(function () {
				me.setDraggable(!me.isFixed);
			});
		},
		setDraggable: function (draggable) {
			var me = this;

			if (me.inlineClassConfig.isDependency || me.readOnly) {
				draggable = false;
			}

			if (draggable && !me.mouseDownHandler) {
				me.mouseDownHandler = on(me.titleBarNode, 'mousedown', lang.hitch(me, 'mouseDown'));
			}

			if (!draggable && me.mouseDownHandler) {
				me.mouseDownHandler.remove();
				delete me.mouseDownHandler;
			}
		},
		hookToDataTypeInstance: function (dt, fieldConf, fieldName) {
			var me = this;

			if (fieldName == 'fixed' && me.mainClassConfig.className != 'RCTemplate') {

				me.ownFixedWatch = dt.watch('STValue', function () {
					me.setFixed(dt.get('STValue'));
				});
			}

			me.inherited(arguments);
		},
		setFixed: function (fixed) {
			var me = this;

			if (typeof fixed == 'undefined') {
				return;
			}

			if (!me.isFixed && fixed) {
				domClass.add(me.titleBarNode, 'fixed');
			}

			if (me.isFixed && !fixed) {
				domClass.remove(me.titleBarNode, 'fixed');
			}

			me.isFixed = fixed;

			if(me.isFixed){
				me.removeHideButton();
			} else {
				me.removeCloseButton();
			}

			me.setDraggable(!me.isFixed);
		},
		getWidget: function (newContainer) {
			var me = this;

			var foreignRecordClass = null;

			switch (newContainer.owningRecordClass) {
				case 'RCPage':
					foreignRecordClass = 'RCPageArea';
					break;
				case 'RCTemplate':
					foreignRecordClass = 'RCTemplateArea';
					break;
				case 'RCRTE':
					foreignRecordClass = 'RCRTEArea';
					break;
				case 'RCArea':
					foreignRecordClass = 'RCElementInArea';
					break;
			}

			if (me.ownClassConfig.className == foreignRecordClass) {
				return me;
			}

			// RCTemplateArea/RCPageArea becomes RCElementInArea or vice versa

			me.willBeDestroyed = true;

			var foreignClassConfig = me.backend.getClassConfigFromClassName(foreignRecordClass);

			var inlineClassConfig = me.backend.getClassConfigFromClassName('RCArea');

			var inlineSubstitutionFieldName = newContainer.owningRecordClass == 'RCArea' ? 'element' : 'area';

			var ownValue = me.get('value');

			ownValue[inlineSubstitutionFieldName] = ownValue[me.inlineSubstitutionFieldName];// what was once an element has now become an area or the other way round

			delete ownValue[me.inlineSubstitutionFieldName];

			delete ownValue[inlineSubstitutionFieldName]['area:RCElementInArea']; // don't set contained wigets as new values
			delete ownValue[inlineSubstitutionFieldName]['area:RCRTEArea'];

			delete ownValue['primary']; // it's a new record of a different record class, after all

			var containedWidgets = me.inlineRecord.getContainedItems();

			var widget = new WidgetBase({
				backend: me.backend,
				ownClassConfig: foreignClassConfig,
				inlineClassConfig: inlineClassConfig,
				inlineSubstitutionFieldName: inlineSubstitutionFieldName,
				itemsAlreadyExist: containedWidgets,
				inlineRecordValue: ownValue[inlineSubstitutionFieldName],
				owningRecordClass: newContainer.owningRecordClass,
				submitName: newContainer.submitName,
				mainClassConfig: me.mainClassConfig,
				dndManager: me.dndManager,
				type: 'area',
				isNew: me.isNew,
				readOnly: newContainer.isReadOnly()
			});

			widget.startup();

			widget.addInitListener(function () {
				widget.set('value', ownValue);
			});

			me.itemsMovedAspect = aspect.after(widget, 'itemsMoved', function () {
				me.itemsMovedAspect.remove();
				me.remove();
			});

			return widget;
		},
		remove: function () {
			var me = this;

			delete me.itemsAlreadyExist;

			me.inherited(arguments);
		},
		startOpen: function () {
			var me = this;

			return this.isNew || me.ownClassConfig.className == 'RCTemplateArea' || me.ownClassConfig.className == 'RCPageArea' || me.inlineClassConfig.className == 'RCArea' || me.get('state') != '';
		},
		getDirtyNess: function () {
			var me = this;

			if (me.isNew) {
				return 1;
			}

			return me.inherited(arguments);
		},
		getFieldConf: function (entry, i) {
			var me = this;

			var fieldConf = me.inherited(arguments);

			if (i == me.inlineSubstitutionFieldName) {
				fieldConf = {
					backend: me.backend,
					ownClassConfig: me.inlineClassConfig,
					mainClassConfig: me.mainClassConfig,
					itemsAlreadyExist: me.itemsAlreadyExist,
					dndManager: me.dndManager,
					isNew: me.isNew,
					fieldName: i,
					submitName: me.submitName + '[' + i + ']',
					parentWidget: me,
					readOnly: me.readOnly
				};
			}

			if (i == 'fixed' && me.mainClassConfig.className != 'RCTemplate') {
				fieldConf.readOnly = true;
			}
			return fieldConf;
		},
		_setReadOnlyAttr: function (readOnly) {
			var me = this;

			me.inherited(arguments);

			me.addValueSetListenerOnce(function () {
				if (readOnly || me.isFixed) {
					me.removeHideButton();

					if (!me.mayCopyReadOnly) {
						me.removeCopyButton();
					}
				} else {
					me.setupHideButton();

					me.setupCopyButton();
                    
                    me.setupPubDateButton();
				}
			});
		},
		setParent: function (parentWidget) {
			var me = this;

			me.parentWidget = parentWidget;

			me.parentContainerPadding = me.parentWidget.containerPadding;

			if (me.parentWidget.ownDimensionsSet.isResolved()) {
				me.setParentDimensions({w: parentWidget.get('ownWidth')});
			} else {
				dojo.when(me.parentWidget.ownDimensionsSet, function (dim) {
					me.setParentDimensions(dim);
				});
			}

			if (me.parentWidthWatch) {
				me.parentWidthWatch.unwatch();
			}

			me.parentWidthWatch = me.parentWidget.watch('ownWidth', function (name, oldValue, newValue) {
				me.setParentDimensions({ w: newValue });
			});

			var parentFixedField = me.parentWidget.getFieldByFieldName('fixed');

			if (parentFixedField) {
				parentFixedField.addValueSetListenerOnce(function () {
					me.parentFixedChange(parentFixedField);
				});

				me.parentFixedWatch = parentFixedField.watch('STValue', function () {
					me.parentFixedChange(this);
				});
			}
		},
		parentFixedChange: function (parentFixedField) {
			var me = this;

			var parentFixed = parentFixedField.get('STValue');

			if (typeof parentFixed === 'undefined' || parentFixed === null) {
				return;
			}

			var ownFixedField = me.getFieldByFieldName('fixed');

			if (typeof ownFixedField === 'undefined' || ownFixedField === null) {
				return;
			}

			var parentReadOnly = parentFixedField.get('readOnly');

			var readOnly = !parentFixed || !!parentReadOnly || me.mainClassConfig.className != 'RCTemplate';

			ownFixedField.set('readOnly', readOnly);

			if (!parentFixed) {
				ownFixedField.set('value', false);
			}
		},
		parentDimensionsChanged: function () {
			var me = this;

			if (typeof me.currentDimensions.w == 'undefined') {
				me.addValueSetListenerOnce(function () {
					var width, cols = Math.max(me.ownFields['columns']._dt.get('value') - 1, 0);

					width = me.parentDimensions.w[cols] || Math.min.apply(Math, me.parentDimensions.w);

					me.setCurrentDimensions({ w: width });
				});
			} else {
				me.inherited(arguments);
			}
		},
		currentDimensionsChanged: function () {
			var me = this;

			me.inherited(arguments);

			var colField = me.ownFields['columns']._dt;

			var colIndex = array.indexOf(me.parentDimensions.w, me.currentDimensions.w);

			colField.set('value', colIndex + 1);
		},
		removeCopyButton: function () {
			var me = this;

			if (me.copyNode) {
				domConstruct.destroy(me.copyNode);
				delete me.copyNode;

				me.copyHandle.remove();
				delete me.copyHandle;
			}
		},
		destroy: function () {
			var me = this;

			me.removePreview();

			if(me.previewStartHandle){
				me.previewStartHandle.remove();
				delete me.previewStartHandle;
			}

			if(me.previewEndHandle){
				me.previewEndHandle.remove();
				delete me.previewEndHandle;
			}

			if (me.parentWidthWatch) {
				me.parentWidthWatch.unwatch();
				delete me.parentWidthWatch;
			}

			if (me.parentFixedWatch) {
				me.parentFixedWatch.unwatch();
				delete me.parentFixedWatch;
			}

			if (me.ownFixedWatch) {
				me.ownFixedWatch.unwatch();
				delete me.ownFixedWatch;
			}

			me.removeHideButton();

			me.removeCopyButton();

			delete me.STStore;

			if (me.mouseDownHandler) {
				me.mouseDownHandler.remove();
				delete me.mouseDownHandler;
			}

			delete me.parentWidget;

			me.inherited(arguments);
		},
		getFieldsToHide: function () {
			var me = this;

			var fields = me.inherited(arguments);

			if (me.ownClassConfig.className == 'RCPageArea') {
				fields.push('columns', 'fixed', 'key');
			}

			if (me.ownClassConfig.className == 'RCTemplateArea') {
				fields.push('columns');
			}

			if (me.ownClassConfig.className == 'RCElementInArea') {
				fields.push('class', 'columns');

				if(me.mainClassConfig.className !== 'RCTemplate'){
					fields.push('fixed');
				}
			}

			return fields;
		}
	});

	return WidgetBase;
});
