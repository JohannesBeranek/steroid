define([
	"dojo/_base/declare",
	"dijit/Dialog",
	"dojo/dom-class",
	"dojo/_base/window"
], function (declare, Dialog, domClass, win) {
	var ds = Dialog._dialogStack;
	var DialogLevelManager = Dialog._DialogLevelManager;
	
	var originalShow = DialogLevelManager.show;
	DialogLevelManager.show = function() {
		domClass.add( win.body(), "STDialogOpen" );
		
		originalShow.apply(this, arguments);
	};
	
	var originalHide = DialogLevelManager.hide;
	DialogLevelManager.hide = function() {
		if (ds.length <= 2) { // there is always one dialog on the stack for some reason
			domClass.remove( win.body(), "STDialogOpen" );
		}

		originalHide.apply(this, arguments);
	};
	
	// return declare([Dialog], {});
	return Dialog; // return original Dialog as long as we don't make any changes
});