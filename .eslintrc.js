module.exports = {
	extends: [
		'@nextcloud',
	],
	settings: {
		'import/resolver': {
		  exports: {},  // enable the exports resolver
		},
	},
	rules: {
		'@nextcloud/no-deprecations': 'off',
		'@nextcloud/no-removed-apis': 'off',
		'import/namespace': 'off',
		'import/named': 'off',
		'n/no-unsupported-features/node-builtins': ['error', {
      		ignores: ['navigator']
    	}]
	},
}
