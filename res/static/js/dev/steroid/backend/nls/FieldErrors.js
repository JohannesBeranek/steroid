define({ root:({
	generic:{
		empty: 'Required field',
		error: 'Invalid value'
	},
	numeric: {
		isNaN: 'Value must be a number',
		outOfRange: 'Value must be between ${min} and ${max}'
	},
	string: {
		fixedLen: 'Value must be exactly ${num} characters',
		maxLen: 'Value must be ${num} characters or less'
	},
	recordTag: {
		maxNum: 'Maximum allowed: '
	},
	url: {
		no_domain: 'No domain for current domain group',
		no_live: 'Page is not live',
		no_url: 'Page currently has no urls'
	}
}),
'de':true
});