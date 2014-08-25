define([
	"dojo/_base/declare",
	"steroid/backend/mixin/_fieldExtension"
], function (declare, _fieldExtension) {
	return declare([_fieldExtension], {
		fileField: null,
		imageWatch: null,
		isImage: false,
		configWatch: null,
		container: null,
		value: null,
		formValueWatchKey: null,
		settingInitialValue: false,

		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.init();
		},
		init: function () {
			var me = this;

			me.formValueWatchKey = me.field.form.addValueSetListener(function () {
				me.fileField = me.field.form.getFieldByFieldName(me.field.form.getDataTypeFieldName('DTFile'));

				if (!me.field.form.isResetting) {
					me.settingInitialValue = true;
					me.setIsImage(me.field.get('isImage'));
					me.setRenderConfig(me.field.get('value'));
					me.initialValueSet();
				}

				if (!me.field.form.isResetting) {
					me.imageWatch = me.field.watch('isImage', function (name, oldValue, newValue, field) {
						if (!me.field.form.isResetting) {
							me.setIsImage(newValue);
						}
					});
				}

				if (!me.field.form.isResetting) {
					me.configWatch = me.field.watch('value', function (name, oldValue, newValue, field) {
						if (!me.field.form.isResetting) {
							me.setRenderConfig(newValue);
						}
					});
				}
			});
		},
		initialValueSet: function () {
			var me = this;

			me.settingInitialValue = false;
		},
		reset: function () {
			var me = this;

			me.inherited(arguments);

			me.settingInitialValue = false;
			delete me.value;
			delete me.isImage;

			me.removeWatches();

			me.removeContainer();

			me.init();
		},
		setIsImage: function (isImage) {
			var me = this;

			me.isImage = isImage;
		},
		buildRenderConfig: function () {
			// stub
		},
		setRenderConfig: function (renderConfig) {
			//stub
		},
		getDisplayNode: function () {
			var me = this;

			return me.field.form.getFieldByFieldName(me.field.form.getDataTypeFieldName('DTFile')).displayNode;
		},
		removeContainer: function () {
			var me = this;

			if (me.container) {
				me.field.extensionContainer.removeChild(me.container);
				me.container.destroyRecursive();
				delete me.container;
			}
		},
		removeWatches: function () {
			var me = this;

			if (me.imageWatch) {
				me.imageWatch.unwatch();
				delete me.imageWatch;
			}

			if (me.configWatch) {
				me.configWatch.unwatch();
				delete me.configWatch;
			}

			me.field.form.removeValueSetListener(me.formValueWatchKey);
			delete me.formValueWatchKey;
		},
		destroy: function () {
			var me = this;

			me.removeWatches();

			me.removeContainer();

			delete me.value;
			delete me.fileField;

			me.inherited(arguments);
		}
	});
});