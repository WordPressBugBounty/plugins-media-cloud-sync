const methods = ['get', 'post', 'put', 'delete'];

export default class FetchWP {
	constructor(options = {}) {
		this.options = options;

		if (!options.restURL) throw new Error('restURL option is required');

		if (!options.restNonce) throw new Error('restNonce option is required');

		methods.forEach((method) => {
			this[method] = this._setup(method);
		});
	}

	/**
	 * Internal method to setup the fetch request.
	 *
	 * @param {string} method HTTP method to use.
	 * @return {function} A function that takes an endpoint and data as arguments.
	 * @example
	 * const instance = new FetchWP();
	 * const get = instance._setup('get');
	 * const response = get('/users');
	 */
	_setup(method) {
		return (endpoint = '/', data = false, options = {}) => {
			const fetchObject = {
				credentials: 'same-origin',
				method,
				headers: {
					Accept: 'application/json',
					'Content-Type': 'application/json',
					'X-WP-Nonce': this.options.restNonce
				},
				...options
			};

			if (data) {
				fetchObject.body = JSON.stringify(data);
			}

			return fetch(this.options.restURL + endpoint, fetchObject).then(
				(response) =>
					response
						.json()
						.then((json) =>
							response.ok ? json : Promise.reject(json)
						)
			);
		};
	}
}
