define([
	"dojo/_base/declare",
	"steroid/backend/mixin/_SubFormMixin",
	"dojo/dom-construct",
	"dojo/on",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dijit/TitlePane"
], function (declare, _SubFormMixin, domConstruct, on, i18nRC, TitlePane) {

	return declare([_SubFormMixin, TitlePane], {

		style: 'float: left',
		removeable: true,
		toggleable: false,
		inlineRecord: null,
		backend: null,
		"class": 'STStaticRecord',
		inlineClassConfig: null,
		inlineSubstitutionFieldName: null,
		inlineRecordPath: "steroid/backend/dnd/InlineRecord",
		isClosing: false,
		titleWatch: null,
		onlyTitleEditable: true,
		readOnly: false,
		mayCopyReadOnly: true,
		ownIndexInParent: 0,

		constructor: function () {
			this.inlineClassConfig = null;
			this.inlineRecord = null;
		},
		_setReadOnlyAttr: function (readOnly) {
			var me = this;

			me.inherited(arguments);

			me.addValueSetListenerOnce(function () {
				if (readOnly) {
					me.removeCloseButton();
				} else {
					me.setupCloseButton();
				}

				if (me.inlineRecord) {
					me.inlineRecord.set('readOnly', readOnly);
				}
			});
		},
		postMixInProperties: function () {
			var me = this;

			me.inherited(arguments);

			for (var fieldName in me.ownFields) {
				if (me.ownFields[fieldName].recordClass && me.ownFields[fieldName].recordClass == me.owningRecordClass) { // remove field that references the record we're being created by
					delete me.ownFields[fieldName];
					continue;
				}

				me.submitFieldsIfDirty.push(fieldName);
			}
		},
		postCreate: function () {
			var me = this;

			me.inherited(arguments);

			me.addInitListener(function () {
				me.inlineRecord = me.ownFields[me.inlineSubstitutionFieldName]._dt;

				me.inlineRecord.addValueSetListener(function (inlineRecord) {
					if (typeof inlineRecord.record._title !== 'undefined') { // widgets don't get the _title set on the server
						me.set('title', inlineRecord.record._title);
					} else {
						me.set('title', me.collectTitle(me.inlineRecord));
					}

					me.inlineRecord.set('readOnly', me.readOnly);
				});
			});

			me.addValueSetListenerOnce(function () {
				me.set('open', me.startOpen());

				me.titleWatch = me.inlineRecord.watch('STValue', function (name, oldValue, newValue) {
					me.set('title', me.collectTitle(me.inlineRecord));
				});

				if (!(me.readOnly || me.startReadOnly) || me.mayCopyReadOnly) {
					me.setupCopyButton();
				}
			});
		},
		startup: function () {
			var me = this;

			me.addValueSetListenerOnce(function () {
				if (me.removeable && !me.readOnly) {
					me.setupCloseButton();
				}
			});

			me.inherited(arguments);
		},
		setupCopyButton: function () {
			//stub
		},
		startOpen: function () {
			return this.backend.debugMode;
		},
		setupCloseButton: function () {
			var me = this;

			if (me.closeNode || me.closeHandle) {
				return;
			}

			me.closeNode = domConstruct.create('div', { "class": 'closeNode STWidgetIcon_close', title: i18nRC.widgets.close });
			domConstruct.place(me.closeNode, me.focusNode, 'after');

			me.closeHandle = on(me.closeNode, 'click', function () {
				if (me.disabled) {
					return;
				}

				me.isClosing = true;
				me.inlineRecord.isClosing = true;
				me.remove();
			});
		},
		removeCloseButton: function () {
			var me = this;

			if (me.closeHandle) {
				me.closeHandle.remove();
				delete me.closeHandle;
			}

			if (me.closeNode) {
				domConstruct.destroy(me.closeNode);
				delete me.closeNode;
			}
		},
		remove: function () {
			var me = this;

			me.destroyRecursive();
		},
		getFieldPath: function (entry, i) {
			var me = this, path;

			if (i == me.inlineSubstitutionFieldName) {
				path = me.inlineRecordPath;
			} else {
				path = me.inherited(arguments);
			}

			return path;
		},
		getFieldConf: function (entry, i) {
			var me = this;

			var fieldConf = me.inherited(arguments);

			if (i == me.inlineSubstitutionFieldName) {
				fieldConf = {
					backend: me.backend,
					ownClassConfig: me.inlineClassConfig,
					mainClassConfig: me.mainClassConfig,
					fieldName: i,
					isNew: me.isNew,
					form: me,
					onlyTitleEditable: me.onlyTitleEditable,
					readOnly: me.readOnly
				};
			}

			return fieldConf;
		},
		updateSubmitName: function (submitName) {
			var me = this;

			me.submitName = submitName;

			me.updateFieldSubmitNames();
		},
		getIdentity: function () {
			var me = this;

			var identity = me.id;

			if (me.isNew) {
				return identity;
			}

			if (me.ownClassConfig.formFields.primary && me.record.primary) {
				identity = me.record.primary;
			} else if (me.inlineClassConfig.formFields.primary && me.inlineRecord.record.primary) {
				identity = me.inlineRecord.record.primary;
			}

			return identity;
		},
		destroy: function () {
			var me = this;

			me.inlineRecord.destroyRecursive();
			delete me.inlineRecord;

			me.removeCloseButton();

			me.titleWatch.unwatch();
			delete me.titleWatch;

			domConstruct.destroy(me.closeNode);

			me.inherited(arguments);
		}
	});
});
