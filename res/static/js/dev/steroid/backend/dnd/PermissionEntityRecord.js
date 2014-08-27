define([
	"dojo/_base/declare",
	"steroid/backend/dnd/InlineEditableRecord",
	"dojo/_base/array",
	"dojo/dom-style",
	"dojo/dom-construct",
	"dojo/i18n!steroid/backend/nls/Permissions"
], function (declare, InlineEditableRecord, array, domStyle, domConstruct, i18nPerm) {

	return declare([InlineEditableRecord], {

		recordEnabled: false,
		recordMayWrite: false,
		isDependency: false,
		permissionRecordClass: null,
		enabledWatch: null,
		isNew: false,
		dependencyLabel: null,
		i18nExt: null,
		mayWriteWatch: null,
		label: null,
		backendType: null,
		actionFields: ['mayPublish', 'mayHide', 'mayDelete', 'mayCreate'],

		postMixInProperties: function () {
			var me = this;

			me.class = me.ownClassConfig.className;

			me.inherited(arguments);

			me.ownFields['enabled'] = {
				dataType: 'DTPermissionEntityEnabled',
				'default': false,
				nullable: false,
				permissionRecordClass: me.permissionRecordClass,
				i18nExt: me.i18nExt,
				label: me.label
			};

			me.ownFields['fieldPermission'] = {
				dataType: 'DTInlineFieldPermissionEdit',
				recordClass: 'RCFieldPermission',
				backend: me.backend,
				mainClassConfig: me.mainClassConfig,
				fieldName: 'fieldPermission',
				submitName: 'fieldPermission',
				permissionRecordClass: me.permissionRecordClass
			};

			if (me.backendType == 'widget') {
				for (var i = 0, item; item = me.actionFields[i]; i++) {
					delete me.ownFields[item];
				}
			}
		},
		updateSubmitName: function (submitName) {
			var me = this;

			me.submitName = submitName;

			for (var fieldName in me.ownFields) {
				if (fieldName == 'fieldPermission') {
					me.ownFields[fieldName]._dt.updateSubmitName(me.submitName.replace('[permissionEntity]', '') + '[' + fieldName + ']');
				} else {
					me.ownFields[fieldName]._dt.updateSubmitName(me.submitName + '[' + fieldName + ']');
				}
			}
		},
		hookToDataTypeInstance: function (dt, fieldConf, fieldName) {
			var me = this;

			if (fieldName == 'mayWrite') {
				me.mayWriteWatch = dt.watch('STValue', function (name, oldValue, newValue) {
					me.recordMayWrite = !!newValue;

					me.ownFields['fieldPermission']._dt.set('disabled', !(me.recordEnabled && me.recordMayWrite));

					if (me.backendType != 'widget') {
						for (var i = 0, item; item = me.actionFields[i]; i++) {
							me.ownFields[item]._dt.set('value', me.recordMayWrite);
							me.ownFields[item]._dt.set('disabled', !me.recordMayWrite);
						}
					}
				});
			}

			if (fieldName == 'enabled') {
				me.enabledWatch = dt.watch('STValue', function (name, oldValue, newValue) {
					me.recordEnabled = !!newValue;

					for (var fieldName in me.ownFields) {
						if (fieldName != 'enabled') {
							if (fieldName == 'fieldPermission') {
								me.ownFields[fieldName]._dt.set('disabled', !(me.recordEnabled && me.recordMayWrite));
							} else if (array.indexOf(me.actionFields, fieldName) !== -1) {
								me.ownFields[fieldName]._dt.set('disabled', !me.recordEnabled || !me.recordMayWrite);
							} else {
								me.ownFields[fieldName]._dt.set('disabled', !me.recordEnabled);
							}
						}
					}

					domStyle.set(me.domNode, 'opacity', (me.recordEnabled ? 1 : 0.5));

					if (me.recordEnabled && me.isDependency) {
						me.isDependency = false;

						var depDT = me.getFieldByFieldName('isDependency');

						depDT.set('value', me.isDependency);
					}
				});
			}

			me.inherited(arguments);
		},
		reset: function () {
			var me = this;

			me.inherited(arguments);

			me.ownFields['fieldPermission']._dt.set('disabled', !(me.recordEnabled && me.recordMayWrite));
		},
		setFieldDirty: function (setName, fieldDirtyNess, fieldName) {
			var me = this;

			return me.recordEnabled && setName && fieldName != 'primary';
		},
		startup: function () {
			var me = this;

			me.addInitListener(function () {
				me.doSetup();
			});

			me.inherited(arguments);
		},
		doSetup: function () {
			var me = this;

			me.dependencyLabel = domConstruct.create('div', { innerHTML: i18nPerm.dependencyLabel, class: 'STDependencyLabel' });

			domConstruct.place(me.dependencyLabel, me.ownFieldContainerNode, 'last');
		},
		_setValueAttr: function (value) {
			var me = this;

			if (!(value && value.permissionEntity)) {
				me.inherited(arguments, [
					{
						recordClass: me.permissionRecordClass
					}
				]);
				return;
			}

			var entityValue = value.permissionEntity || {};

			entityValue.enabled = !!entityValue.recordClass && !entityValue.isDependency;

			entityValue.recordClass = me.permissionRecordClass;

			me.isDependency = !!entityValue.isDependency;

			if (me.dependencyLabel) {
				domStyle.set(me.dependencyLabel, 'display', (me.isDependency ? 'block' : 'none'));
			}

			entityValue.fieldPermission = value.fieldPermission || {};

			if (me.backendType != 'widget') {
				me.addInitListener(function () {
					for (var i = 0, item; item = me.actionFields[i]; i++) {
						me.ownFields[item]._dt.set('disabled', !me.recordMayWrite);
					}
				});
			}

			me.inherited(arguments, [entityValue]);
		},
		getFieldsToHide: function () {
			var me = this;

			var fields = ['recordClass', 'isDependency'];

			if (me.backendType === 'widget') {
				fields.push('restrictToOwn');
			}

			return fields;
		},
		getFieldOrder: function (fieldOrder) {
			fieldOrder.splice(0, 0, fieldOrder.splice(array.indexOf(fieldOrder, 'enabled'), 1)[0]);

			return fieldOrder;
		},
		destroy: function () {
			var me = this;

			me.enabledWatch.unwatch();
			delete me.enabledWatch;

			me.mayWriteWatch.unwatch();
			delete me.mayWriteWatch;

			domConstruct.destroy(me.dependencyLabel);

			me.inherited(arguments);
		}
	});
});
