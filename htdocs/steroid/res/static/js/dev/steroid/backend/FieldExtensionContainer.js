define([
	"dojo/_base/declare",
	"dijit/layout/TabContainer",
	"dojo/dom-class"
], function (declare, TabContainer, domClass) {
	return declare([TabContainer], {
		addChild: function (/*dijit/_WidgetBase*/ child, /*Integer?*/ insertIndex) {
			var me = this;

			if (!domClass.contains(me.domNode, 'hasChildren')) {
				domClass.add(me.domNode, 'hasChildren');
			}

			me.inherited(arguments);
		},
		removeChild: function (/*dijit/_WidgetBase*/ page) {
			var me = this;

			me.inherited(arguments);

			if (!me.hasChildren()) {
				domClass.remove(me.domNode, 'hasChildren');
			}
		}
	});
});