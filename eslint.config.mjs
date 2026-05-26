import { recommendedJavascript } from '@nextcloud/eslint-config'

export default [
	...recommendedJavascript,
	{
		rules: {
			// We accept deprecated/removed Nextcloud APIs while we keep
			// compatibility with the older NC versions advertised in info.xml.
			'@nextcloud/no-deprecations': 'off',
			'@nextcloud/no-removed-apis': 'off',
			// Error/warn logging is intentional in our async paths.
			'no-console': ['error', { allow: ['error', 'warn'] }],
		},
	},
]
