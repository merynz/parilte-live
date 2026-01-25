import { createReduxStore, register } from '@wordpress/data'

const DEFAULT_STATE = {
	isEditingPalettes: false,
	customPalettes: [],
	loading: false,
}

const actions = {
	setCustomPalettes(palettes) {
		return { type: 'SET_CUSTOM_PALETTES', palettes }
	},

	setLoading(loading) {
		return { type: 'SET_LOADING', loading }
	},

	// --- Async actions ---
	*fetchCustomPalettes() {
		yield actions.setLoading(true)

		const response = yield {
			type: 'FETCH_FROM_SERVER',
			url: `${window.ajaxurl}?action=blocksy_get_custom_palettes`,
			method: 'POST',
		}

		if (response?.data?.palettes) {
			yield actions.setCustomPalettes(response.data.palettes)
		}

		yield actions.setLoading(false)
	},

	*syncCustomPalettes(palettes) {
		yield actions.setLoading(true)

		yield {
			type: 'POST_TO_SERVER',
			url: `${window.ajaxurl}?action=blocksy_sync_custom_palettes`,
			body: JSON.stringify({ palettes }),
			method: 'POST',
		}

		yield actions.setCustomPalettes(palettes)
		yield actions.setLoading(false)
	},
}

const store = createReduxStore('ct/color-palette-store', {
	reducer(state = DEFAULT_STATE, action) {
		switch (action.type) {
			case 'SET_CUSTOM_PALETTES':
				return { ...state, customPalettes: action.palettes }

			case 'SET_LOADING':
				return { ...state, loading: action.loading }

			default:
				return state
		}
	},

	actions,

	selectors: {
		getCustomPalettes: (state) => state.customPalettes,
		isLoading: (state) => state.loading,
	},

	// --- Controls tell WP data how to handle async effects ---
	controls: {
		FETCH_FROM_SERVER({ url, method }) {
			return fetch(url, {
				method,
				headers: {
					Accept: 'application/json',
					'Content-Type': 'application/json',
				},
				body: JSON.stringify({}),
			}).then((r) => r.json())
		},

		POST_TO_SERVER({ url, body, method }) {
			return fetch(url, {
				method,
				headers: {
					Accept: 'application/json',
					'Content-Type': 'application/json',
				},
				body,
			}).then((r) => r.json())
		},
	},
})

register(store)

export default store
