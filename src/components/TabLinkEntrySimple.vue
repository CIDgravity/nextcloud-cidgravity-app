<template>
	<div v-if="hasLink">
		<li class="sharing-entry">
			<slot name="avatar" />
			<a :href="link" target="_blank" rel="noopener noreferrer">
				<div class="sharing-entry__desc">
					<span class="sharing-entry__title">{{ title }}</span>
					<p v-if="subtitle">
						{{ subtitle }}
					</p>
				</div>
			</a>

			<NcActions v-if="$slots['default']"
				ref="actionsComponent"
				:inline="1"
				class="sharing-entry__actions"
				menu-align="right"
				:aria-expanded="ariaExpandedValue">
				<slot />
			</NcActions>
		</li>
	</div>

	<div v-else>
		<li class="sharing-entry">
			<slot name="avatar" />
			<div class="sharing-entry__desc">
				<span class="sharing-entry__title">{{ title }}</span>
				<p v-if="subtitle">
					{{ subtitle }}
				</p>
			</div>

			<NcActions v-if="$slots['default']"
				ref="actionsComponent"
				:inline="1"
				class="sharing-entry__actions"
				menu-align="right"
				:aria-expanded="ariaExpandedValue">
				<slot />
			</NcActions>
		</li>
	</div>
</template>

<script>
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'

export default {
	name: 'TabLinkEntrySimple',

	components: {
		NcActions,
	},

	props: {
		title: {
			type: String,
			default: '',
		},
		subtitle: {
			type: String,
			default: '',
		},
		isUnique: {
			type: Boolean,
			default: true,
		},
		ariaExpanded: {
			type: Boolean,
			default: null,
		},
		link: {
			type: String,
			default: '',
		},
	},

	computed: {
		ariaExpandedValue() {
			if (this.ariaExpanded === null) {
				return this.ariaExpanded
			}
			return this.ariaExpanded ? 'true' : 'false'
		},
		hasLink() {
			return this.link !== ''
		},
	},
}
</script>

<style lang="scss" scoped>
.sharing-entry {
	display: flex;
	align-items: center;
	min-height: 44px;
	&__desc {
		padding: 8px;
		padding-inline-start: 10px;
		line-height: 1.2em;
		position: relative;
		flex: 1 1;
		min-width: 0;
		p {
			color: var(--color-text-maxcontrast);
		}
	}
	&__title {
		white-space: nowrap;
		text-overflow: ellipsis;
		overflow: hidden;
		max-width: inherit;
	}
	&__actions {
		margin-inline-start: auto !important;
	}
}
</style>
