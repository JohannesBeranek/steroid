define([
	"../../../../dojo/_base/declare",
	"dijit/_Widget",
	"dojo/dom-construct",
	"dojo/string",
	"dojo/i18n!steroid/backend/nls/RTE",
	"dojo/query!acme",
	"dojo/_base/window",
	"dojo/NodeList-manipulate",
	"dojo/dom-class",
	"/steroid/res/static/js/dev/steroid/backend/datatype/form/_DTFormFieldMixin.js",
	"/steroid/res/static/js/dev/steroid/backend/datatype/_DTText.js"
], function (declare, _Widget, domConstruct, string, i18n, query, win, manipulate, domClass, _DTFormFieldMixin, _DTText) {

	return declare([_DTFormFieldMixin, _DTText, _Widget], {
		//templatePath :dojo.moduleUrl("my", "widgets/layout/RichEditor.htm"),
//		templateString : "<textarea id='tAreas' name='tAreas'></textarea>",
		widgetsInTemplate: true,
		editor: null,
		skin: "o2k7",
		skin_variant: "silver",
		theme: "advanced",
		plugins: "safari,pagebreak,style,layer,table,save,advhr,advimage,advlink,emotions,iespell,inlinepopups,insertdatetime,preview,media,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras,template",
//		advanced_buttons1:"save,newdocument,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,styleselect,formatselect,fontselect,fontsizeselect",
//		advanced_buttons2:"cut,copy,paste,pastetext,pasteword,|,search,replace,|,bullist,numlist,|,outdent,indent,blockquote,|,undo,redo,|,link,unlink,anchor,image,cleanup,help,code,|,insertdate,inserttime,preview,|,forecolor,backcolor",
//		advanced_buttons3:"tablecontrols,|,hr,removeformat,visualaid,|,sub,sup,|,charmap,emotions,iespell,media,advhr,|,print,|,ltr,rtl,|,fullscreen",
//		advanced_buttons4:"insertlayer,moveforward,movebackward,absolute,|,styleprops,|,cite,abbr,acronym,del,ins,attribs,|,visualchars,nonbreaking,template,pagebreak",
		advanced_buttons1: "greenbox,bold,italic,underline,strikethrough,|,styleselect,|,fullscreen,|,link,unlink,cleanup,|,bullist,numlist,|,blockquote,cite,|,undo,redo,|,removeformat,|,sub,sup,|,charmap,|,tablecontrols",
		advanced_buttons2: "",
		advanced_buttons3: "",
		advanced_buttons4: "",
		toolbar_location: "default",
		toolbar_align: "left",
		statusbar_location: "bottom",
		resizing: true,
		inline_styles: false,
		content_css: "/res?file=/stlocal/res/css/headings.css",
		template_list_url: "",
		link_list_url: "",
		image_list_url: "",
		media_list_url: "",
		value: "",
		editorInitialized: false,
		editorNode: null,
		valueNode: null,

		constructor: function () {
			this.editor = {};
			this.value = '';
			this.editorNode = null;
			this.valueNode = null;
		},

		startup: function () {
			this.inherited(arguments);

			this.advancedStyles = [];

			for (var i in this.fieldConf.customization) {
				var custom = this.fieldConf.customization[i];
				if (custom.showInStyleSelect) {
					this.advancedStyles.push(i18n[custom.title] + '=' + custom.class);
				}
			}

			this.initEditor();
		},
		initEditor: function () {
			var me = this;

			if (!this.editorNode) {
				this.editorNode = domConstruct.create('div', { id: this.id + '_editor'});
				this.domNode.appendChild(this.editorNode);
			}

			if (!this.valueNode) {
				this.valueNode = domConstruct.create('textarea', { innerHTML: this.get('value'), style: 'display: none' });
				this.domNode.appendChild(this.valueNode);
			}

			if ((this.theme == "simple") && (this.toolbar_location == "default")) {
				var ed = new tinymce.Editor(this.editorNode, {
					theme: this.theme,
					skin: this.skin,
					skin_variant: this.skin_variant,
					convert_urls: false
				});
			} else if ((this.theme == "simple") && (this.toolbar_location == "top")) {
				var ed = new tinymce.Editor(this.editorNode, {
					theme: "advanced",
					skin: this.skin,
					skin_variant: this.skin_variant,
					theme_advanced_buttons1: "bold,italic,underline,strikethrough,|,undo,redo,|cleanup,|,bullist,numlist",
					theme_advanced_buttons2: "",
					theme_advanced_toolbar_align: this.toolbar_align,
					theme_advanced_toolbar_location: this.toolbar_location,
					convert_urls: false
				});
			} else {
				var tool_loc = (this.toolbar_location == "default") ? "top" : this.toolbar_location;
				var ed = new tinymce.Editor(this.editorNode.id, {
					theme: this.theme,
					skin: this.skin,
					skin_variant: this.skin_variant,
					plugins: this.plugins,

					theme_advanced_buttons1: this.advanced_buttons1,
					theme_advanced_buttons2: this.advanced_buttons2,
					theme_advanced_buttons3: this.advanced_buttons3,
					theme_advanced_buttons4: this.advanced_buttons4,
					theme_advanced_toolbar_location: tool_loc,
					theme_advanced_toolbar_align: this.toolbar_align,
					theme_advanced_statusbar_location: this.statusbar_location,
					theme_advanced_resizing: this.resizing,

					content_css: this.content_css,

					// Drop lists for link/image/media/template dialogs
					template_external_list_url: this.template_list_url,
					external_link_list_url: this.link_list_url,
					external_image_list_url: this.image_list_url,
					media_external_list_url: this.media_list_url,
					content: '',
					onchange_callback: dojo.hitch(this, 'editorChange'),
					width: '100%',
					submit_patch: false,
					add_form_submit_trigger: true,
					hidden_input: 0,
					convert_urls: false,
					paste_auto_cleanup_on_paste: true,
					theme_advanced_styles: this.advancedStyles.join(','),
					formats: {
						underline: {inline: 'u', exact: true}
					},
					setup: function (editor) {
						// FUKKIT
//						editor.addButton('greenbox', {
//							'title':i18n.greenbox,
//							'image':'/stlocal/res/static/img/arrow_more.png',
//							'onclick':function () {
//								var html = '<div class="box">' + ed.selection.getContent({ 'format':'raw' }) + '</div>';
//
//								ed.execCommand('mceInsertRawHTML', false, html);
//							}
//						});
					},
//					element_format: 'html',
//					fix_list_elements: true,
//					valid_children: 'div[div,p,ol,ul,table]',
					valid_elements: "@[class|title],a[name|href|target|title|class],strong/b,em/i,strike,u,"
						+ "p,ol,ul,li,br,img[src|alt=|title|width|height],"
						+ "-sub,-sup,-blockquote,-table[border=0|cellspacing|cellpadding|width|height|align],"
						+ "-tr[valign,align],tbody,thead,tfoot,#td[colspan|rowspan|width|height|align|valign]"
						+ ",#th[colspan|rowspan|width|height|align|valign],caption,div[class],"
						+ "-span,-code,-pre,address,-h1,-h2,-h3,-h4,-h5,-h6,hr,"
						+ "dd,dl,dt,cite,abbr,acronym"
				});
			}

			this.editor = ed;
			this.editor.onInit.add(dojo.hitch(this, function () {
				this.editorInitialized = true;
				this.set('value', this.value);
			}));

			this.addValueSetListenerOnce(function () {
				setTimeout(function () {
					me.editor.render();
					me.initComplete();
				}, 200);
			});
		},
		destroyEditor: function () {
			if (!this.editorInitialized) {
				this.editor.onInit.add(dojo.hitch(this, function () {
					this.destroyEditor();
				}));

				return;
			}

			var id = this.editor.editorContainer;
			this.editor.destroy();
			tinyMCE.remove(this.editor);
			domConstruct.destroy(this.editorNode);
			delete this.editorNode;

			domConstruct.destroy(dojo.byId(id));
		},
		beforeDomMove: function () {
			this.close();
		},
		afterDomMove: function () {
			this.open();
		},
		close: function () {
			var me = this;

			this.destroyEditor();
			this.editorInitialized = false;
		},
		open: function () {
			var me = this;

			me.value = me.STValue;
			me.initEditor();
		},
		doCustomization: function (editor) {
			var me = this;

			win.withDoc(editor.contentDocument, function () {
				for (var i in me.fieldConf.customization) {
					var custom = me.fieldConf.customization[i];
					var selector = [];

					if (custom.tags) {
						selector.push(custom.tags);
					}

					if (custom.class) {
						selector.push('.' + custom.class);
					}

					if (!selector.length) {
						return;
					}

					var res = query(selector.join(','));

					res.forEach(function (node, index, array) {
						var newNode = domConstruct.toDom(custom.replaceWithHtml.replace('|', (node.innerText || node.textContent)));
						domConstruct.place(newNode, node, 'replace');
					});
				}
			}, this);
		},
		editorChange: function (editor) {
			if (!this.editorInitialized) {
				return;
			}

			var content = editor.getContent();

			if (content != this.STValue) {
				this.doCustomization(editor);
				content = editor.getContent();

				if (this.valueNode) {
					this.valueNode.innerHTML = content;
				}

				this.value = content;

				this.STSetValue(content);
				this.set('STValue', content);

				this.set('changeWatch', this.get('changeWatch') * (-1));

				this.valuesHaveBeenSetDef = true;
			}
		},
		reset: function () {
			var me = this;

			me.set('value', '');
			this.value = '';
			this.STValue = '';

			delete me.originalValue;
		},
		_setValueAttr: function (value) {
			value = value || '';

			this.value = value;

			if (this.editorInitialized) {
				this.editor.setContent(value);
				this.doCustomization(this.editor);
			}

			if (this.valueNode) {
				this.valueNode.innerHTML = value;
			}

			this.inherited(arguments);
		},
		_getValueAttr: function () {
			var me = this;

			if (me.editorInitialized) {
				return me.editor.getContent();
			}

			return me.value;
		},
		destroy: function () {
			this.destroyEditor();
			this.inherited(arguments);
		}
	});
});
