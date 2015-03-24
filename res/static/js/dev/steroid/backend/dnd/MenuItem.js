define([
	"dojo/_base/declare",
	"dijit/TitlePane",
	"steroid/backend/mixin/_SubFormMixin",
	"dojo/dom-construct",
	"dojo/i18n!steroid/backend/nls/RecordClasses",
	"dojo/on",
	"dojo/dom-style",
	"dojo/dom-class",
	"dojo/i18n!steroid/backend/nls/Menu",
	"dojox/lang/functional",
	"dojo/_base/event"
], function (declare, TitlePane, _SubFormMixin, domConstruct, i18nRC, on, domStyle, domClass, i18nMenu, langFunc, event) {

// FIXME: it's not possible to re-sort newly added items (works for loaded items)
// FIXME: sometimes first items gets negative sorting, which might be a problem with db
	return declare([TitlePane, _SubFormMixin], {

		"class": 'STMenuItem',
		type: '', // will be set by DTMenuItemForeignReference to include the widget's ID
		generated: false,
		userChange: false,
		readOnly: false,
		submitFieldsIfDirty: null,
		level: 0,
		publishHandle: null,

		construct: function () {
			var me = this;

			me.submitFieldsIfDirty = ['primary', 'parent:RCMenuItem', 'menu'];
		},
		postCreate: function () {
			var me = this;

			me.inherited(arguments);

			me.addInitListener(function () {
				me.setupCloseButton();
			});

			me.set('open', false);
		},
		startup: function () {
			var me = this;

			me.inherited(arguments);

			me.addValueSetListener(function () {
				me.setGeneratedVisibility();
				me.set('title', me.collectTitle(me));
			});
		},
		collectTitle: function (origin) {
			var me = this, menuItemTitle = '', title;

			if (me.ownFields['title'] && me.ownFields['title']._dt && (title = me.ownFields['title']._dt.collectTitle())) {
				menuItemTitle = title;
			}

			if (!menuItemTitle && me.ownFields['page'] && me.ownFields['page']._dt && (title = me.ownFields['page']._dt.collectTitle())) {
				menuItemTitle = title;
			}

			if (!menuItemTitle) {
				menuItemTitle = i18nMenu.untitled;
			}

			if (me.generated) {
				menuItemTitle += ' (' + i18nMenu.generated + ')';
			}

			return menuItemTitle;
		},
		setupCloseButton: function () {
			var me = this;

			if (!(me.readOnly || me.closeNode || (me.generated && !me.userChange))) {
				me.closeNode = domConstruct.create('div', { "class": 'closeNode', innerHTML: '&nbsp;', style: 'display: ' + (me.generated ? 'none' : 'block' ) });
				me.titleBarNode.appendChild(me.closeNode);

				me.closeHandle = on(me.closeNode, 'click', function () {
					me.remove();
				});
			}

			if (!me.visibleNode) {
				me.visibleNode = domConstruct.create('div', { "class": 'visibleNode', innerHTML: '&nbsp;' });
				me.titleBarNode.appendChild(me.visibleNode);

				me.publishHandle = on(me.visibleNode, 'click', function(e){
					me.toggleVisible();

					event.stop(e);

					return false;
				});
			}
		},
		toggleVisible: function(){
			var me = this;

			me.addValueSetListenerOnce(function(){
				var showInMenuField = me.getFieldByFieldName('showInMenu');

				showInMenuField.set('value', !showInMenuField.get('value'));
			});
		},
		beforeDomMove: function () {
			// stub
		},
		afterDomMove: function () {
			//stub
		},
		getIdentity: function () {
			var me = this;

			return me.record && me.record.primary ? me.record.primary : me.id;
		},
		setGeneratedVisibility: function () {
			var me = this;

			// TODO: if there is some way to determine that visibility has to be changed, we should only change visibility then ;
			// maybe even only call function then

			if (me.generated && !me.userChange) {
				domClass.add(me.domNode, 'STGenerated');
			} else {
				domClass.remove(me.domNode, 'STGenerated');
			}

			if (me.closeNode) {
				domStyle.set(me.closeNode, 'display', ( !me.readOnly && (!me.generated || me.userChange) ? 'block' : 'none' ));
			}

			if (me.visibleNode) {
				var showInMenu = me.getFieldByFieldName('showInMenu').get('value');

				if (showInMenu) {
					domClass.add(me.visibleNode, 'visible');
				} else {
					domClass.remove(me.visibleNode, 'visible');
				}
			}
		},
		setIsNewFromValue: function () {
			var me = this;

			if (!me.generated) {
				me.inherited(arguments);
			}
		},
		fieldChanged: function () {
			var me = this;

			me.userChange = (me.getDirtyNess() > 0);

			if (me.generated && me.userChange) {
				me.submitFieldsIfDirty = langFunc.keys(me.ownFields);
			}

			me.setGeneratedVisibility();

			me.inherited(arguments);
		},
		removeCloseButton: function () {
			var me = this;

			if (me.closeHandle) {
				me.closeHandle.remove();
				delete me.closeHandle;

				domConstruct.destroy(me.closeNode);
				delete me.closeNode;
			}

			if(me.publishHandle){
				me.publishHandle.remove();
				delete me.publishHandle;

				domConstruct.destroy(me.visibleNode);
				delete me.visibleNode;
			}
		},
		_setReadOnlyAttr: function (readOnly) {
			var me = this;

			if (readOnly) {
				me.removeCloseButton();
			} else {
				if (!me.generated || me.userChange) {
					me.setupCloseButton();
				}
			}

			me.inherited(arguments);
		},
		remove: function () {
			var me = this;

			me.inherited(arguments);

			me.destroyRecursive();
		},
		destroy: function () {
			var me = this;

			me.removeCloseButton();

			me.inherited(arguments);
		}
	});
});
