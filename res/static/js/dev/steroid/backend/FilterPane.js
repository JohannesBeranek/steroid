define([
	"dojo/_base/declare",
	"steroid/backend/DetailPane",
	"dojo/dom-construct",
	"dijit/form/Select",
	"dojo/dom-class",
	"dojo/_base/lang"
], function (declare, DetailPane, domConstruct, Select, domClass, lang) {
	return declare([DetailPane], {
		isFilterPane: true,
		class: 'STFilterPane',
		selectContainer: null,
		selectors: null,
		updatingFilters: false,
		useFieldSets: false,

		constructor: function () {
			var me = this;

			me.selectors = {};
		},
		postMixInProperties: function () {
			var me = this;

			me.inherited(arguments);

			for (var fieldName in me.classConfig.filterFields) {
				if (me.classConfig.filterFields[fieldName].dataType === 'DTSteroidLanguage') {
					if (!me.backend.config.system.languages.available.length) {
						me.classConfig.filterFields[fieldName].startReadOnly = true;
					}
				}
			}
		},
		postCreate: function () {
			var me = this;

			me.inherited(arguments);

			me.form.addInitListener(function () {
				me.selectContainer = domConstruct.create('div', { class: 'STFilterSelectContainer' });

				domConstruct.place(me.selectContainer, me.form.domNode, 'before');

				me.addSelect(me.getAvailableFilters());
			});

			me.form.addValueSetListenerOnce(function () {
				var domainGroupField = me.form.getFieldByFieldName(me.form.getDataTypeFieldName('DTSteroidDomainGroup'));

				if (domainGroupField) {
					domainGroupField.fieldConf.mayBeEmpty = true;
				}

				me.showDefaultFilters();
			});
		},
		showDefaultFilters: function () {
			var me = this;

			for (var fieldName in me.form.ownFields) {
				var val = me.form.ownFields[fieldName]._dt.get('value');

				if ((lang.isArray(val) && val.length) || (!lang.isArray(val) && val)) {
					me.selectors.none.set('value', fieldName);
				}
			}
		},
		getAvailableFilters: function () {
			var me = this;

			var filters = [
				{
					value: 'none',
					label: ' --- '
				}
			];

			for (var fieldName in me.classConfig.filterFields) {
				if (!me.selectors[fieldName]) {
					filters.push({
						value: fieldName,
						label: me.form.ownFields[fieldName]._dt.getLabel(true)
					});
				}
			}

			return filters;
		},
		addSelect: function (filters) {
			var me = this;

			var select = new Select({
				options: filters,
				onChange: function (value) {
					if (!me.updatingFilters) {
						me.filterSelected(this, value, this.get('value'));
					}
				}
			});

			select.startup();
			select.oldValue = 'none';

			me.selectors.none = select;

			domConstruct.place(select.domNode, me.selectContainer);

			return select;
		},
		filterSelected: function (selector, value) {
			var me = this, oldValue = selector.oldValue;

			if (oldValue === value) {
				return;
			}

			if (oldValue !== 'none') {
				me.hideField(oldValue);
			}

			if (value === 'none') {
				if (oldValue !== 'none') {
					me.removeSelect(oldValue);
					me.ensureAvailableSelect();
					me.updateAvailableFilters();
					return;
				}
			} else {
				var field = me.form.getFieldByFieldName(value);

				domClass.add(field.domNode, 'active');

				domConstruct.place(field.domNode, me.form.ownFieldContainerNode, me.getSelectorIndex(selector));

				delete me.selectors[oldValue];

				me.selectors[value] = selector;
			}

			selector.oldValue = value;

			me.ensureAvailableSelect();

			me.updateAvailableFilters(value);
		},
		getSelectorIndex: function (selector) {
			var me = this;

			var idx = 0;

			for (var i = 0; i < me.selectContainer.childNodes.length; i++) {
				if (me.selectContainer.childNodes[i].id == selector.id) {
					idx = i;
				}
			}

			return idx;
		},
		removeSelect: function (value) {
			var me = this;

			me.selectors[value].destroyRecursive();

			delete me.selectors[value];
		},
		ensureAvailableSelect: function () {
			var me = this;

			if (!me.selectors.none) {
				me.addSelect(me.getAvailableFilters());
			}
		},
		hideField: function (value) {
			var me = this;

			var field = me.form.getFieldByFieldName(value);

			domClass.remove(field.domNode, 'active');

			field.set('value', null);
		},
		updateAvailableFilters: function (originValue) {
			var me = this;

			me.updatingFilters = true;

			for (var fieldName in me.selectors) {
				if (originValue && fieldName === originValue) { // don't update the selector that caused the update
					continue;
				}

				var filters = me.getAvailableFilters();

				var options = me.selectors[fieldName].getOptions();

				var selected = me.selectors[fieldName].get('value');

				if (fieldName !== 'none') {
					var field = me.form.getFieldByFieldName(fieldName);

					filters.push({
						value: selected,
						label: field.getLabel(true)
					});
				}

				for (var i = 0, filter; filter = filters[i]; i++) {
					if (filter.value === selected) {
						filter.selected = true;
					}
				}

				for (var i = 0, option; option = options[i]; i++) {
					me.selectors[fieldName].removeOption(option);
				}

				me.selectors[fieldName].addOption(filters);
			}

			me.updatingFilters = false;
		}
	});
});