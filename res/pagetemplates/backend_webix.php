<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8">
		<meta http-equiv='Content-Type' content='text/html;charset=UTF-8'>
		<meta name="robots" content="noindex,nofollow"/>
	
		<title>CMS gruene.at 2013</title>
		
		<link rel="stylesheet" href="/steroid/res/static/js/dev/webix/webix.css">
		<script src="/steroid/res/static/js/dev/webix/webix.js"></script>
		<script src="/steroid/res/static/js/dev/webix/i18n/de.js"></script>	
	</head>
	<body>
		<script>
			webix.i18n.setLocale("de-DE");

			// TODO: steroid i18n
<?php
if ($this->isBackendUser) {
?>

<?php
} else {
?>

			function submitLogin() {
				$$('login_form').blockEvent();
				$$('tabs').disable();
				$$('tabs').showProgress({ type: 'icon', delay: 3000 });
				
				var enableLogin = function( e ) {
					$$('tabs').hideProgress();
					$$('tabs').enable();
					$$('login_form').unblockEvent();
					
					if (e !== undefined) {
						webix.message({ type: 'error', text: e });
					}
				};
				
			
				
				try {
					var url = '<?= $this->config['interface']['basePath'] ?>?ajax=1';
					var data = $$('login_form').getValues();
					data.requestType = 'login';
					data.login = '<?= $this->config['login']['class'] ?>';
					// TODO: data.beLang
					
					webix.ajax().post(url, data, function(text, data, req) {						
						if (req.status === 200) {
							var d = data.json();
							
							if (typeof d.success === 'undefined' || (!d.success && !d.error)) {
								enableLogin('Unknown server response'); // TODO: i18n
							} else if (d.error) {
								enableLogin(d.message); // TODO: use d.error and i18n
							} else {
								// TODO: log user in
								console.log('success');
							}
						} else {
							switch(req.status) {
								case 0:
									enableLogin('Offline or unable to connect to server'); // TODO: i18n
								break;
								default:
									enableLogin('Unknown error'); // TODO: i18n

							}
						}
						
						
					});
				} catch(e) {
					enableLogin(e.message);
				}
			}
			

			function validateForm() {
				console.log(arguments);
				this.getFormView().validate();
			}

			var UIDef = {
				view: 'window',
				head: false,
				hidden: false,
				position: 'center',
				borderless: true,
				id: 'login',
				maxWidth: 500,
				body: {
					view: 'tabview',
					id: 'tabs',
					cells: [
						{
							view: 'form',
							id: 'login_form',
							header: 'Login',
							elements: [
							// TODO: i18n for placeholders
								{ view: 'text', label: 'Username', name: 'username', placeholder: 'max.mustermann@gruene.at', labelPosition: 'top',
								required: true, id: 'login_username',
								on: { onTimedKeyPress: validateForm } },
								{ view: 'text', label: 'Password', type: 'password', name: 'password', placeholder: '********',	labelPosition: 'top',
								required: true, 
								on: { onTimedKeyPress: validateForm } },
								{ view: "button", label: "Login", id: "login_login", type: "form", click: submitLogin, disabled: true }
								
							],
							on: {
//								onValidationError: function(key, obj) {
//									webix.message({ type: 'error', text: 'validation error on field ' + this.elements[key].config.label }); // TODO: i18n
//								},
								onAfterValidation: function(isValid, fields) { 
									$$('login_login')[isValid ? 'enable' : 'disable'](); 
								},
								onSubmit: function(field, event) { 
									if (!field.validate()) {
//										webix.message({ type: 'error', text: 'validation error on field ' + field.config.label });
										return;
									}
									
									
									var index = this.index(field), childViews = this.getChildViews();
									
									if (index === (childViews.length - 2)) {
										for (var i = 0; i < (childViews.length - 2); i++) {
											if (!childViews[i].validate()) {
												childViews[i].focus();
												return;
											}
										}
										
										submitLogin();
									} else {
										if (this.validate()) {
											submitLogin();
										} else {
											childViews[index + 1].focus();
										}
									}
									
								}
							}
						}
					]
				}
			};
<?php
}
?>
		</script>
		
<?php

if (!$this->isBackendUser) {
	foreach ($this->config['loginext'] as $loginExt) {
		if (!empty($loginExt['includeFilesAfter'])) {
			foreach ((array)$loginExt['includeFilesAfter'] as $includeFile) { ?>
				<script src="<?= htmlspecialchars($includeFile) ?>"></script><?
			}
		}
	}
}

?>
		<script>
			webix.ui(UIDef);
			webix.extend($$('tabs'), webix.ProgressBar);
			webix.UIManager.setFocus('login_username');
		</script>
	</body>
</html>