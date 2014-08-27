define([
	"dojo/_base/declare",
	"dijit/_WidgetBase",
	"dijit/MenuBarItem",
	"dojox/timing",
	"dojo/date/locale"
], function (declare, _WidgetBase, MenuBarItem, Timer, DateLocale) {
	return declare([_WidgetBase], {
		postCreate: function () {
			var me = this;

			me.BTTime = new MenuBarItem({
				label: DateLocale.format(new Date(), { formatLength: 'medium' }),
				style: 'float:right;',
				class: 'STForceIcon STMenuTime',
				disabled: true
			});

			// FIXME: use native js setInterval
			// FIXME: showing minutes should be enough
			var t = new dojox.timing.Timer(1000);

			t.BTTime = me.BTTime;

			t.onTick = function () {
				me.BTTime.set('label', DateLocale.format(new Date(), { formatLength: 'medium' }));
			};

			t.start();
		},
		destroy: function () {
			var me = this;

			me.BTTime.destroyRecursive();
			delete me.BTTime;

			me.inherited(arguments);
		}
	});
});