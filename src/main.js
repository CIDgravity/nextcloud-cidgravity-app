import Vue from 'vue'
import axios from '@nextcloud/axios'

import { generateOcsUrl } from '@nextcloud/router'
import { translate, translatePlural } from 'nextcloud-l10n'

import GatewayTab from './views/GatewayTab.vue'

Vue.prototype.t = translate
Vue.prototype.n = translatePlural

const View = Vue.extend(GatewayTab)
let tabInstance = null

window.addEventListener('DOMContentLoaded', function() {
	if (OCA.Files && OCA.Files.Sidebar) {

		const getExternalStorageConfig = (fileInfo) => {
			if (!fileInfo || fileInfo.mountType !== 'external') {
				return Promise.resolve({
					isCidgravityStorage: false,
					isError: false,
				})
			}

			const url = generateOcsUrl('apps/cidgravity_gateway/get-external-storage-config?fileId=' + fileInfo.id, 2)

			return axios.get(url)
				.then(response => {
					if (response.data.success && (response.data.configuration.is_cidgravity || response.data.configuration.is_cidgravity_gateway)) {
						return {
							isCidgravityStorage: true,
							configuration: response.data.configuration,
							isError: false,
						}
					} else {
						return {
							isCidgravityStorage: false,
							isError: false,
						}
					}
				})
				.catch(error => {
					console.error(error)
					return {
						isCidgravityStorage: false,
						isError: true,
					}
				})
		}

		const gatewayTab = new OCA.Files.Sidebar.Tab({
			id: 'cidgravity_gateway',
			name: t('cidgravity_gateway', 'CIDgravity'),

			// use svg string directly because importing with file seems not working here
			iconSvg: '<?xml version="1.0" encoding="UTF-8"?><svg fill="none" viewBox="0 0 688 591" xmlns="http://www.w3.org/2000/svg"><path d="m568 439.69-233 133.31" stroke="#fff" stroke-miterlimit="1" stroke-width="25"/><path d="m335 39.763 233 133.31" stroke="#fff" stroke-miterlimit="1" stroke-width="25"/><path d="m102 173.07 233-133.31" stroke="#fff" stroke-miterlimit="1" stroke-width="25"/><path d="M335 580L102 442V166L335 304V580Z" stroke="#fff" stroke-miterlimit="1" stroke-width="25"/><path d="M335 573L568 437.333V166L335 301.667V573Z" stroke="#fff" stroke-miterlimit="1" stroke-width="25"/><path d="m568 173.5-233-134-233 134 233 133.5 233-133.5z" stroke="#fff" stroke-miterlimit="1" stroke-width="25"/><ellipse cx="335.5" cy="303" rx="44.5" ry="43" fill="#fff"/><path d="m331.54 33.604-24.4 13.536c-0.122 4.5104 0.887 9.0079 3.164 13.111 7.501 13.522 25.915 17.643 41.128 9.204 6.557-3.6375 11.449-9.0148 14.293-15.039l-34.185-20.812z" clip-rule="evenodd" fill="#fff" fill-rule="evenodd"/><path d="m99.974 449.06 24.125 14.02c3.936-2.206 7.281-5.377 9.639-9.434 7.77-13.37 1.876-31.295-13.166-40.037-6.483-3.767-13.607-5.214-20.238-4.57l-0.3597 40.021z" clip-rule="evenodd" fill="#fff" fill-rule="evenodd"/><path d="m570 442.5v-27.902c-3.885-2.295-8.308-3.594-13-3.594-15.464 0-28 14.103-28 31.5 0 7.498 2.328 14.385 6.217 19.794l34.783-19.798z" clip-rule="evenodd" fill="#fff" fill-rule="evenodd"/><path d="m428.17 430.88c178.34-107.25 281.62-262.87 230.67-347.59-36.947-61.438-144.85-68.719-269.38-27.181l26.765 15.18c90.43-24.018 164.32-16.244 190.51 27.314 41.413 68.866-52.073 201.1-208.81 295.35-156.74 94.253-317.37 114.83-358.78 45.967-23.982-39.879-2.7263-101.01 49.852-163.71v-28.61c-76.614 82.763-109.35 168.6-75.996 224.06 50.948 84.721 236.82 66.46 415.17-40.788z" clip-rule="evenodd" fill="url(#b)" fill-rule="evenodd"/><path d="m638.85 449.62c20.333-37.178 1.348-94.302-45.161-154.58l14.75-17.529c67.619 80.255 96.726 160.46 68.46 212.15-45.906 83.937-225.75 60.851-401.7-51.566-10.818-6.911-21.368-13.999-31.634-21.236 12.73-9.021 21.288-23.275 22.33-39.511 8.274 5.745 16.748 11.393 25.408 16.927 154.63 98.796 310.23 123.58 347.55 55.346zm-442.28-126.61c-96.071-85.207-146.58-178.32-117.69-231.16 24.849-45.435 102.15-49.626 196.69-18.179l21.201-12.193c-120.92-44.328-223.9-39.518-257.04 21.088-34.94 63.888 17.786 171.36 122.55 268.63 6.674-13.601 19.135-24 34.284-28.187zm312.78-114.79 20.158-9.223c-0.807-0.684-1.616-1.368-2.428-2.05l-18.516 10.608c0.263 0.222 0.524 0.443 0.786 0.665z" clip-rule="evenodd" fill="url(#a)" fill-rule="evenodd"/><ellipse cx="597.5" cy="69" rx="69.5" ry="69" fill="#fff"/><path d="m211.5 443c38.384 0 69.5-30.892 69.5-69s-31.116-69-69.5-69-69.5 30.892-69.5 69 31.116 69 69.5 69zm0.5-24c24.853 0 45-20.147 45-45s-20.147-45-45-45-45 20.147-45 45 20.147 45 45 45z" clip-rule="evenodd" fill="#fff" fill-rule="evenodd"/><defs><linearGradient id="b" x1="293.75" x2="239.85" y1="524.96" y2="45.543" gradientUnits="userSpaceOnUse"><stop stop-color="#E1E0E0" offset=".16"/><stop stop-color="#E3E2E2" offset=".35"/><stop stop-color="#fff" offset=".69"/></linearGradient><linearGradient id="a" x1="687.64" x2="48.974" y1="344.87" y2="413.45" gradientUnits="userSpaceOnUse"><stop stop-color="#E1E0E0" offset=".16"/><stop stop-color="#E3E2E2" offset=".34"/><stop stop-color="#fff" offset=".69"/></linearGradient></defs></svg>',

			mount(el, fileInfo, context) {
				if (tabInstance) {
					tabInstance.$destroy()
				}

				getExternalStorageConfig(fileInfo).then(result => {
					let activeTab = 'cidgravity_gateway'

					tabInstance = new View({
						parent: context,
						propsData: {
							isCidgravityStorage: result.isCidgravityStorage,
							isError: result.isError,
						},
					})

					tabInstance.setFileInfo(fileInfo)

					if (result.isCidgravityStorage) {
						tabInstance.setExternalStorageConfiguration(result.configuration)
						tabInstance.loadFileMetadata()
					} else {
						tabInstance.setLoading(false)
						activeTab = 'comments'
					}

					tabInstance.$mount(el)
					window.OCA.Files.Sidebar.setActiveTab(activeTab)
				})
			},

			update(fileInfo) {
				if (tabInstance) {
					let activeTab = 'cidgravity_gateway'

					getExternalStorageConfig(fileInfo).then(result => {
						tabInstance.setFileInfo(fileInfo)

						if (result.isCidgravityStorage) {
							tabInstance.setExternalStorageConfiguration(result.configuration)
							tabInstance.loadFileMetadata()
						} else {
							activeTab = 'comments'
							tabInstance.setIsCidgravityStorage(result.isCidgravityStorage)
							tabInstance.setIsError(result.isError)
							tabInstance.setLoading(false)
						}

						window.OCA.Files.Sidebar.setActiveTab(activeTab)
						tabInstance.$forceUpdate()
					})
				}
			},

			destroy() {
				tabInstance.$destroy()
				tabInstance = null
			},
		})

		OCA.Files.Sidebar.registerTab(gatewayTab)
	}
})
