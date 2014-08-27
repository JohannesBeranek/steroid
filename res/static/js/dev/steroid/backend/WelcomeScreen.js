define([
	"dojo/_base/declare",
	"dijit/layout/BorderContainer",
	"dijit/layout/ContentPane",
	"dijit/TitlePane",
	"dojo/i18n!steroid/backend/nls/Backend",
	"dijit/layout/AccordionContainer",
	"dojo/dom-class"
], function (declare, BorderContainer, ContentPane, TitlePane, i18n, AccordionContainer, domClass) {

	return declare([BorderContainer], {
		postCreate: function(){
			var me = this;

			me.topBar = new ContentPane({
				style:'height: 53px;',
				region:'top',
				content:'<img src="/steroid/res/static/img/5teroid.jpg" style="width:354px;height:53px;margin:0 auto;display:block;"/>'
			});

			me.center = new ContentPane({
				region:'center',
				style:'width: 60%',
				content:'<p style="text-align:center; font-size: 2em;">Subweb: ' + me.backend.config.system.domainGroups.current.title + '</p>'
			});

			me.left = new TitlePane({
				title:i18n.welcome.changeLog.title,
				content:me.createWelcomeBoxContent(me.backend.config.changeLog),
				style:'width: 20%;height:100%;padding:0;',
				region:'left'
			});

			me.right = new TitlePane({
				title:i18n.welcome.messageBox.title,
				content:me.createWelcomeBoxContent(me.backend.config.messageBox),
				style:'width: 20%;padding:0;',
				region:'right'
			});

			me.addChild(me.topBar);
			me.addChild(me.left);
			me.addChild(me.center);
			me.addChild(me.right);
		},
		domainGroupSwitched: function(domainGroup){
			var me = this;

			me.center.set('content', '<p style="text-align:center; font-size: 2em;">Subweb: ' + domainGroup.title + '</p>');
		},
		createWelcomeBoxContent:function (content) {
			var me = this;

			var container = new AccordionContainer({});

			for (var i = 0; i < content.length; i++) {
				var entry = content[i];

				var title = entry.date + ' - ' + entry.title + ' - by ' + entry.creator;

				if (entry.alert) {
					title = '<span class="alertHint">(!!!)</span>' + title;
				}

				var entryPane = new ContentPane({
					title:title,
					content:'<p>' + entry.text + '</p>',
					isPersonal:entry.user == me.backend.config.User.values.primary,
					startup:function () {
						if (this.isPersonal) {
							domClass.add(this.domNode.parentNode.parentNode, 'personal');
						}
					}
				});

				container.addChild(entryPane);
			}

			return container;
		},
		destroy: function(){
			var me = this;

			me.topBar.destroyRecursive();
			delete me.topBar;

			me.center.destroyRecursive();
			delete me.center;

			me.left.destroyRecursive();
			delete me.left;

			me.right.destroyRecursive();
			delete me.right;

			me.inherited(arguments);
		}
	});
});
