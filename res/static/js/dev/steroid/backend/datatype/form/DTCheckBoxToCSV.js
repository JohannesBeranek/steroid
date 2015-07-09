define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/DTText",
	"dojo/_base/lang",
	"dijit/form/CheckBox",
	"dojo/dom-construct",
	"dojo/dom-style",
	"dijit/layout/ContentPane"
], function (declare, DTText, lang, CheckBox, domConstruct, domStyle, ContentPane) {
	return declare([DTText], {
		"class": 'STCheckBoxToCSV',
		checkBoxes: null,
		separator: ',',
		settingValueFromCheckBox: false,
		checkBoxContainer: null,
		checkBoxWatches: null,
		settingValue: false,

		constructor: function(){
			var me = this;

			me.checkBoxWatches = [];
		},
		startup: function(){
			var me = this;

			me.inherited(arguments);

			if(!me.backend.debugMode){
				domStyle.set(me.textbox, 'display', 'none');
			}

			me.createCheckBoxContainer();
		},
		createCheckBoxContainer: function(){
			var me = this;

			me.checkBoxContainer = new ContentPane({});

			me.checkBoxContainer.startup();

			domConstruct.place(me.checkBoxContainer.domNode, me.domNode, 'before');
		},
		_setValueAttr: function(value){
			var me = this;

			if(!me.settingValueFromCheckBox){
				me.settingValue = true;
				me.setCheckBoxValues(value);
				me.settingValue = false;
			}

			if(lang.isArray(value)){
				value = value.join(me.separator);
			}

			me.inherited(arguments, [value]);
		},
		_setDisabledAttr: function(disabled){
			var me = this;

			for(var fieldName in me.checkBoxes){
				me.checkBoxes[fieldName].set('disabled', disabled);
			}

			me.inherited(arguments);
		},
		setCheckBoxValues: function(value){
			var me = this;

			if(value && !lang.isArray(value)){
				value = value.split(me.separator);
			}

			if(!me.checkBoxes){
				me.createCheckBoxes();
			}

			for(var fieldName in me.checkBoxes){
				me.checkBoxes[fieldName].set('checked', value && value.indexOf(fieldName) > -1);
			}
		},
		createCheckBoxes: function(){
			var me = this;

			me.checkBoxes = {};

			var values = me.getPossibleValues();

			for(var i = 0, ilen = values.length; i < ilen; i++){
				var container = domConstruct.create('div', { "class": 'STFieldPermissionCheckBoxContainer' });

				var cb = new CheckBox({
					disabled: me.disabled,
					readOnly: me.readOnly
				});

				me.checkBoxWatches.push(cb.watch('checked', function(name, oldValue, newValue){
					if(!me.settingValue){
						me.valueChange();
					}
				}));

				me.checkBoxes[values[i].name] = cb;

				cb.startup();

				me.checkBoxContainer.containerNode.appendChild(container);

				container.appendChild(cb.domNode);
				container.appendChild(domConstruct.create('label', { 'for': cb.id, innerHTML: values[i].label}));
			}

			me.checkBoxContainer.resize();
		},
		valueChange: function(){
			var me = this;

			var val = [];

			for(var fieldName in me.checkBoxes){
				var checked = me.checkBoxes[fieldName].get('checked');

				if(checked){
					val.push(fieldName);
				}
			}

			me.settingValueFromCheckBox = true;
			me.set('value', val);
			me.settingValueFromCheckBox = false;
		},
		getPossibleValues: function(){
			var me = this;

			var value = [];

			var fieldNames = me.fieldConf.values || [];

			for(var i = 0, ilen = fieldNames.length; i < ilen; i++){
				value.push({
					name: fieldNames[i],
					label: 'test'
				});
			}

			return value;
		},
		destroy: function(){
			var me = this;

			if(me.checkBoxes){
				for(var fieldName in me.checkBoxes){
					me.checkBoxes[fieldName].destroyRecursive();
				}

				delete me.checkBoxes;
			}

			if(me.checkBoxContainer){
				me.checkBoxContainer.destroyRecursive();
				delete me.checkBoxContainer;
			}

			me.inherited(arguments);
		}
	});
});