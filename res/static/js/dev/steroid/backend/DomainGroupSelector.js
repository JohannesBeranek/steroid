define([
	"dojo/_base/declare",
	"dijit/_WidgetBase",
	"dijit/MenuBarItem",
	"dojo/i18n!steroid/backend/nls/DomainGroupSelector",
	"dijit/DropDownMenu",
	"dijit/PopupMenuBarItem",
	"dijit/PopupMenuItem",
	"dijit/MenuItem",
	"dijit/a11yclick",
	"dijit/registry",
], function (declare, _WidgetBase, MenuBarItem, i18n, DropDownMenu, PopupMenuBarItem, PopupMenuItem, MenuItem, a11yclick, registry) {

	var DropDownMenuWithClickablePopupMenuItems = declare([DropDownMenu], {
		// modified from _MenuBase, Dojo 1.9
		// removed check for this.popup, so PopupMenuItem is handled the same way as normal MenuItem
		onItemClick: function(/*dijit/_WidgetBase*/ item, /*Event*/ evt){
			// summary:
			//		Handle clicks on an item.
			// tags:
			//		private

			if(this.passive_hover_timer){
				this.passive_hover_timer.remove();
			}

			this.focusChild(item);

			if(item.disabled){
				return false;
			}


			// before calling user defined handler, close hierarchy of menus
			// and restore focus to place it was when menu was opened
			this.onExecute();

			// user defined handler for click
			item._onClick ? item._onClick(evt) : item.onClick(evt);
		} 
	});


	return declare([_WidgetBase], {

		domainGroupMenu: null,
		domainGroupMenuItem: null,
		menuBar: null,
		backend: null,
		listeners: null,
		items: [],

		constructor: function () {
			this.listeners = [];
			this.items = [];
		},
		postCreate: function () {
			var me = this;

			me.domainGroupMenu = new DropDownMenuWithClickablePopupMenuItems(); // new DropDownMenu({});

			me.domainGroupMenuItem = new PopupMenuBarItem({
				label: me.backend.config.system.domainGroups.current.title,
				class: 'STForceIcon STIconDomainGroup',
				popup: me.domainGroupMenu,
				style: 'float:right;'
			});

			var availableDomainGroups = dojo.clone(me.backend.config.system.domainGroups.available);

			var root = me.generateHierarchy(availableDomainGroups, { children: [] });

			me.addMenuItems(root.children, me.domainGroupMenu);

			me.menuBar.addChild(me.domainGroupMenuItem);
		},
		generateHierarchy: function (all, root) {
			var me = this;

			for (var i = 0; i < all.length; i++) {
				for (var j = 0; j < all.length; j++) {
					if (all[i].parent_primary == all[j].primary) {
						if (!all[j].children) {
							all[j].children = [];
						}

						all[i].hasParent = true;

						all[j].children.push(all[i]);
						break;
					}
				}

				if (!all[i].hasParent) {
					root.children.push(all[i]);
				}
			}

			return root;
		},
		addMenuItems: function (domainGroups, menu) {
			var me = this;

			domainGroups = domainGroups.sort(function (a, b) {
				return a.title.toLowerCase() > b.title.toLowerCase() ? 1 : -1;
			});

			var itemClickFunction = function (e) {
				// window.setTimeout(dijit.popup.close, 1);
				e.preventDefault();
				e.stopPropagation();

				dijit.popup.close(me.domainGroupMenu);
				me.backend.doStandBy();

				var conf = {
					data: {
						requestType: 'selectDomainGroup',
						recordID:  this.domainGroup.primary // registry.byNode(this).domainGroup.primary 
					},
					success: function (response) {
						me.backend.domainGroupSwitched(response);
					},
					error: function (response) {
						// FIXME: error handling
						console.error(response);
					}
				};

				me.backend.STServerComm.sendAjax(conf);

				return false;
			};

			for (var i = 0, ilen = domainGroups.length; i < ilen; i++) {
				var conf = {
					label: domainGroups[i].title,
					domainGroup: domainGroups[i],
					iconClass: 'STForceIcon STDomainGroup_' + ' STDomainGroup_' + (!domainGroups[i].excludeFromSearch ? 'hasSearch' : 'noSearch'),
					class: 'STForceIcon STDomainGroup_' + (domainGroups[i].hasTracking ? 'hasTracking' : 'noTracking'),
					onClick: itemClickFunction
				};

				if (domainGroups[i].children) {
					var subMenu = new DropDownMenuWithClickablePopupMenuItems(); // new DropDownMenu({});

					me.addMenuItems(domainGroups[i].children, subMenu);

					conf.popup = subMenu;

					var item = new PopupMenuItem(conf);
				} else {
					var item = new MenuItem(conf);
				}

				// me.listeners.push(item.on(a11yclick, ));

				me.items.push(item);

				menu.addChild(item);
			}
		},
		clearMenu: function () {
			var me = this;

			for (var i = 0, ilen = me.listeners.length; i < ilen; i++) {
				me.listeners[i].remove();
			}

			me.listeners = [];

			for (var i = 0, ilen = me.items.length; i < ilen; i++) {
				me.items[i].destroyRecursive();
			}

			me.items = [];

			me.menuBar.removeChild(me.domainGroupMenuItem);

			me.domainGroupMenu.destroyRecursive();
			delete me.domainGroupMenu;

			me.domainGroupMenuItem.destroyRecursive();
			delete me.domainGroupMenuItem;
		},
		destroy: function () {
			var me = this;

			me.clearMenu();

			delete me.menuBar;
			delete me.backend;

			me.inherited(arguments);
		}
	});
});