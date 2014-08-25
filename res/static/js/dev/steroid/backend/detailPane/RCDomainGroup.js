define([
	"dojo/_base/declare",
	"steroid/backend/DetailPane",
	"steroid/backend/YesNoDialog"
], function (declare, DetailPane, YesNoDialog) {
	return declare([DetailPane], {
		actionSuccessDialog: null,

		afterActionSuccess: function (response, action) {
			var me = this, record = response && response.data && response.data.items ? response.data.items[0] : null;

			if (record && action == 'createRecord' || action == 'publishRecord' || action == 'saveRecord' && (record.primary !== me.backend.config.system.domainGroups.current.primary)) {

				if (me.actionSuccessDialog) {
					if (me.actionSuccessDialog) {
						me.actionSuccessDialog.destroy();

						delete me.actionSuccessDialog;
					}
				}

				me.actionSuccessDialog = new YesNoDialog({
					messageType: 'domainGroupModified',
					onYes: function () {
						me.backend.switchDomainGroup(record.primary);
					}
				});

				me.actionSuccessDialog.show();
			} else if (action == 'deleteRecord') {
				me.backend.switchDomainGroup(me.backend.config.system.domainGroups.available[0].primary);
			}

			return null;
		},
		destroy: function () {
			var me = this;

			me.inherited(arguments);

			if (me.actionSuccessDialog) {
				me.actionSuccessDialog.destroy();

				delete me.actionSuccessDialog;
			}
		}
	});
});