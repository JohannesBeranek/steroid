define([
	"dojo/_base/declare",
	"dojo/on",
	"dojo/dom-class"
], function (declare, on, domClass) {
	return declare([], {
		keyUpListener: null,
		keyUpTimeout: null,

		startup: function () {
			var me = this;

			if (me.fieldConf.dataType !== 'DTRTE' && me.fieldConf.maxLen) { // RTE already has keyup listener
				me.keyUpListener = on(me.focusNode, 'keyup', function () {
					if (me.keyUpTimeout) {
						clearTimeout(me.keyUpTimeout);
					}

					me.keyUpTimeout = setTimeout(function () {
						me.updateContentLengthIndicator();
					}, 50);
				});
			}

			me.inherited(arguments);
		},
		_setValueAttr: function (value) {
			var me = this;

			me.inherited(arguments);

			if (!me.isHidden() && me.fieldConf.maxLen) {
				me.updateContentLengthIndicator();
			}
		},
		updateContentLengthIndicator: function () {
			var me = this;

			var remaining = me.fieldConf.maxLen - me.get('value').length;

			me.labelNode.innerHTML = me.getLabel() + ' (' + remaining + ')';

			if (remaining < (me.fieldConf.maxLen / 10)) {
				domClass.replace(me.labelNode, 'STContentLength_crit', 'STContentLength_ok STContentLength_warn STContentLength_crit');
			} else if (remaining < (me.fieldConf.maxLen / 4)) {
				domClass.replace(me.labelNode, 'STContentLength_warn', 'STContentLength_ok STContentLength_warn STContentLength_crit');
			} else {
				domClass.replace(me.labelNode, 'STContentLength_ok', 'STContentLength_ok STContentLength_warn STContentLength_crit');
			}
		},
		destroy: function () {
			var me = this;

			if (me.keyUpTimeout) {
				clearTimeout(me.keyUpTimeout);
			}

			if (me.keyUpListener) {
				me.keyUpListener.remove();
				delete me.keyUpListener;
			}

			me.inherited(arguments);
		}
	});
});