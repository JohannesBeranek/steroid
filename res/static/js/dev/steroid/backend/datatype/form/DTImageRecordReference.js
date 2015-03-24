define([
	"dojo/_base/declare",
	"steroid/backend/datatype/form/DTRecordSelector",
	"dojo/dom-construct",
	"dojo/_base/lang"
], function (declare, DTRecordSelector, domConstruct, lang) {

	return declare([DTRecordSelector], {
		_setValueAttr: function(value){
			var me = this;

			var tmpValue = value;

			if(!me.form.isFilterPane){
				tmpValue = me.showImage(value);
			}

			me.inherited(arguments, [tmpValue]);
		},
		getPreview: function(){
			var me = this;

			if(me.displayNode){
				var tmp = domConstruct.create('div');

				tmp.appendChild(lang.clone(me.displayNode));

				var preview = tmp.innerHTML;

				domConstruct.destroy(tmp);

				return preview;
			}

			return null;
		},
		showImage: function(value){
			var me = this;

			if (value !== null && lang.isObject(value) && !lang.isArray(value)) {
				value = [value];
			}

			if ((!value || value.length && value[0] && value[0].primary === 0) && me.displayNode) {
				domConstruct.destroy(me.displayNode);
				delete me.displayNode;
				
				if (typeof me.displayNodeWrap !== 'undefined') {
					domConstruct.destroy(me.displayNodeWrap);
					delete me.displayNodeWrap;
				}
			}

			var tmpValue = value;

			if (value && value.length && value[0] && value[0].primary !== 0) {
				tmpValue = {
					filename:value[0].filename.filename || value[0].filename,
					_title:value[0]._title,
					primary:value[0].primary
				};

				if (value[0].filename.cached) {
					if (!me.displayNode) {
						me.displayNode = domConstruct.create('img', { "class":'STFileName', src:'' });

						me.displayNodeWrap = domConstruct.create('div', { "class":'STFileNameWrap' });
						me.displayNodeWrap.appendChild(me.displayNode);
						
						domConstruct.place(me.displayNodeWrap, me.domNode, 'after');
					}

					me.displayNode.src = value[0].filename.cached;
				}
			}

			return tmpValue;
		},
		destroy: function(){
			var me = this;

			domConstruct.destroy(me.displayNode);
			delete me.displayNode;
			
			if (typeof me.displayNodeWrap !== 'undefined') {
				domConstruct.destroy(me.displayNodeWrap);
				delete me.displayNodeWrap;
			}

			me.inherited(arguments);
		}
	});
});
